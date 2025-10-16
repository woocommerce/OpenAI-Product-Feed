<?php
/**
 *  Admin Controller class.
 *
 * @package OAPFW
 */

declare(strict_types=1);

namespace OAPFW\Admin\Controllers;

use OAPFW\Core\Interfaces\SettingsRepositoryInterface;
use OAPFW\Core\Interfaces\FeedGeneratorInterface;
use OAPFW\Core\Interfaces\ValidatorInterface;
use OAPFW\Admin\Helpers\CredentialValidator;
use OAPFW\Admin\Helpers\FeedStatusProvider;
use OAPFW\Admin\Views\AdminViewRenderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin controller for handling admin interface functionality.
 */
class AdminController {

	/**
	 * Settings repository instance.
	 *
	 * @var SettingsRepositoryInterface
	 */
	private SettingsRepositoryInterface $settings;

	/**
	 * Feed generator instance.
	 *
	 * @var FeedGeneratorInterface
	 */
	private FeedGeneratorInterface $feed_generator;

	/**
	 * Validator instance.
	 *
	 * @var ValidatorInterface
	 */
	private ValidatorInterface $validator;

	/**
	 * Credential validator instance.
	 *
	 * @var CredentialValidator
	 */
	private CredentialValidator $credential_validator;

	/**
	 * Status provider instance.
	 *
	 * @var FeedStatusProvider
	 */
	private FeedStatusProvider $status_provider;

	/**
	 * View renderer instance.
	 *
	 * @var AdminViewRenderer
	 */
	private AdminViewRenderer $view_renderer;

	/**
	 * Logger instance.
	 *
	 * @var mixed
	 */
	private $logger;

	const SCHEDULED_ACTION_HOOK = 'oapfw_push_feed_event';

	/**
	 * Constructor.
	 *
	 * @param SettingsRepositoryInterface $settings The settings repository.
	 * @param FeedGeneratorInterface      $feed_generator The feed generator.
	 * @param ValidatorInterface          $validator The validator.
	 */
	public function __construct(
		SettingsRepositoryInterface $settings,
		FeedGeneratorInterface $feed_generator,
		ValidatorInterface $validator
	) {
		$this->settings       = $settings;
		$this->feed_generator = $feed_generator;
		$this->validator      = $validator;
		$this->logger         = function_exists( 'wc_get_logger' ) ? wc_get_logger() : null;

		$this->credential_validator = new CredentialValidator( $settings );
		$this->status_provider      = new FeedStatusProvider( $feed_generator, $validator );
		$this->view_renderer        = new AdminViewRenderer( $settings, $this->credential_validator, $this->status_provider );
	}

