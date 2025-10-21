<?php
/**
 *  Api Controller class.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

declare(strict_types=1);

namespace Automattic\WooCommerce\ProductFeedForOpenAI\API\Controllers;

use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\FeedValidatorInterface;
use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\ProductMapperInterface;
use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\ProductWalker;
use Automattic\WooCommerce\ProductFeedForOpenAI\Platforms\OpenAI\Mappers\ProductMapper;
use Automattic\WooCommerce\ProductFeedForOpenAI\Platforms\OpenAI\Validators\FeedValidator;
use Automattic\WooCommerce\ProductFeedForOpenAI\Storage\JsonInMemoryFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API controller for REST endpoints
 */
class ApiController {
	/**
	 * Product mapper instance.
	 *
	 * @var ProductMapperInterface
	 */
	private ProductMapperInterface $product_mapper;

	/**
	 * Feed validator instance.
	 *
	 * @var FeedValidatorInterface
	 */
	private FeedValidatorInterface $feed_validator;

	/**
	 * Dependency injector.
	 *
	 * @param ProductMapper $product_mapper The product mapper.
	 * @param FeedValidator $feed_validator The feed validator.
	 */
	public function init( ProductMapper $product_mapper, FeedValidator $feed_validator ) {
		$this->product_mapper = $product_mapper;
		$this->feed_validator = $feed_validator;
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
	 * @return \WP_REST_Response|\WP_Error Response object or error.
	 */
	public function handle_preview_feed() {
		try {
			$feed = new JsonInMemoryFeed();

			$feed->start();
			$product_walker = new ProductWalker( $this->product_mapper, $this->feed_validator, $feed );
			$product_walker->walk();
			$feed->end();

			$response = rest_ensure_response( $feed->deliver() );
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
