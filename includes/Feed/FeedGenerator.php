<?php

declare(strict_types=1);

namespace OAPFW\Feed;

use OAPFW\Core\FeedGeneratorInterface;
use OAPFW\Core\ProductMapperInterface;
use OAPFW\Feed\Serializers\SerializerFactory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Refactored feed generator
 */
class FeedGenerator implements FeedGeneratorInterface {

	private ProductMapperInterface $productMapper;

	public function __construct( ProductMapperInterface $productMapper ) {
		$this->productMapper = $productMapper;
	}

	/**
	 * Build complete feed
	 */
	public function buildFeed(): array {
		if ( ! class_exists( 'WC_Product' ) ) {
			return array();
		}

		$products = $this->getProducts();
		$rows     = array();

		foreach ( $products as $product ) {
			$product_rows = $this->processProduct( $product );
			$rows         = array_merge( $rows, $product_rows );
		}

		return apply_filters( 'oapfw_feed_rows', $rows );
	}

	/**
	 * Build feed for specific product ID
	 */
	public function buildForProductId( int $product_id ): array {
		if ( ! class_exists( 'WC_Product' ) ) {
			return array();
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return array();
		}

		$rows = $this->processProduct( $product );
		return apply_filters( 'oapfw_feed_rows_single', $rows, $product_id );
	}

	/**
	 * Serialize feed data to specified format
	 */
	public function serialize( array $rows, string $format, ?string &$content_type = null ): string {
		$serializer   = SerializerFactory::create( $format );
		$content_type = $serializer->getContentType();

		return $serializer->serialize( $rows );
	}

	/**
	 * Get products for feed generation
	 */
	private function getProducts(): array {
		$args = array(
			'status' => array( 'publish' ),
			'limit'  => -1,
			'type'   => array( 'simple', 'variable', 'variation' ),
			'return' => 'objects',
		);

		return wc_get_products( $args );
	}

	/**
	 * Process individual product (handles variations)
	 */
	private function processProduct( \WC_Product $product ): array {
		$rows = array();

		if ( $product->is_type( 'variable' ) ) {
			// Variable product - process all variations
			foreach ( $product->get_children() as $variation_id ) {
				$variation = wc_get_product( $variation_id );
				if ( $variation ) {
					$rows[] = $this->productMapper->mapProduct( $variation, $product );
				}
			}
		} elseif ( $product->is_type( 'variation' ) ) {
			// Individual variation
			$parent = wc_get_product( $product->get_parent_id() );
			$rows[] = $this->productMapper->mapProduct( $product, $parent );
		} else {
			// Simple product
			$rows[] = $this->productMapper->mapProduct( $product );
		}

		return $rows;
	}
}
