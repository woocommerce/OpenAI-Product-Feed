<?php
/**
 * POS Catalog API Controller.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Integrations\POSCatalog;

use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * POS Catalog API Controller.
 */
class ApiController {
	const ROUTE_NAMESPACE = 'wc/product-catalog/v1';

	/**
	 * Integration instance.
	 *
	 * @var POSIntegration
	 */
	private POSIntegration $integration;

	/**
	 * Dependency injector.
	 *
	 * @param POSIntegration $integration The integration instance.
	 */
	public function init( POSIntegration $integration ) {
		$this->integration = $integration;
	}

	/**
	 * Register the routes for the API controller.
	 */
	public function register_routes() {
		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/create',
			[
				'methods'  => 'GET', // @todo: Switch back to POST and add validation.
				'callback' => [ $this, 'generate_feed' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	/**
	 * Starts generating a feed.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response The response object.
	 */
	public function generate_feed( WP_REST_Request $request ) { // phpcs:ignore VariableAnalysis
		$status = $this->integration->generate_feed();

		return new WP_REST_Response( $status );
	}
}
