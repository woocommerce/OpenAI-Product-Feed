<?php
/**
 *  Product Mapper Interface interface.
 *
 * @package OAPFW
 */

declare(strict_types=1);

namespace OAPFW\Core\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product mapper interface
 */
interface ProductMapperInterface {

	/**
	 * Map a product to feed data.
	 *
	 * @param \WC_Product      $product The product to map.
	 * @param \WC_Product|null $parent_product The parent product (for variations).
	 * @return array Mapped product data.
	 */
	public function map_product_by_schema( \WC_Product $product, ?\WC_Product $parent_product = null ): array;
}
