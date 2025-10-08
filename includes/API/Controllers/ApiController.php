<?php

declare(strict_types=1);

namespace OAPFW\API\Controllers;

use OAPFW\Core\SettingsRepositoryInterface;
use OAPFW\Core\FeedGeneratorInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API controller for REST endpoints
 */
class ApiController {

	private SettingsRepositoryInterface $settings;
	private FeedGeneratorInterface $feedGenerator;

	public function __construct(
		SettingsRepositoryInterface $settings,
		FeedGeneratorInterface $feedGenerator
	) {
		$this->settings      = $settings;
		$this->feedGenerator = $feedGenerator;
	}

	/**
	 * Initialize API endpoints
	 */
	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
	}

	/**
	 * Register REST API routes
	 */
	public function registerRoutes(): void {
		// Admin-only preview endpoint following WooCommerce v3 patterns
		register_rest_route(
			'wc/v3',
			'/openai-feed',
			array(
				'methods'             => 'GET',
				'permission_callback' => function( \WP_REST_Request $request ) {
					return $this->checkAdminPermission( $request );
				},
				'callback'            => array( $this, 'handlePreviewFeed' ),
				'args'                => array(
					'product_id' => array(
						'description' => __( 'Product ID to preview in feed.', 'openai-product-feed-for-woo' ),
						'type'        => 'integer',
						'minimum'     => 1,
					),
				),
			)
		);
	}

	/**
	 * Check if user has permission to access feed endpoints
	 * Supporting both cookie authentication for logged-in users and WooCommerce API keys
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool|\WP_Error True if user has permission, WP_Error otherwise.
	 */
	public function checkAdminPermission( \WP_REST_Request $request ) {
		// Check if user is logged in via WordPress session (cookie auth)
		if ( is_user_logged_in() && current_user_can( 'manage_woocommerce' ) ) {
			// Verify nonce for cookie authentication
			$nonce = $request->get_header( 'X-WP-Nonce' ) ?: $request->get_param( '_wpnonce' );
			if ( $nonce && wp_verify_nonce( $nonce, 'wp_rest' ) ) {
				return true;
			}
			
			// For direct browser access without nonce, still allow if user can manage WooCommerce
			// This enables preview links to work for logged-in admins
			if ( current_user_can( 'manage_woocommerce' ) ) {
				return true;
			}
		}

		// Fallback to WooCommerce authentication patterns
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return new \WP_Error(
				'woocommerce_rest_cannot_view',
				__( 'Sorry, you cannot view this resource. Please ensure you are logged in as an administrator.', 'openai-product-feed-for-woo' ),
				array( 'status' => rest_authorization_required_code() )
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
	public function handlePreviewFeed( \WP_REST_Request $request ) {
		try {
			$product_id = $request->get_param( 'product_id' );

			if ( $product_id ) {
				$product = wc_get_product( $product_id );
				if ( ! $product ) {
					return new \WP_Error(
						'woocommerce_rest_product_invalid_id',
						__( 'Invalid product ID.', 'openai-product-feed-for-woo' ),
						array( 'status' => 404 )
					);
				}
				$rows = $this->feedGenerator->buildForProductId( $product_id );
			} else {
				$rows = $this->feedGenerator->buildFeed();
			}

			$response = rest_ensure_response( $rows );
			$response->header( 'Content-Type', 'application/json; charset=utf-8' );

			return $response;

		} catch ( \Exception $e ) {
			return new \WP_Error(
				'woocommerce_rest_feed_error',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}
}
