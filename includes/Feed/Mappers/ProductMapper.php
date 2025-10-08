<?php

declare(strict_types=1);

namespace OAPFW\Feed\Mappers;

use OAPFW\Core\ProductMapperInterface;
use OAPFW\Core\SettingsRepositoryInterface;
use OAPFW\Utils\StringHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps WooCommerce products to OpenAI feed format using schema-driven approach
 */
class ProductMapper extends SchemaBasedMapper implements ProductMapperInterface {

	private static ?array $cached_shipping_data = null;
	private static ?array $cached_shipping_zones = null;
	private static ?bool $cached_has_local_pickup = null;

	/**
	 * Map WooCommerce product to feed row
	 */
	public function mapProduct( \WC_Product $product, ?\WC_Product $parent = null ): array {
		return $this->mapProductBySchema( $product, $parent );
	}

	// Schema mapper method implementations

	protected function getEnableSearch( \WC_Product $product, ?\WC_Product $parent ): string {
		$override = $this->getMetaValue( $product, '_oapfw_enable_search' );
		if ( $override !== null && $override !== '' ) {
			return StringHelper::boolString( $override );
		}
		return $this->settings->get( 'enable_search_default', 'true' );
	}

	protected function getEnableCheckout( \WC_Product $product, ?\WC_Product $parent ): string {
		$override = $this->getMetaValue( $product, '_oapfw_enable_checkout' );
		if ( $override !== null && $override !== '' ) {
			return StringHelper::boolString( $override );
		}
		return $this->settings->get( 'enable_checkout_default', 'false' );
	}

	protected function getId( \WC_Product $product, ?\WC_Product $parent ): string {
		return (string) $product->get_id();
	}

	protected function getTitle( \WC_Product $product, ?\WC_Product $parent ): string {
		return wp_strip_all_tags( $product->get_name() );
	}

	protected function getDescription( \WC_Product $product, ?\WC_Product $parent ): string {
		$description = $product->get_description() ?: $product->get_short_description();
		return wp_strip_all_tags( $description );
	}

	protected function getLink( \WC_Product $product, ?\WC_Product $parent ): string {
		return get_permalink( $product->get_id() );
	}

	protected function getGtin( \WC_Product $product, ?\WC_Product $parent ): ?string {
		$gtin = $this->getMetaValue( $product, '_gtin' );
		return $gtin ?: 'MISSING';
	}

	protected function getMpn( \WC_Product $product, ?\WC_Product $parent ): ?string {
		return $this->getMetaValue( $product, '_mpn' );
	}

	protected function getProductCategory( \WC_Product $product, ?\WC_Product $parent ): ?string {
		return $this->getCategoryPath( $product );
	}

	protected function getBrand( \WC_Product $product, ?\WC_Product $parent ): ?string {
		$brand = $product->get_attribute( 'pa_brand' );
		if ( ! $brand && $parent ) {
			$brand = $parent->get_attribute( 'pa_brand' );
		}
		if ( ! $brand ) {
			$brand = $this->getMetaValue( $product, '_brand' );
		}
		return $brand ?: 'Generic';
	}

	protected function getMaterial( \WC_Product $product, ?\WC_Product $parent ): ?string {
		return $product->get_attribute( 'pa_material' ) ?: null;
	}

	protected function getCondition( \WC_Product $product, ?\WC_Product $parent ): ?string {
		return $this->getMetaValue( $product, '_oapfw_condition' );
	}

	protected function getAgeGroup( \WC_Product $product, ?\WC_Product $parent ): ?string {
		return $this->getMetaValue( $product, '_oapfw_age_group' );
	}

	protected function getWeight( \WC_Product $product, ?\WC_Product $parent ): ?string {
		return $this->formatWeight( $product ) ?: '0 kg';
	}

	protected function getLength( \WC_Product $product, ?\WC_Product $parent ): ?string {
		return $this->formatDimension( $product->get_length() );
	}

	protected function getWidth( \WC_Product $product, ?\WC_Product $parent ): ?string {
		return $this->formatDimension( $product->get_width() );
	}

	protected function getHeight( \WC_Product $product, ?\WC_Product $parent ): ?string {
		return $this->formatDimension( $product->get_height() );
	}

	protected function getDimensions( \WC_Product $product, ?\WC_Product $parent ): ?string {
		return $this->formatDimensions( $product );
	}