	/**
	 * Initialize the admin controller.
	 */
	public function init(): void {
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_wc_settings_tab' ), 50 );
		add_action( 'woocommerce_settings_tabs_oapfw', array( $this, 'render_wc_settings_tab' ) );
		add_action( 'woocommerce_update_options_oapfw', array( $this, 'save_wc_settings' ) );

		add_action( 'admin_post_oapfw_download_feed', array( $this, 'handle_download_feed' ) );
		add_action( 'admin_post_oapfw_push_now', array( $this, 'handle_push_now' ) );

		add_action( self::SCHEDULED_ACTION_HOOK, array( $this, 'cron_push_feed' ) );
		add_action( 'oapfw_push_delta_event', array( $this, 'push_delta_to_endpoint' ), 10, 1 );
		add_action( 'update_option_' . $this->settings->get_option_name(), array( $this, 'maybe_reschedule' ), 10, 3 );

		add_action( 'woocommerce_update_product', array( $this, 'queue_delta_push' ), 10, 1 );
		add_action( 'woocommerce_product_set_stock', array( $this, 'queue_delta_push' ), 10, 1 );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'maybe_push_delta_on_save' ) );

		add_action( 'admin_notices', array( $this, 'maybe_show_admin_notice' ) );
	}

	/**
	 * Add WooCommerce settings tab.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array Modified tabs.
	 */
	public function add_wc_settings_tab( array $tabs ): array {
		$tabs['oapfw'] = __( 'OpenAI Feed', 'openai-product-feed-for-woo' );
		return $tabs;
	}

	/**
	 * Render WooCommerce settings tab.
	 */
	public function render_wc_settings_tab(): void {
		$section = isset( $_GET['section'] ) ? sanitize_key( $_GET['section'] ) : 'push';

		$this->view_renderer->render_tab_navigation( $section );
		$this->render_tab_content( $section );
	}

	/**
	 * Render tab content.
	 *
	 * @param string $section The section to render.
	 */
	private function render_tab_content( string $section ): void {
		$this->view_renderer->render_tab_header();

		switch ( $section ) {
			case 'push':
				$this->view_renderer->render_push_section();
				break;
			case 'settings':
				$this->view_renderer->render_settings_section();
				break;
			default:
				$this->view_renderer->render_push_section();
				break;
		}
	}

	/**
	 * Save WooCommerce settings.
	 */
	public function save_wc_settings(): void {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['_wpnonce'] ), 'woocommerce-settings' ) ) {
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

	/**
	 * Handle feed download.
	 */
	public function handle_download_feed(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'openai-product-feed-for-woo' ) );
		}

		check_admin_referer( 'oapfw_download_feed' );

		$format   = $this->settings->get( 'format', 'json' );
		$filename = 'openai-feed-' . gmdate( 'Ymd-His' ) . '.' . $format;

		$rows    = $this->feed_generator->build_feed();
		$payload = $this->feed_generator->serialize( $rows, $format, $content_type );

		nocache_headers();
		header( 'Content-Type: ' . $content_type );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		echo $payload; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Handle push now action.
	 */
	public function handle_push_now(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'openai-product-feed-for-woo' ) );
		}

		check_admin_referer( 'oapfw_push_now' );

		$this->push_to_endpoint();

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

	/**
	 * Cron job to push feed.
	 */
	public function cron_push_feed(): void {
		$this->push_to_endpoint();
	}

	/**
	 * Push delta to endpoint for specific product.
	 *
	 * @param int $product_id The product ID.
	 */
	public function push_delta_to_endpoint( int $product_id ): void {
		$rows = $this->feed_generator->build_for_product_id( $product_id );
		if ( ! $rows ) {
			return;
		}

		$this->push_feed_data( $rows, true );
	}

	/**
	 * Queue delta push for product.
	 *
	 * @param mixed $product_id_or_obj Product ID or object.
	 */
	public function queue_delta_push( $product_id_or_obj ): void {
		if ( 'true' !== $this->settings->get( 'delivery_enabled', 'false' ) ) {
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

	/**
	 * Maybe push delta on save.
	 *
	 * @param mixed $product Product ID or object.
	 */
	public function maybe_push_delta_on_save( $product ): void {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( $product );
		}

		if ( $product instanceof \WC_Product ) {
			$this->queue_delta_push( $product );
		}
	}

	/**
	 * Maybe reschedule feed delivery.
	 *
	 * @param mixed  $old_value Old option value.
	 * @param mixed  $value New option value.
	 * @param string $option Option name.
	 */
	public function maybe_reschedule( $old_value, $value, $option ): void {
		$enabled = isset( $value['delivery_enabled'] ) && 'true' === $value['delivery_enabled'];

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
				$this->logger->info(
					'Feed delivery scheduled',
					array(
						'source' => 'oapfw',
						'action' => $action_id,
					)
				);
			}
		}
	}

	/**
	 * Maybe show admin notice.
	 */
	public function maybe_show_admin_notice(): void {
		if ( ! isset( $_GET['page'] ) || 'wc-settings' !== $_GET['page'] ) {
			return;
		}

		if ( isset( $_GET['oapfw_message'] ) && 'pushed' === $_GET['oapfw_message'] ) {
			echo '<div class="notice notice-success"><p>' .
				esc_html__( 'Feed push triggered. Check debug log for status.', 'openai-product-feed-for-woo' ) .
				'</p></div>';
		}
	}

	/**
	 * Check if feed can be pushed.
	 *
	 * @return bool True if feed can be pushed.
	 */
	private function can_push_feed(): bool {
		return $this->credential_validator->can_push_feed();
	}

	/**
	 * Push feed to endpoint.
	 */
	private function push_to_endpoint(): void {
		$rows = $this->feed_generator->build_feed();
		$this->push_feed_data( $rows, false );
	}

	/**
	 * Push feed data to endpoint.
	 *
	 * @param array $rows The feed rows.
	 * @param bool  $is_delta Whether this is a delta push.
	 */
	private function push_feed_data( array $rows, bool $is_delta = false ): void {
		$issues = $this->validator->validate_feed( $rows );

		if ( $issues ) {
			return;
		}

		$format   = $this->settings->get( 'format', 'json' );
		$endpoint = $this->credential_validator->get_endpoint_url();

		if ( empty( $endpoint ) ) {
			return;
		}

		$payload = $this->feed_generator->serialize( $rows, $format, $content_type );

		$headers = array( 'Content-Type' => $content_type );
		if ( $is_delta ) {
			$headers['X-Feed-Delta'] = 'true';
		}

		$token = $this->credential_validator->get_auth_token();
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
