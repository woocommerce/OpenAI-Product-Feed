<?php
/**
 *  Admin Controller class.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

declare(strict_types=1);

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Admin\Controllers;

use Automattic\WooCommerce\ProductFeedForOpenAI\Settings\SettingsRepository;
use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\FeedGenerator;
use Automattic\WooCommerce\ProductFeedForOpenAI\Platforms\OpenAI\Validators\FeedValidator;
use Automattic\WooCommerce\ProductFeedForOpenAI\Admin\Helpers\CredentialValidator;

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
	 * @var SettingsRepository
	 */
	private SettingsRepository $settings;

	/**
	 * Feed generator instance.
	 *
	 * @var FeedGenerator
	 */
	private FeedGenerator $feed_generator;

	/**
	 * Validator instance.
	 *
	 * @var FeedValidator
	 */
	private FeedValidator $validator;

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

	const SCHEDULED_ACTION_HOOK = 'wpfoai_push_feed_event';

	/**
	 * Dependencies injector.
	 *
	 * @param SettingsRepository $settings The settings repository.
	 * @param FeedGenerator      $feed_generator The feed generator.
	 * @param FeedValidator      $validator The validator.
	 */
	public function init(
		SettingsRepository $settings,
		FeedGenerator $feed_generator,
		FeedValidator $validator
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
	public function initialize(): void {
		add_action( self::SCHEDULED_ACTION_HOOK, [ $this, 'cron_push_feed' ] );
		add_action( 'wpfoai_push_delta_event', [ $this, 'push_delta_to_endpoint' ], 10, 1 );

		add_action( 'woocommerce_update_product', [ $this, 'queue_delta_push' ], 10, 1 );
		add_action( 'woocommerce_product_set_stock', [ $this, 'queue_delta_push' ], 10, 1 );
		add_action( 'woocommerce_admin_process_product_object', [ $this, 'maybe_push_delta_on_save' ] );
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
				'wpfoai_push_delta_event',
				[ $product_id ],
				'wpfoai'
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

		$endpoint = $this->credential_validator->get_endpoint_url();

		if ( empty( $endpoint ) ) {
			return;
		}

		$payload = $this->feed_generator->serialize( $rows );
		$headers = [ 'Content-Type' => 'application/json' ];
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
				$this->logger->error( 'Feed push failed: ' . $response->get_error_message(), [ 'source' => 'wpfoai' ] );
			}
		} else {
			$code = wp_remote_retrieve_response_code( $response );
			if ( $this->logger ) {
				if ( $code >= 200 && $code < 300 ) {
					$this->logger->info( 'Feed push successful: HTTP ' . $code, [ 'source' => 'wpfoai' ] );
				} else {
					$this->logger->warning( 'Feed push returned HTTP ' . $code, [ 'source' => 'wpfoai' ] );
				}
			}
		}
	}
}
