<?php
/**
 *  Feed Generator class.
 *
 * @package OAPFW
 */

declare(strict_types=1);

namespace OAPFW\Feed;

use OAPFW\Core\Interfaces\FeedGeneratorInterface;
use OAPFW\Core\Interfaces\ProductMapperInterface;
use OAPFW\Feed\Serializers\SerializerFactory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Refactored feed generator
 */
class FeedGenerator implements FeedGeneratorInterface {

	/**
	 * Product mapper instance.
	 *
	 * @var ProductMapperInterface
	 */
	private ProductMapperInterface $product_mapper;

	/**
	 * Constructor.
	 *
	 * @param ProductMapperInterface $product_mapper The product mapper.
	 */
	public function __construct( ProductMapperInterface $product_mapper ) {
		$this->product_mapper = $product_mapper;
	}

	/**
	 * Build complete feed.
	 *
	 * @return array Feed rows.
	 */
	public function build_feed(): array {
		if ( ! class_exists( 'WC_Product' ) ) {
			return [];
		}

		$products = $this->getProducts();
		$rows     = [];

		foreach ( $products as $product ) {
			$product_rows = $this->process_product( $product );
			$rows         = array_merge( $rows, $product_rows );
		}

		/**
		 * Filter feed rows before returning.
		 *
		 * @param array $rows The feed rows.
		 * @since 1.0.0
		 */
		return apply_filters( 'oapfw_feed_rows', $rows );
	}

	/**
	 * Build feed for specific product ID.
	 *
	 * @param int $product_id The product ID.
	 * @return array Feed rows.
	 */
	public function build_for_product_id( int $product_id ): array {
		if ( ! class_exists( 'WC_Product' ) ) {
			return [];
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return [];
		}

		$rows = $this->process_product( $product );
		/**
		 * Filter single product feed rows.
		 *
		 * @param array $rows The feed rows.
		 * @param int   $product_id The product ID.
		 * @since 1.0.0
		 */
		return apply_filters( 'oapfw_feed_rows_single', $rows, $product_id );
	}

	/**
	 * Serialize feed data to specified format.
	 *
	 * @param array       $rows The feed rows.
	 * @param string      $format The output format.
	 * @param string|null $content_type The content type (passed by reference).
	 * @return string Serialized data.
	 */
	public function serialize( array $rows, string $format, ?string &$content_type = null ): string {
		$serializer   = SerializerFactory::create( $format );
		$content_type = $serializer->get_content_type();

		return $serializer->serialize( $rows );
	}

	/**
	 * Get products for feed generation
	 */
	private function getProducts(): array {
		$args = [
			'status' => [ 'publish' ],
			'limit'  => -1,
			'type'   => [ 'simple', 'variable', 'variation' ],
			'return' => 'objects',
		];

		return wc_get_products( $args );
	}

	/**
	 * Process individual product (handles variations).
	 *
	 * @param \WC_Product $product The product to process.
	 * @return array Product rows.
	 */
	private function process_product( \WC_Product $product ): array {
		$rows = [];

		if ( $product->is_type( 'variable' ) ) {
			// Variable product - process all variations.
			foreach ( $product->get_children() as $variation_id ) {
				$variation = wc_get_product( $variation_id );
				if ( $variation ) {
					$rows[] = $this->product_mapper->map_product_by_schema( $variation, $product );
				}
			}
		} elseif ( $product->is_type( 'variation' ) ) {
			// Individual variation.
			$parent_product = wc_get_product( $product->get_parent_id() );
			$rows[]         = $this->product_mapper->map_product_by_schema( $product, $parent_product );
		} else {
			// Simple product.
			$rows[] = $this->product_mapper->map_product_by_schema( $product );
		}

		return $rows;
	}
}
