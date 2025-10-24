<?php
/**
 *  Api Controller class.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

declare(strict_types=1);

namespace Automattic\WooCommerce\ProductFeedForOpenAI\API\Controllers;

use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\ProductWalker;
use Automattic\WooCommerce\ProductFeedForOpenAI\Platforms\OpenAI\OpenAIIntegration;
use Automattic\WooCommerce\ProductFeedForOpenAI\Storage\StreamFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API controller for REST endpoints
 */
class ApiController {
	/**
	 * OpenAI integration instance.
	 *
	 * @var OpenAIIntegration
	 */
	private OpenAIIntegration $openai_integration;

	/**
	 * Dependency injector.
	 *
	 * @param OpenAIIntegration $openai_integration The OpenAI integration.
	 */
	public function init( OpenAIIntegration $openai_integration ) {
		$this->openai_integration = $openai_integration;
	}

	/**
	 * Initialize API endpoints
	 */
	public function initialize(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register REST API routes
	 */
	public function register_routes(): void {
		// Admin-only preview endpoint.
		register_rest_route(
			'wc/v3',
			'/openai-feed',
			[
				'methods'             => 'GET',
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'callback'            => [ $this, 'handle_preview_feed' ],
				'args'                => [
					'product_id' => [
						'description' => __( 'Product ID to preview in feed.', 'woocommerce-product-feed-openai' ),
						'type'        => 'integer',
						'minimum'     => 1,
					],
				],
			]
		);
	}

	/**
	 * Check if user has permission to access feed endpoints
	 * Supporting both cookie authentication for logged-in users and WooCommerce API keys
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool|\WP_Error True if user has permission, WP_Error otherwise.
	 */
	public function check_admin_permission( \WP_REST_Request $request ) {
		if ( is_user_logged_in() && current_user_can( 'manage_woocommerce' ) ) {
			$nonce = $request->get_header( 'X-WP-Nonce' ) ? $request->get_header( 'X-WP-Nonce' ) : $request->get_param( '_wpnonce' );
			if ( $nonce && wp_verify_nonce( $nonce, 'wp_rest' ) ) {
				return true;
			}

			if ( current_user_can( 'manage_woocommerce' ) ) {
				return true;
			}
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return new \WP_Error(
				'woocommerce_rest_cannot_view',
				__( 'Sorry, you cannot view this resource. Please ensure you are logged in as an administrator.', 'woocommerce-product-feed-openai' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return true;
	}

	/**
	 * Handle preview feed request
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error Response object or error.
	 */
	public function handle_preview_feed( \WP_REST_Request $request ) {
		try {
			$feed = new StreamFeed();

			$product_walker = new ProductWalker(
				$this->openai_integration->get_product_mapper(),
				$this->openai_integration->get_feed_validator(),
				$feed
			);

			$product_walker->add_time_limit( 30 );

			$additional_args = [];
			if ( $request->get_param( 'product_id' ) ) {
				$additional_args['include'] = [ $request->get_param( 'product_id' ) ];
			}

			$product_walker->walk( null, $additional_args );
		} catch ( \Exception $e ) {
			return new \WP_Error(
				'woocommerce_rest_feed_error',
				$e->getMessage(),
				[ 'status' => 500 ]
			);
		}
	}
}
