<?php
/**
 * Catalog Product Mapper class.
 *
 * @package OAPFW
 */

declare(strict_types=1);

namespace OAPFW\Platforms\OpenAI\Mappers;

use OAPFW\Core\Interfaces\ProductMapperInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps WooCommerce products to catalog format
 *
 * Converts WooCommerce product data into catalog specification format.
 */
final class CatalogProductMapper implements ProductMapperInterface {

	/**
	 * Product meta cache to prevent N+1 queries.
	 *
	 * @var array
	 */
	protected array $product_meta_cache = [];

	/**
	 * Map WooCommerce product to catalog row
	 *
	 * @param \WC_Product      $product Product to map.
	 * @param \WC_Product|null $parent_product Parent product for variations.
	 * @return array Mapped product data array.
	 */
	public function map_product( \WC_Product $product, ?\WC_Product $parent_product = null ): array {
		$row = [
			'id'                => $this->get_id( $product ),
			'name'              => $this->get_name( $product ),
			'type'              => $this->get_type( $product ),
			'description'       => $this->get_description( $product ),
			'short_description' => $this->get_short_description( $product ),
			'sku'               => $this->get_sku( $product ),
			'global_unique_id'  => $this->get_global_unique_id( $product ),
			'price'             => $this->get_price( $product ),
			'downloadable'      => $this->get_downloadable( $product ),
			'parent_id'         => $this->get_parent_id( $product, $parent_product ),
			'images'            => $this->get_images( $product, $parent_product ),
			'attributes'        => $this->get_attributes( $product ),
			'manage_stock'      => $this->get_manage_stock( $product ),
			'stock_quantity'    => $this->get_stock_quantity( $product ),
			'stock_status'      => $this->get_stock_status( $product ),
		];

		/**
		 * Filter mapped catalog product data.
		 *
		 * @since 1.0.0
		 * @param array            $row     Mapped product data.
		 * @param \WC_Product      $product Product object.
		 * @param \WC_Product|null $parent_product Parent product for variations.
		 */
		return apply_filters( 'oapfw_map_catalog_product', $row, $product, $parent_product );
	}

	/**
	 * Get product ID
	 *
	 * @param \WC_Product $product Product object.
	 * @return int Product ID.
	 */
	protected function get_id( \WC_Product $product ): int {
		return $product->get_id();
	}

	/**
	 * Get product name
	 *
	 * @param \WC_Product $product Product object.
	 * @return string Product name.
	 */
	protected function get_name( \WC_Product $product ): string {
		return wp_strip_all_tags( $product->get_name() );
	}

	/**
	 * Get product type
	 *
	 * @param \WC_Product $product Product object.
	 * @return string Product type.
	 */
	protected function get_type( \WC_Product $product ): string {
		return $product->get_type();
	}

	/**
	 * Get product description
	 *
	 * @param \WC_Product $product Product object.
	 * @return string Product description.
	 */
	protected function get_description( \WC_Product $product ): string {
		$description = $product->get_description();
		return $description ? wp_strip_all_tags( $description ) : '';
	}

	/**
	 * Get product short description
	 *
	 * @param \WC_Product $product Product object.
	 * @return string Product short description.
	 */
	protected function get_short_description( \WC_Product $product ): string {
		$short_description = $product->get_short_description();
		return $short_description ? wp_strip_all_tags( $short_description ) : '';
	}

	/**
	 * Get product SKU
	 *
	 * @param \WC_Product $product Product object.
	 * @return string Product SKU.
	 */
	protected function get_sku( \WC_Product $product ): string {
		$sku = $product->get_sku();
		return $sku ? $sku : '';
	}

	/**
	 * Get product global unique ID
	 *
	 * @param \WC_Product $product Product object.
	 * @return string Global unique ID.
	 */
	protected function get_global_unique_id( \WC_Product $product ): string {
		// Try to get GTIN first.
		$gtin = $this->get_meta_value( $product, '_gtin' );
		if ( $gtin ) {
			return $gtin;
		}

		// Fallback to SKU or product ID.
		$sku = $product->get_sku();
		return $sku ? $sku : (string) $product->get_id();
	}

