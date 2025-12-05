<?php
/**
 * ProductMapper class.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Integrations\POSCatalog;

use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\ProductMapperInterface;
use WC_Product;
use WC_REST_Products_Controller;
use WC_REST_Product_Variations_Controller;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product Mapper for the POS catalog.
 *
 * Uses WooCommerce REST API controllers to map product data.
 */
class ProductMapper implements ProductMapperInterface {
	/**
	 * Fields to include in the product mapping.
	 *
	 * @var string|null Fields to include in the product mapping.
	 */
	private ?string $fields = null;

	/**
	 * REST controller instance for products.
	 *
	 * @var WC_REST_Products_Controller|null
	 */
	private ?WC_REST_Products_Controller $products_controller = null;

	/**
	 * REST controller instance for variations.
	 *
	 * @var WC_REST_Product_Variations_Controller|null
	 */
	private ?WC_REST_Product_Variations_Controller $variations_controller = null;

	/**
	 * Cached REST request instance.
	 *
	 * @var WP_REST_Request|null
	 */
	private ?WP_REST_Request $rest_request = null;

	/**
	 * Initialize the mapper.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->products_controller   = new WC_REST_Products_Controller();
		$this->variations_controller = new WC_REST_Product_Variations_Controller();
	}

	/**
	 * Set fields to include in the product mapping.
	 *
	 * @param string|null $fields Fields to include in the product mapping.
	 * @return void
	 */
	public function set_fields( ?string $fields = null ): void {
		$this->fields       = $fields;
		$this->rest_request = null; // Invalidate the cached request.
	}

	/**
	 * Map WooCommerce product to catalog row
	 *
	 * @param WC_Product $product Product to map.
	 * @return array Mapped product data array.
	 */
	public function map_product( WC_Product $product ): array {
		$controller = $product->is_type( 'variation' )
			? $this->variations_controller
			: $this->products_controller;

		$request  = $this->get_rest_request();
		$response = $controller->prepare_object_for_response( $product, $request );

		// Apply _fields filtering (normally done by REST server dispatch).
		if ( null !== $this->fields ) {
			$response = rest_filter_response_fields( $response, null, $request );
		}

		$row = $response->get_data();

		/**
		 * Filter mapped catalog product data.
		 *
		 * @since 1.0.0
		 * @param array      $row     Mapped product data.
		 * @param WC_Product $product Product object.
		 */
		return apply_filters( 'oapfw_map_catalog_product', $row, $product );
	}

	/**
	 * Get the REST request instance.
	 *
	 * @return WP_REST_Request
	 */
	protected function get_rest_request(): WP_REST_Request {
		if ( null === $this->rest_request ) {
			$this->rest_request = new WP_REST_Request( 'GET' );
			$this->rest_request->set_param( 'context', 'view' );

			if ( null !== $this->fields ) {
				$this->rest_request->set_param( '_fields', $this->fields );
			}
		}

		return $this->rest_request;
	}
}
