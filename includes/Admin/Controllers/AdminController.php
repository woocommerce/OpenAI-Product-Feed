<?php

declare(strict_types=1);

namespace OAPFW\Admin\Controllers;

use OAPFW\Core\SettingsRepositoryInterface;
use OAPFW\Core\FeedGeneratorInterface;
use OAPFW\Core\ValidatorInterface;
use OAPFW\Admin\Helpers\CredentialValidator;
use OAPFW\Admin\Helpers\FeedStatusProvider;
use OAPFW\Admin\Views\AdminViewRenderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdminController {

	private SettingsRepositoryInterface $settings;
	private FeedGeneratorInterface $feedGenerator;
	private ValidatorInterface $validator;
	private CredentialValidator $credentialValidator;
	private FeedStatusProvider $statusProvider;
	private AdminViewRenderer $viewRenderer;
	private $logger;

	const SCHEDULED_ACTION_HOOK = 'oapfw_push_feed_event';

	public function __construct(
		SettingsRepositoryInterface $settings,
		FeedGeneratorInterface $feedGenerator,
		ValidatorInterface $validator
	) {
		$this->settings      = $settings;
		$this->feedGenerator = $feedGenerator;
		$this->validator     = $validator;
		$this->logger        = function_exists( 'wc_get_logger' ) ? wc_get_logger() : null;
		
		$this->credentialValidator = new CredentialValidator( $settings );
		$this->statusProvider = new FeedStatusProvider( $feedGenerator, $validator );
		$this->viewRenderer = new AdminViewRenderer( $settings, $this->credentialValidator, $this->statusProvider );
	}

	public function init(): void {
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'addWcSettingsTab' ), 50 );
		add_action( 'woocommerce_settings_tabs_oapfw', array( $this, 'renderWcSettingsTab' ) );
		add_action( 'woocommerce_update_options_oapfw', array( $this, 'saveWcSettings' ) );

		add_action( 'admin_post_oapfw_download_feed', array( $this, 'handleDownloadFeed' ) );
		add_action( 'admin_post_oapfw_push_now', array( $this, 'handlePushNow' ) );

		add_action( self::SCHEDULED_ACTION_HOOK, array( $this, 'cronPushFeed' ) );
		add_action( 'oapfw_push_delta_event', array( $this, 'pushDeltaToEndpoint' ), 10, 1 );
		add_action( 'update_option_' . $this->settings->getOptionName(), array( $this, 'maybeReschedule' ), 10, 3 );

		add_action( 'woocommerce_update_product', array( $this, 'queueDeltaPush' ), 10, 1 );
		add_action( 'woocommerce_product_set_stock', array( $this, 'queueDeltaPush' ), 10, 1 );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'maybePushDeltaOnSave' ) );

		add_action( 'admin_notices', array( $this, 'maybeShowAdminNotice' ) );
	}

	public function addWcSettingsTab( array $tabs ): array {
		$tabs['oapfw'] = __( 'OpenAI Feed', 'openai-product-feed-for-woo' );
		return $tabs;
	}

	public function renderWcSettingsTab(): void {
		$section = isset( $_GET['section'] ) ? sanitize_key( $_GET['section'] ) : 'push';

		$this->viewRenderer->renderTabNavigation( $section );
		$this->renderTabContent( $section );
	}

	private function renderTabContent( string $section ): void {
		$this->viewRenderer->renderTabHeader();

		switch ( $section ) {
			case 'push':
				$this->viewRenderer->renderPushSection();
				break;
			case 'settings':
				$this->viewRenderer->renderSettingsSection();
				break;
			default:
				$this->viewRenderer->renderPushSection();
				break;
		}
	}

	public function saveWcSettings(): void {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'woocommerce-settings' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$posted = isset( $_POST['oapfw_settings'] ) && is_array( $_POST['oapfw_settings'] )
			? wp_unslash( $_POST['oapfw_settings'] )
			: array();

		$this->settings->save( $posted );
	}

	public function handleDownloadFeed(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'Permission denied.', 'openai-product-feed-for-woo' ) );
		}

		check_admin_referer( 'oapfw_download_feed' );

		$format   = $this->settings->get( 'format', 'json' );
		$filename = 'openai-feed-' . date( 'Ymd-His' ) . '.' . $format;

		$rows    = $this->feedGenerator->buildFeed();
		$payload = $this->feedGenerator->serialize( $rows, $format, $content_type );

		nocache_headers();
		header( 'Content-Type: ' . $content_type );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		echo $payload; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	public function handlePushNow(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'Permission denied.', 'openai-product-feed-for-woo' ) );
		}

		check_admin_referer( 'oapfw_push_now' );

		$this->pushToEndpoint();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'          => 'wc-settings',
					'tab'           => 'oapfw',
					'oapfw_message' => 'pushed',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function cronPushFeed(): void {
		$this->pushToEndpoint();
	}

	public function pushDeltaToEndpoint( int $product_id ): void {
		$rows = $this->feedGenerator->buildForProductId( $product_id );
		if ( ! $rows ) {
			return;
		}

		$this->pushFeedData( $rows, true );
	}

	public function queueDeltaPush( $product_id_or_obj ): void {
		if ( $this->settings->get( 'delivery_enabled', 'false' ) !== 'true' ) {
			return;
		}

		$product_id = is_numeric( $product_id_or_obj ) 
			? (int) $product_id_or_obj 
			: $product_id_or_obj->get_id();

		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( 
				time() + 30, 
				'oapfw_push_delta_event', 
				array( $product_id ),
				'oapfw'
			);
		}
	}

	public function maybePushDeltaOnSave( $product ): void {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( $product );
		}

		if ( $product instanceof \WC_Product ) {
			$this->queueDeltaPush( $product );
		}
	}

	public function maybeReschedule( $old_value, $value, $option ): void {
		$enabled = isset( $value['delivery_enabled'] ) && $value['delivery_enabled'] === 'true';

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::SCHEDULED_ACTION_HOOK );
		}

		if ( $enabled && function_exists( 'as_schedule_recurring_action' ) ) {
			$action_id = as_schedule_recurring_action( 
				time() + 60,
				900,
				self::SCHEDULED_ACTION_HOOK,
				array(),
				'oapfw'
			);
			
			if ( $this->logger && $action_id ) {
				$this->logger->info( 'Feed delivery scheduled', array(
					'source' => 'oapfw',
					'action' => $action_id,
				) );
			}
		}
	}

	public function maybeShowAdminNotice(): void {
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'wc-settings' ) {
			return;
		}

		if ( isset( $_GET['oapfw_message'] ) && $_GET['oapfw_message'] === 'pushed' ) {
			echo '<div class="notice notice-success"><p>' .
				esc_html__( 'Feed push triggered. Check debug log for status.', 'openai-product-feed-for-woo' ) .
				'</p></div>';
		}
	}

	private function canPushFeed(): bool {
		return $this->credentialValidator->canPushFeed();
	}

	private function pushToEndpoint(): void {
		$rows = $this->feedGenerator->buildFeed();
		$this->pushFeedData( $rows, false );
	}

	private function pushFeedData( array $rows, bool $is_delta = false ): void {
		$issues = $this->validator->validateFeed( $rows );

		if ( $issues ) {
			return;
		}

		$format   = $this->settings->get( 'format', 'json' );
		$endpoint = $this->credentialValidator->getEndpointUrl();

		if ( empty( $endpoint ) ) {
			return;
		}

		$payload = $this->feedGenerator->serialize( $rows, $format, $content_type );

		$headers = array( 'Content-Type' => $content_type );
		if ( $is_delta ) {
			$headers['X-Feed-Delta'] = 'true';
		}

		$token = $this->credentialValidator->getAuthToken();
		if ( ! empty( $token ) ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		$response = wp_remote_post(
			$endpoint,
			array(
				'headers' => $headers,
				'timeout' => 30,
				'body'    => $payload,
			)
		);

		if ( is_wp_error( $response ) ) {
			if ( $this->logger ) {
				$this->logger->error( 'Feed push failed: ' . $response->get_error_message(), array( 'source' => 'oapfw' ) );
			}
		} else {
			$code = wp_remote_retrieve_response_code( $response );
			if ( $this->logger ) {
				if ( $code >= 200 && $code < 300 ) {
					$this->logger->info( 'Feed push successful: HTTP ' . $code, array( 'source' => 'oapfw' ) );
				} else {
					$this->logger->warning( 'Feed push returned HTTP ' . $code, array( 'source' => 'oapfw' ) );
				}
			}
		}
	}
}