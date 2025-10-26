<?php
/**
 * POS Catalog API Controller.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Integrations\POSCatalog;

use Automattic\WooCommerce\ProductFeedForOpenAI\Core\DependencyManagement\Container;
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
	 * Container instance.
	 *
	 * @var Container
	 */
	private $container;

	/**
	 * Dependency injector.
	 *
	 * @param Container $container The container instance. Everything else will be dynamic.
	 */
	public function init( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Register the routes for the API controller.
	 */
	public function register_routes() {
		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/create',
			[
				'methods'             => 'GET', // @todo: Switch back to POST and add validation.
				'callback'            => [ $this, 'generate_feed' ],
				'permission_callback' => '__return_true', // @todo: This should be a proper permission callback.
				'args'                => [
					'force' => [
						'type'        => 'boolean',
						'default'     => false,
						'description' => 'Force regeneration of the feed. NOOP if generation is in progress.',
					],
				],
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
		$generator = $this->container->get( AsyncGenerator::class );
		try {
			$response = $request->get_param( 'force' ) ? $generator->force_regeneration() : $generator->get_status();
			if ( isset( $response['action_id'] ) ) {
				unset( $response['action_id'] );
			}
			if ( isset( $response['path'] ) ) {
				unset( $response['path'] );
			}
		} catch ( \Exception $e ) {
			return new WP_REST_Response(
				[
					'success' => false,
					'message' => $e->getMessage(),
				],
				500
			);
		}
		return new WP_REST_Response( $response );
	}
}