	protected function getImageLink( \WC_Product $product, ?\WC_Product $parent ): string {
		return $this->getMainImage( $product, $parent );
	}

	protected function getAdditionalImageLink( \WC_Product $product, ?\WC_Product $parent ): array {
		return $this->getGalleryImages( $product, $parent );
	}

	protected function getVideoLink( \WC_Product $product, ?\WC_Product $parent ): ?string {
		return $this->getMetaValue( $product, '_oapfw_video_link' );
	}

	protected function getModel3dLink( \WC_Product $product, ?\WC_Product $parent ): ?string {
		return $this->getMetaValue( $product, '_oapfw_model_3d_link' );
	}

	protected function getPrice( \WC_Product $product, ?\WC_Product $parent ): ?string {
		$currency = get_woocommerce_currency();
		return $this->formatPrice( $product->get_regular_price(), $currency );
	}

	protected function getSalePrice( \WC_Product $product, ?\WC_Product $parent ): ?string {
		$currency = get_woocommerce_currency();
		return $this->formatPrice( $product->get_sale_price(), $currency );
	}

	protected function getSalePriceEffectiveDate( \WC_Product $product, ?\WC_Product $parent ): ?string {
		return $this->getSaleDateRange( $product );
	}

	protected function getAvailability( \WC_Product $product, ?\WC_Product $parent ): string {
		$stock_status = $product->get_stock_status();

		switch ( $stock_status ) {
			case 'instock':
				return 'in_stock';
			case 'outofstock':
				return 'out_of_stock';
			case 'onbackorder':
				return 'preorder';
			default:
				return 'out_of_stock';
		}
	}

	protected function getInventoryQuantity( \WC_Product $product, ?\WC_Product $parent ): int {
		return $product->get_stock_quantity() ?? 0;
	}

	protected function getAvailabilityDate( \WC_Product $product, ?\WC_Product $parent ): ?string {
		return $this->getMetaValue( $product, '_oapfw_availability_date' );
	}

	protected function getExpirationDate( \WC_Product $product, ?\WC_Product $parent ): ?string {
		return $this->getMetaValue( $product, '_oapfw_expiration_date' );
	}

	protected function getItemGroupId( \WC_Product $product, ?\WC_Product $parent ): ?string {
		if ( ! $parent ) {
			return null;
		}
		return (string) $parent->get_id();
	}

	protected function getItemGroupTitle( \WC_Product $product, ?\WC_Product $parent ): ?string {
		return $parent ? wp_strip_all_tags( $parent->get_name() ) : null;
	}

	protected function getColor( \WC_Product $product, ?\WC_Product $parent ): ?string {
		return $product->get_attribute( 'pa_color' ) ?: null;
	}

	protected function getSize( \WC_Product $product, ?\WC_Product $parent ): ?string {
		return $product->get_attribute( 'pa_size' ) ?: null;
	}

	protected function getSizeSystem( \WC_Product $product, ?\WC_Product $parent ): ?string {
		return $product->get_attribute( 'pa_size_system' ) ?: null;
	}

	protected function getGender( \WC_Product $product, ?\WC_Product $parent ): ?string {
		return $product->get_attribute( 'pa_gender' ) ?: null;
	}

	protected function getSellerName( \WC_Product $product, ?\WC_Product $parent ): ?string {
		$seller_name = $this->settings->get( 'seller_name' );
		return $seller_name ? (string) $seller_name : null;
	}

	protected function getSellerUrl( \WC_Product $product, ?\WC_Product $parent ): ?string {
		$seller_url = $this->settings->get( 'seller_url' );
		return $seller_url ? (string) $seller_url : null;
	}

	protected function getSellerPrivacyPolicy( \WC_Product $product, ?\WC_Product $parent ): ?string {
		$privacy_url = $this->settings->get( 'privacy_url' );
		return $privacy_url ? (string) $privacy_url : null;
	}

	protected function getSellerTos( \WC_Product $product, ?\WC_Product $parent ): ?string {
		$tos_url = $this->settings->get( 'tos_url' );
		return $tos_url ? (string) $tos_url : null;
	}

	protected function getReturnPolicy( \WC_Product $product, ?\WC_Product $parent ): ?string {
		$returns_url = $this->settings->get( 'returns_url' );
		return $returns_url ? (string) $returns_url : null;
	}

