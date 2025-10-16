<?php

declare(strict_types=1);

namespace OAPFW\Core\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product mapper interface
 */
interface ProductMapperInterface {

	public function mapProduct( \WC_Product $product, ?\WC_Product $parent = null ): array;
}
