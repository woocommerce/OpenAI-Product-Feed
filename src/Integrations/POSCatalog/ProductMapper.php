<?php
/**
 * ProductMapper class.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Integrations\POSCatalog;

use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\ProductMapperInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product Mapper for the POS catalog.
 */
class ProductMapper implements ProductMapperInterface {
	/**
	 * Map a product to a feed row.
	 *
	 * @param \WC_Product $product The product to map.
	 * @return array The feed row.
	 */
	public function map_product( \WC_Product $product ): array {
		return [
			'id' => $product->get_id(),
		];
	}
}