	protected function getReturnWindow( \WC_Product $product, ?\WC_Product $parent ): ?string {
		$return_window = $this->settings->get( 'return_window' );
		return $return_window ? (string) $return_window : null;
	}

	protected function getShipping( \WC_Product $product, ?\WC_Product $parent ): array {
		return $this->getShippingData();
	}

	protected function getPickupMethod( \WC_Product $product, ?\WC_Product $parent ): ?string {
		return $this->hasLocalPickup() ? 'in_store' : null;
	}

	protected function getPickupSla( \WC_Product $product, ?\WC_Product $parent ): ?string {
		if ( $this->hasLocalPickup() ) {
			$pickup_sla = $this->settings->get( 'pickup_sla' );
			return $pickup_sla ? (string) $pickup_sla : null;
		}
		return null;
	}

	protected function getWarning( \WC_Product $product, ?\WC_Product $parent ): ?string {
		return $this->getMetaValue( $product, '_oapfw_warning' );
	}

	protected function getWarningUrl( \WC_Product $product, ?\WC_Product $parent ): ?string {
		return $this->getMetaValue( $product, '_oapfw_warning_url' );
	}

	protected function getAgeRestriction( \WC_Product $product, ?\WC_Product $parent ): ?string {
		return $this->getMetaValue( $product, '_oapfw_age_restriction' );
	}

	protected function getQAndA( \WC_Product $product, ?\WC_Product $parent ): ?string {
		return $this->getMetaValue( $product, '_oapfw_q_and_a' );
	}

	// Keep existing business logic methods



	/**
	 * Get category path
	 */
	private function getCategoryPath( \WC_Product $product ): ?string {
		$terms = get_the_terms( $product->get_id(), 'product_cat' );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return null;
		}

		// Find deepest category
		$deepest_term = null;
		$max_depth    = -1;

		foreach ( $terms as $term ) {
			$depth = $this->getCategoryDepth( $term );
			if ( $depth > $max_depth ) {
				$max_depth    = $depth;
				$deepest_term = $term;
			}
		}

		if ( ! $deepest_term ) {
			return null;
		}

