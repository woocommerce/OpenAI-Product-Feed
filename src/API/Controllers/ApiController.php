<?php
/**
 *  Api Controller class.
 *
 * @package Automattic\WooCommerce\OAPF
 */

declare(strict_types=1);

namespace Automattic\WooCommerce\OAPF\API\Controllers;

use Automattic\WooCommerce\OAPF\Feed\FeedGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API controller for REST endpoints
 */
class ApiController {
	/**
	 * Feed generator instance.
	 *
	 * @var FeedGenerator
	 */
	private FeedGenerator $feed_generator;

	/**
	 * Dependency injector.
	 *
	 * @param FeedGenerator $feed_generator The feed generator.
	 */
	public function init( FeedGenerator $feed_generator ) {
		$this->feed_generator = $feed_generator;
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
				'permission_callback' => function ( \WP_REST_Request $request ) {
					return $this->check_admin_permission( $request );
				},
				'callback'            => [ $this, 'handle_preview_feed' ],
				'args'                => [
					'product_id' => [
						'description' => __( 'Product ID to preview in feed.', 'openai-product-feed-for-woo' ),
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
				__( 'Sorry, you cannot view this resource. Please ensure you are logged in as an administrator.', 'openai-product-feed-for-woo' ),
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
			$product_id = $request->get_param( 'product_id' );

			if ( $product_id ) {
				$product = wc_get_product( $product_id );
				if ( ! $product ) {
					return new \WP_Error(
						'woocommerce_rest_product_invalid_id',
						__( 'Invalid product ID.', 'openai-product-feed-for-woo' ),
						[ 'status' => 404 ]
					);
				}
				$rows = $this->feed_generator->build_for_product_id( $product_id );
			} else {
				$rows = $this->feed_generator->build_feed();
			}

			$response = rest_ensure_response( $rows );
			$response->header( 'Content-Type', 'application/json; charset=utf-8' );

			return $response;

		} catch ( \Exception $e ) {
			return new \WP_Error(
				'woocommerce_rest_feed_error',
				$e->getMessage(),
				[ 'status' => 500 ]
			);
		}
	}
}