	/**
	 * Get product price
	 *
	 * @param \WC_Product $product Product object.
	 * @return float Product price.
	 */
	protected function get_price( \WC_Product $product ): float {
		$price = $product->get_price();
		return $price ? (float) $price : 0.0;
	}

	/**
	 * Get product downloadable status
	 *
	 * @param \WC_Product $product Product object.
	 * @return bool True if downloadable.
	 */
	protected function get_downloadable( \WC_Product $product ): bool {
		return $product->is_downloadable();
	}

	/**
	 * Get parent product ID
	 *
	 * @param \WC_Product      $product Product object.
	 * @param \WC_Product|null $parent_product Parent product for variations.
	 * @return int|null Parent product ID or null.
	 */
	protected function get_parent_id( \WC_Product $product, ?\WC_Product $parent_product = null ): ?int {
		if ( $parent_product ) {
			return $parent_product->get_id();
		}

		if ( $product->is_type( 'variation' ) ) {
			return $product->get_parent_id();
		}

		return null;
	}

	/**
	 * Get product images
	 *
	 * @param \WC_Product      $product Product object.
	 * @param \WC_Product|null $parent_product Parent product for fallback.
	 * @return array Array of image URLs.
	 */
	protected function get_images( \WC_Product $product, ?\WC_Product $parent_product = null ): array {
		$images = [];

		// Get main image.
		$image_id = $product->get_image_id();
		if ( ! $image_id && $parent_product ) {
			$image_id = $parent_product->get_image_id();
		}
		if ( $image_id ) {
			$image_url = wp_get_attachment_url( $image_id );
			if ( $image_url ) {
				$images[] = $image_url;
			}
		}

		// Get gallery images.
		$gallery_ids = $product->get_gallery_image_ids();
		if ( empty( $gallery_ids ) && $parent_product ) {
			$gallery_ids = $parent_product->get_gallery_image_ids();
		}

		foreach ( $gallery_ids as $gallery_id ) {
			$gallery_url = wp_get_attachment_url( $gallery_id );
			if ( $gallery_url ) {
				$images[] = $gallery_url;
			}
		}

		return $images;
	}

	/**
	 * Get product attributes
	 *
	 * @param \WC_Product $product Product object.
	 * @return array Product attributes.
	 */
	protected function get_attributes( \WC_Product $product ): array {
		$attributes = [];
		$product_attributes = $product->get_attributes();

		foreach ( $product_attributes as $key => $attribute ) {
			if ( is_a( $attribute, 'WC_Product_Attribute' ) ) {
				$attributes[ $key ] = [
					'name'    => wc_attribute_label( $attribute->get_name() ),
					'options' => $attribute->get_options(),
					'visible' => $attribute->get_visible(),
				];
			} else {
				// For simple attributes (variations).
				$attributes[ $key ] = $attribute;
			}
		}

		return $attributes;
	}

	/**
	 * Get manage stock status
	 *
	 * @param \WC_Product $product Product object.
	 * @return bool True if managing stock.
	 */
	protected function get_manage_stock( \WC_Product $product ): bool {
		return $product->get_manage_stock();
	}

	/**
	 * Get stock quantity
	 *
	 * @param \WC_Product $product Product object.
	 * @return int|null Stock quantity or null.
	 */
	protected function get_stock_quantity( \WC_Product $product ): ?int {
		return $product->get_stock_quantity();
	}

	/**
	 * Get stock status
	 *
	 * @param \WC_Product $product Product object.
	 * @return string Stock status.
	 */
	protected function get_stock_status( \WC_Product $product ): string {
		return $product->get_stock_status();
	}

	/**
	 * Get meta value with caching
	 *
	 * @param \WC_Product $product Product object.
	 * @param string      $key Meta key to retrieve.
	 * @return string|null Meta value or null if not found.
	 */
	protected function get_meta_value( \WC_Product $product, string $key ): ?string {
		$product_id = $product->get_id();

		if ( ! isset( $this->product_meta_cache[ $product_id ] ) ) {
			$this->product_meta_cache[ $product_id ] = get_post_meta( $product_id );
		}

		$value = isset( $this->product_meta_cache[ $product_id ][ $key ][0] )
			? $this->product_meta_cache[ $product_id ][ $key ][0]
			: null;

		return ! empty( $value ) ? wp_strip_all_tags( $value ) : null;
	}
}