		return $this->buildCategoryPath( $deepest_term );
	}

	/**
	 * Get category depth
	 */
	private function getCategoryDepth( \WP_Term $term ): int {
		$depth   = 0;
		$current = $term;

		while ( $current && $current->parent ) {
			$current = get_term( $current->parent, 'product_cat' );
			if ( is_wp_error( $current ) ) {
				break;
			}
			++$depth;
		}

		return $depth;
	}

	/**
	 * Build category path string
	 */
	private function buildCategoryPath( \WP_Term $term ): string {
		$path    = array( $term->name );
		$current = $term;

		while ( $current->parent ) {
			$current = get_term( $current->parent, 'product_cat' );
			if ( is_wp_error( $current ) ) {
				break;
			}
			array_unshift( $path, $current->name );
		}

		return implode( ' > ', $path );
	}

	/**
	 * Format weight with unit
	 */
	private function formatWeight( \WC_Product $product ): ?string {
		$weight = $product->get_weight();
		if ( ! $weight ) {
			return null;
		}

		$unit = get_option( 'woocommerce_weight_unit' );
		return $weight . ' ' . $unit;
	}

	/**
	 * Format dimension with unit
	 */
	private function formatDimension( ?string $dimension ): ?string {
		if ( ! $dimension ) {
			return null;
		}

		$unit = get_option( 'woocommerce_dimension_unit' );
		return $dimension . ' ' . $unit;
	}

	/**
	 * Format all dimensions
	 */
	private function formatDimensions( \WC_Product $product ): ?string {
		$length = $product->get_length();
		$width  = $product->get_width();
		$height = $product->get_height();

		if ( ! $length || ! $width || ! $height ) {
			return null;
		}

		$unit = get_option( 'woocommerce_dimension_unit' );
		return sprintf( '%sx%sx%s %s', $length, $width, $height, $unit );
	}

	/**
	 * Get main product image
	 */
	private function getMainImage( \WC_Product $product, ?\WC_Product $parent ): string {
		$image_id = $product->get_image_id();
		if ( ! $image_id && $parent ) {
			$image_id = $parent->get_image_id();
		}

		return $image_id ? wp_get_attachment_url( $image_id ) : '';
	}

	/**
	 * Get gallery images
	 */
	private function getGalleryImages( \WC_Product $product, ?\WC_Product $parent ): array {
		$gallery_ids = $product->get_gallery_image_ids();
		if ( empty( $gallery_ids ) && $parent ) {
			$gallery_ids = $parent->get_gallery_image_ids();
		}

		return array_filter( array_map( 'wp_get_attachment_url', $gallery_ids ) );
	}

	/**
	 * Format price with currency
	 */
	private function formatPrice( ?string $price, string $currency ): ?string {
		return $price ? sprintf( '%s %s', $price, $currency ) : null;
	}

	/**
	 * Get sale date range
	 */
	private function getSaleDateRange( \WC_Product $product ): ?string {
		$sale_price = $product->get_sale_price();
		if ( ! $sale_price ) {
			return null;
		}

		$sale_from = $product->get_date_on_sale_from();
		$sale_to   = $product->get_date_on_sale_to();

		if ( $sale_from && $sale_to ) {
			return $sale_from->date_i18n( 'Y-m-d' ) . ' / ' . $sale_to->date_i18n( 'Y-m-d' );
		}

		return null;
	}




	/**
	 * Get shipping data from WooCommerce zones (cached globally to prevent repeated queries)
	 */
	private function getShippingData(): array {
		// Return cached data if available
		if ( self::$cached_shipping_data !== null ) {
			return self::$cached_shipping_data;
		}

		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			self::$cached_shipping_data = array();
			return self::$cached_shipping_data;
		}

		$shipping_data = array();
		$currency      = get_woocommerce_currency();
		$zones         = $this->getCachedShippingZones();

		foreach ( $zones as $zone ) {
			$locations = $zone['zone_locations'];

			foreach ( $zone['shipping_methods'] as $method ) {
				$method_title = $method->get_method_title();
				$price        = $this->getShippingPrice( $method );

				foreach ( $locations as $location ) {
					$shipping_string = $this->buildShippingString( $location, $method_title, $price, $currency );
					if ( $shipping_string ) {
						$shipping_data[] = $shipping_string;
					}
				}
			}
		}

		// Cache the result globally
		self::$cached_shipping_data = array_values( array_unique( $shipping_data ) );
		return self::$cached_shipping_data;
	}

	/**
	 * Get cached shipping zones (prevents repeated API calls)
	 */
	private function getCachedShippingZones(): array {
		if ( self::$cached_shipping_zones === null ) {
			self::$cached_shipping_zones = \WC_Shipping_Zones::get_zones();
		}
		return self::$cached_shipping_zones;
	}

	/**
	 * Get shipping price from method
	 */
	private function getShippingPrice( $method ): string {
		if ( $method->id === 'free_shipping' ) {
			return '0.00';
		}

		if ( isset( $method->settings['cost'] ) && is_numeric( $method->settings['cost'] ) ) {
			return $method->settings['cost'];
		}

		return '';
	}

	/**
	 * Build shipping string for location
	 */
	private function buildShippingString( $location, string $method_title, string $price, string $currency ): ?string {
		$country = '';
		$region  = '';

		switch ( $location->type ) {
			case 'country':
				$country = $location->code;
				break;
			case 'state':
				list($country, $region) = array_pad( explode( ':', $location->code ), 2, '' );
				break;
			case 'continent':
				$country = $location->code;
				break;
			default:
				return null;
		}

		$parts = array_filter( array( $country, $region, $method_title ) );

		if ( $price !== '' ) {
			$parts[] = sprintf( '%s %s', $price, $currency );
		}

		return implode(
			':',
			array_map(
				function ( $part ) {
					return trim( $part, ':' );
				},
				$parts
			)
		);
	}


	/**
	 * Check if local pickup is available (cached to prevent repeated zone queries)
	 */
	private function hasLocalPickup(): bool {
		// Return cached result if available
		if ( self::$cached_has_local_pickup !== null ) {
			return self::$cached_has_local_pickup;
		}

		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			self::$cached_has_local_pickup = false;
			return self::$cached_has_local_pickup;
		}

		$zones = $this->getCachedShippingZones();

		foreach ( $zones as $zone ) {
			foreach ( $zone['shipping_methods'] as $method ) {
				if ( $method->id === 'local_pickup' ) {
					self::$cached_has_local_pickup = true;
					return self::$cached_has_local_pickup;
				}
			}
		}

		self::$cached_has_local_pickup = false;
		return self::$cached_has_local_pickup;
	}
}
