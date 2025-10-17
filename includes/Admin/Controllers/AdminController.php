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
// No additional admin helper/view dependencies needed.

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
	}

	/**
	 * Initialize the admin controller.
	 */
	public function init(): void {
		add_action( 'admin_post_oapfw_download_feed', [ $this, 'handle_download_feed' ] );
		add_action( 'admin_post_oapfw_push_now', [ $this, 'handle_push_now' ] );

		add_action( self::SCHEDULED_ACTION_HOOK, [ $this, 'cron_push_feed' ] );
		add_action( 'oapfw_push_delta_event', [ $this, 'push_delta_to_endpoint' ], 10, 1 );

		add_action( 'woocommerce_update_product', [ $this, 'queue_delta_push' ], 10, 1 );
		add_action( 'woocommerce_product_set_stock', [ $this, 'queue_delta_push' ], 10, 1 );
		add_action( 'woocommerce_admin_process_product_object', [ $this, 'maybe_push_delta_on_save' ] );
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

		// @see https://github.com/woocommerce/OpenAI-Product-Feed/issues/4
		$content_type = null;

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
				[
					'page'          => 'wc-settings',
					'tab'           => 'oapfw',
					'oapfw_message' => 'pushed',
					'oapfw_nonce'   => wp_create_nonce( 'oapfw_push_now' ),
				],
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
				[ $product_id ],
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

		// @see https://github.com/woocommerce/OpenAI-Product-Feed/issues/4
		$content_type = null;

		$payload = $this->feed_generator->serialize( $rows, $format, $content_type );

		$headers = [ 'Content-Type' => $content_type ];
		if ( $is_delta ) {
			$headers['X-Feed-Delta'] = 'true';
		}

		$token = $this->credential_validator->get_auth_token();
		if ( ! empty( $token ) ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		$response = wp_remote_post(
			$endpoint,
			[
				'headers' => $headers,
				'timeout' => 30,
				'body'    => $payload,
			]
		);

		if ( is_wp_error( $response ) ) {
			if ( $this->logger ) {
				$this->logger->error( 'Feed push failed: ' . $response->get_error_message(), [ 'source' => 'oapfw' ] );
			}
		} else {
			$code = wp_remote_retrieve_response_code( $response );
			if ( $this->logger ) {
				if ( $code >= 200 && $code < 300 ) {
					$this->logger->info( 'Feed push successful: HTTP ' . $code, [ 'source' => 'oapfw' ] );
				} else {
					$this->logger->warning( 'Feed push returned HTTP ' . $code, [ 'source' => 'oapfw' ] );
				}
			}
		}
	}
}
