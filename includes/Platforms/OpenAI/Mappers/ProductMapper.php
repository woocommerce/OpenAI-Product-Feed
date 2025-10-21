<?php
/**
 * Product Mapper class.
 *
 * @package OAPFW
 */

declare(strict_types=1);

namespace OAPFW\Platforms\OpenAI\Mappers;

use OAPFW\Core\Interfaces\ProductMapperInterface;
use OAPFW\Settings\SettingsRepository;
use OAPFW\Platforms\OpenAI\Schema\OpenAIFeedSchema;
use OAPFW\Utils\StringHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps WooCommerce products to OpenAI feed format using schema-driven approach
 *
 * Converts WooCommerce product data into OpenAI Product Feed specification format.
 * Uses a schema-driven approach to ensure all required fields are mapped correctly.
 */
final class ProductMapper implements ProductMapperInterface {

	/**
	 * Settings repository instance.
	 *
	 * @var SettingsRepository
	 */
	protected SettingsRepository $settings;

	/**
	 * OpenAI feed schema definition.
	 *
	 * @var array
	 */
	protected array $schema;

	/**
	 * Product meta cache to prevent N+1 queries.
	 *
	 * @var array
	 */
	protected array $product_meta_cache = [];

	/**
	 * Cached shipping data to prevent repeated queries.
	 *
	 * @var array|null
	 */
	private static ?array $cached_shipping_data = null;

	/**
	 * Cached shipping zones to prevent repeated API calls.
	 *
	 * @var array|null
	 */
	private static ?array $cached_shipping_zones = null;

	/**
	 * Cached local pickup availability flag.
	 *
	 * @var bool|null
	 */
	private static ?bool $cached_has_local_pickup = null;

	/**
	 * Dependency injector.
	 *
	 * @param SettingsRepository $settings Settings repository.
	 */
	public function init( SettingsRepository $settings ) {
		$this->settings = $settings;
		$this->schema   = OpenAIFeedSchema::get_schema();
	}

	/**
	 * Map WooCommerce product to feed row
	 *
	 * Main entry point for converting a WooCommerce product into OpenAI feed format.
	 *
	 * @param \WC_Product      $product Product to map.
	 * @param \WC_Product|null $parent_product  Parent product for variations.
	 * @return array Mapped product data array.
	 */
	public function map_product( \WC_Product $product, ?\WC_Product $parent_product = null ): array {
		$row = [];

		foreach ( $this->schema as $field => $config ) {
			$row[ $field ] = $this->map_field( $product, $parent_product, $field, $config );
		}

		$row = $this->validate_and_clean_row( $row );

		/**
		 * Filter mapped product data before validation.
		 *
		 * @since 1.0.0
		 * @param array            $row     Mapped product data.
		 * @param \WC_Product      $product Product object.
		 * @param \WC_Product|null $parent_product  Parent product for variations.
		 */
		return apply_filters( 'oapfw_map_product', $row, $product, $parent_product );
	}

	/**
	 * Map individual field based on configuration
	 *
	 * @param \WC_Product      $product Product object.
	 * @param \WC_Product|null $parent_product  Parent product for variations.
	 * @param string           $field   Field name to map.
	 * @param array            $config  Field configuration from schema.
	 * @return mixed Mapped field value.
	 */
	protected function map_field( \WC_Product $product, ?\WC_Product $parent_product = null, string $field, array $config ) {
		$field_mappings = $this->get_field_mappings();
		$mapper_method  = $field_mappings[ $field ] ?? null;

		if ( $mapper_method && method_exists( $this, $mapper_method ) ) {
			$value = $this->$mapper_method( $product, $parent_product );
		} else {
			$value = $this->get_meta_value( $product, "_oapfw_{$field}" );
		}

		if ( empty( $value ) && isset( $config['default'] ) ) {
			$value = $config['default'];
		}

		return $this->convert_type( $value, $config );
	}

	/**
	 * Convert value to appropriate type
	 *
	 * @param mixed $value  Value to convert.
	 * @param array $config Field configuration.
	 * @return mixed Converted value.
	 */
	protected function convert_type( $value, array $config ) {
		if ( null === $value || '' === $value ) {
			return $value;
		}

		switch ( $config['type'] ) {
			case 'boolean_string':
				return StringHelper::bool_string( $value );

			case 'integer':
				return (int) $value;

			case 'string':
				$value = (string) $value;
				if ( isset( $config['max_length'] ) ) {
					$value = StringHelper::truncate( $value, $config['max_length'] );
				}
				return $value;

			case 'array':
				return is_array( $value ) ? $value : [];

			default:
				return $value;
		}
	}

	/**
	 * Validate and clean row data using schema
	 *
	 * @param array $row Product data row.
	 * @return array Cleaned product data row.
	 */
	protected function validate_and_clean_row( array $row ): array {
		foreach ( $this->schema as $field => $config ) {
			if ( ! isset( $row[ $field ] ) ) {
				continue;
			}

			if ( isset( $config['depends_on'] ) ) {
				foreach ( $config['depends_on'] as $dep_field => $dep_value ) {
					$current_value = $row[ $dep_field ] ?? null;
					if ( $dep_value !== $current_value ) {
						if ( 'boolean_string' === $config['type'] ) {
							$row[ $field ] = 'false';
						} else {
							unset( $row[ $field ] );
						}
						break;
					}
				}
			}

			if ( isset( $config['pattern'] ) && ! empty( $row[ $field ] ) ) {
				if ( ! preg_match( $config['pattern'], (string) $row[ $field ] ) ) {
					if ( 'gtin' === $field ) {
						$row[ $field ] = 'MISSING'; // Will be caught by validator.
					}
				}
			}
		}

		return array_filter(
			$row,
			function ( $value ) {
				return null !== $value && '' !== $value;
			}
		);
	}

	/**
	 * Get meta value with fallback (with caching to prevent N+1 queries)
	 *
	 * @param \WC_Product $product Product object.
	 * @param string      $key     Meta key to retrieve.
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

	/**
	 * Get field mappings for OpenAI feed format
	 *
	 * Maps OpenAI field names to ProductMapper method names.
	 * This keeps the mapping logic separate from the schema.
	 *
	 * @return array Field name to method name mappings.
	 */
	protected function get_field_mappings(): array {
		return [
			'enable_search'             => 'get_enable_search',
			'enable_checkout'           => 'get_enable_checkout',
			'id'                        => 'get_id',
			'title'                     => 'get_title',
			'description'               => 'get_description',
			'link'                      => 'get_link',
			'gtin'                      => 'get_gtin',
			'mpn'                       => 'get_mpn',
			'product_category'          => 'get_product_category',
			'brand'                     => 'get_brand',
			'material'                  => 'get_material',
			'condition'                 => 'get_condition',
			'age_group'                 => 'get_age_group',
			'weight'                    => 'get_weight',
			'length'                    => 'get_length',
			'width'                     => 'get_width',
			'height'                    => 'get_height',
			'dimensions'                => 'get_dimensions',
			'image_link'                => 'get_image_link',
			'additional_image_link'     => 'get_additional_image_link',
			'video_link'                => 'get_video_link',
			'model_3d_link'             => 'get_model_3d_link',
			'price'                     => 'get_price',
			'sale_price'                => 'get_sale_price',
			'sale_price_effective_date' => 'get_sale_price_effective_date',
			'availability'              => 'get_availability',
			'inventory_quantity'        => 'get_inventory_quantity',
			'availability_date'         => 'get_availability_date',
			'expiration_date'           => 'get_expiration_date',
			'item_group_id'             => 'get_item_group_id',
			'item_group_title'          => 'get_item_group_title',
			'color'                     => 'get_color',
			'size'                      => 'get_size',
			'size_system'               => 'get_size_system',
			'gender'                    => 'get_gender',
			'seller_name'               => 'get_seller_name',
			'seller_url'                => 'get_seller_url',
			'seller_privacy_policy'     => 'get_seller_privacy_policy',
			'seller_tos'                => 'get_seller_tos',
			'return_policy'             => 'get_return_policy',
			'return_window'             => 'get_return_window',
			'shipping'                  => 'get_shipping',
			'pickup_method'             => 'get_pickup_method',
			'pickup_sla'                => 'get_pickup_sla',
			'warning'                   => 'get_warning',
			'warning_url'               => 'get_warning_url',
			'age_restriction'           => 'get_age_restriction',
			'q_and_a'                   => 'get_q_and_a',
		];
	}

	/**
	 * Get enable/disable setting with product override support
	 *
	 * Helper method to reduce redundancy in enable_search/enable_checkout logic.
	 *
	 * @param \WC_Product $product      Product to check.
	 * @param string      $meta_key     Meta key for disable override.
	 * @param string      $setting_key  Global setting key.
	 * @param string      $default_value      Default value if setting not found.
	 * @return string 'true' or 'false'.
	 */
	private function get_enable_with_override( \WC_Product $product, string $meta_key, string $setting_key, string $default_value ): string {
		$disable_override = $this->get_meta_value( $product, $meta_key );

		// Only disable if explicitly set to 'yes'.
		// Empty/null/no all mean "don't disable" (use global default).
		if ( 'yes' === $disable_override ) {
			return 'false';
		}
		return $this->settings->get( $setting_key, $default_value );
	}

	/**
	 * Get enable search setting
	 *
	 * @param \WC_Product      $product Product to check.
	 * @param \WC_Product|null $parent_product  Parent product for variations.
	 * @return string 'true' or 'false'.
	 */
	protected function get_enable_search( \WC_Product $product, ?\WC_Product $parent_product ): string {
		// For variations, check parent product meta; for simple products, check product meta.
		$check_product = $parent_product ? $parent_product : $product;
		return $this->get_enable_with_override( $check_product, '_oapfw_disable_search', 'enable_products_default', 'true' );
	}

	/**
	 * Get enable checkout setting
	 *
	 * @param \WC_Product      $product Product to check.
	 * @param \WC_Product|null $parent_product  Parent product for variations.
	 * @return string 'true' or 'false'.
	 */
	protected function get_enable_checkout( \WC_Product $product, ?\WC_Product $parent_product ): string {
		// For variations, check parent product meta; for simple products, check product meta.
		$check_product = $parent_product ? $parent_product : $product;
		return $this->get_enable_with_override( $check_product, '_oapfw_disable_checkout', 'enable_products_default', 'false' );
	}

	/**
	 * Get product ID
	 *
	 * @param \WC_Product $product Product object.
	 * @return string Product ID as string.
	 */
	protected function get_id( \WC_Product $product ): string {
		return (string) $product->get_id();
	}

	/**
	 * Get product title
	 *
	 * @param \WC_Product $product Product object.
	 * @return string Product title with HTML tags stripped.
	 */
	protected function get_title( \WC_Product $product ): string {
		return wp_strip_all_tags( $product->get_name() );
	}

	/**
	 * Get product description
	 *
	 * @param \WC_Product $product Product object.
	 * @return string Product description with HTML tags stripped.
	 */
	protected function get_description( \WC_Product $product ): string {
		$description = $product->get_description() ? $product->get_description() : $product->get_short_description();
		return wp_strip_all_tags( $description );
	}

	/**
	 * Get product permalink
	 *
	 * @param \WC_Product $product Product object.
	 * @return string Product permalink URL.
	 */
	protected function get_link( \WC_Product $product ): string {
		return get_permalink( $product->get_id() );
	}

	/**
	 * Get product GTIN.
	 *
	 * @param \WC_Product $product Product object.
	 * @return string|null Product GTIN or null.
	 */
	protected function get_gtin( \WC_Product $product ): ?string {
		return $this->get_meta_value( $product, '_gtin' );
	}

	/**
	 * Get product MPN.
	 *
	 * @param \WC_Product $product Product object.
	 * @return string|null Product MPN or null.
	 */
	protected function get_mpn( \WC_Product $product ): ?string {
		$mpn = $this->get_meta_value( $product, '_mpn' );
		if ( $mpn ) {
			return $mpn;
		}

		$gtin = $this->get_meta_value( $product, '_gtin' );
		if ( ! $gtin ) {
			return $this->generate_mpn( $product );
		}

		return null;
	}

	/**
	 * Generate MPN using product ID and trimmed product name
	 *
	 * @param \WC_Product $product Product object.
	 * @return string Generated MPN.
	 */
	private function generate_mpn( \WC_Product $product ): string {
		$product_id   = $product->get_id();
		$product_name = trim( wp_strip_all_tags( $product->get_name() ) );

		$hash_input = $product_id . '_' . $product_name;
		$hash       = hash( 'crc32', $hash_input );

		return 'MPN-' . str_pad( $hash, 8, '0', STR_PAD_LEFT );
	}

	/**
	 * Get product category path.
	 *
	 * @param \WC_Product $product Product object.
	 * @return string|null Product category path or null.
	 */
	protected function get_product_category( \WC_Product $product ): ?string {
		return $this->get_category_path( $product );
	}

	/**
	 * Get product brand.
	 *
	 * @param \WC_Product      $product Product object.
	 * @param \WC_Product|null $parent_product  Parent product for fallback.
	 * @return string|null Product brand or Generic.
	 */
	protected function get_brand( \WC_Product $product, ?\WC_Product $parent_product = null ): ?string {
		$brand = $product->get_attribute( 'pa_brand' );
		if ( ! $brand && $parent_product ) {
			$brand = $parent_product->get_attribute( 'pa_brand' );
		}
		if ( ! $brand ) {
			$brand = $this->get_meta_value( $product, '_brand' );
		}
		return $brand ? $brand : 'Generic';
	}

	/**
	 * Get product material.
	 *
	 * @param \WC_Product $product Product object.
	 * @return string|null Product material or null.
	 */
	protected function get_material( \WC_Product $product ): ?string {
		return $product->get_attribute( 'pa_material' ) ? $product->get_attribute( 'pa_material' ) : null;
	}

	/**
	 * Get product condition.
	 *
	 * @param \WC_Product $product Product object.
	 * @return string|null Product condition or null.
	 */
	protected function get_condition( \WC_Product $product ): ?string {
		return $this->get_meta_value( $product, '_oapfw_condition' );
	}

	/**
	 * Get product age group.
	 *
	 * @param \WC_Product $product Product object.
	 * @return string|null Product age group or null.
	 */
	protected function get_age_group( \WC_Product $product ): ?string {
		return $this->get_meta_value( $product, '_oapfw_age_group' );
	}

	/**
	 * Get product weight with unit.
	 *
	 * @param \WC_Product $product Product object.
	 * @return string|null Product weight with unit or 0 kg.
	 */
	protected function get_weight( \WC_Product $product ): ?string {
		return $this->format_weight( $product ) ? $this->format_weight( $product ) : '0 kg';
	}

	/**
	 * Get product length with unit.
	 *
	 * @param \WC_Product $product Product object.
	 * @return string|null Product length with unit or null.
	 */
	protected function get_length( \WC_Product $product ): ?string {
		return $this->format_dimension( $product->get_length() );
	}

	/**
	 * Get product width with unit.
	 *
	 * @param \WC_Product $product Product object.
	 * @return string|null Product width with unit or null.
	 */
	protected function get_width( \WC_Product $product ): ?string {
		return $this->format_dimension( $product->get_width() );
	}

	/**
	 * Get product height with unit.
	 *
	 * @param \WC_Product $product Product object.
	 * @return string|null Product height with unit or null.
	 */
	protected function get_height( \WC_Product $product ): ?string {
		return $this->format_dimension( $product->get_height() );
	}

	/**
	 * Get product dimensions.
	 *
	 * @param \WC_Product $product Product object.
	 * @return string|null Product dimensions or null.
	 */
	protected function get_dimensions( \WC_Product $product ): ?string {
		return $this->format_dimensions( $product );
	}

	/**
	 * Get product main image link.
	 *
	 * @param \WC_Product      $product Product object.
	 * @param \WC_Product|null $parent_product  Parent product for fallback.
	 * @return string Product image URL.
	 */
	protected function get_image_link( \WC_Product $product, ?\WC_Product $parent_product ): string {
		return $this->get_main_image( $product, $parent_product );
	}

	/**
	 * Get product additional image links.
	 *
	 * @param \WC_Product      $product Product object.
	 * @param \WC_Product|null $parent_product  Parent product for fallback.
	 * @return array Product gallery image URLs.
	 */
	protected function get_additional_image_link( \WC_Product $product, ?\WC_Product $parent_product ): array {
		return $this->get_gallery_images( $product, $parent_product );
	}

	/**
	 * Get product video link.
	 *
	 * @param \WC_Product $product Product object.
	 * @return string|null Product video URL or null.
	 */
	protected function get_video_link( \WC_Product $product ): ?string {
		return $this->get_meta_value( $product, '_oapfw_video_link' );
	}

	/**
	 * Get product 3D model link.
	 *
	 * @param \WC_Product $product Product object.
	 * @return string|null Product 3D model URL or null.
	 */
	protected function get_model_3d_link( \WC_Product $product ): ?string {
		return $this->get_meta_value( $product, '_oapfw_model_3d_link' );
	}

	/**
	 * Get product price with currency.
	 *
	 * @param \WC_Product $product Product object.
	 * @return string|null Product price or null.
	 */
	protected function get_price( \WC_Product $product ): ?string {
		$currency = get_woocommerce_currency();
		return $this->format_price( $product->get_regular_price(), $currency );
	}

	/**
	 * Get product sale price with currency.
	 *
	 * @param \WC_Product $product Product object.
	 * @return string|null Product sale price or null.
	 */
	protected function get_sale_price( \WC_Product $product ): ?string {
		$currency = get_woocommerce_currency();
		return $this->format_price( $product->get_sale_price(), $currency );
	}

	/**
	 * Get product sale price effective date.
	 *
	 * @param \WC_Product $product Product object.
	 * @return string|null Product sale price effective date or null.
	 */
	protected function get_sale_price_effective_date( \WC_Product $product ): ?string {
		return $this->get_sale_date_range( $product );
	}

	/**
	 * Get product availability status.
	 *
	 * @param \WC_Product $product Product object.
	 * @return string Product availability status.
	 */
	protected function get_availability( \WC_Product $product ): string {
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

	/**
	 * Get product inventory quantity.
	 *
	 * @param \WC_Product $product Product object.
	 * @return int Product inventory quantity.
	 */
	protected function get_inventory_quantity( \WC_Product $product ): int {
		return $product->get_stock_quantity() ?? 0;
	}

	/**
	 * Get product availability date.
	 *
	 * @param \WC_Product $product Product object.
	 * @return string|null Product availability date or null.
	 */
	protected function get_availability_date( \WC_Product $product ): ?string {
		return $this->get_meta_value( $product, '_oapfw_availability_date' );
	}

	/**
	 * Get product expiration date.
	 *
	 * @param \WC_Product $product Product object.
	 * @return string|null Product expiration date or null.
	 */
	protected function get_expiration_date( \WC_Product $product ): ?string {
		return $this->get_meta_value( $product, '_oapfw_expiration_date' );
	}

	/**
	 * Get product item group ID.
	 *
	 * @param \WC_Product      $product Product object.
	 * @param \WC_Product|null $parent_product  Parent product for group ID.
	 * @return string|null Parent product ID or null.
	 */
	protected function get_item_group_id( \WC_Product $product, ?\WC_Product $parent_product = null ): ?string {
		if ( ! $parent_product ) {
			return null;
		}
		return (string) $parent_product->get_id();
	}

	/**
	 * Get product item group title.
	 *
	 * @param \WC_Product      $product Product object.
	 * @param \WC_Product|null $parent_product  Parent product for title.
	 * @return string|null Parent product title or null.
	 */
	protected function get_item_group_title( \WC_Product $product, ?\WC_Product $parent_product = null ): ?string {
		return $parent_product ? wp_strip_all_tags( $parent_product->get_name() ) : null;
	}

	/**
	 * Get product color.
	 *
	 * @param \WC_Product $product Product object.
	 * @return string|null Product color or null.
	 */
	protected function get_color( \WC_Product $product ): ?string {
		return $product->get_attribute( 'pa_color' ) ? $product->get_attribute( 'pa_color' ) : null;
	}

	/**
	 * Get product size.
	 *
	 * @param \WC_Product $product Product object.
	 * @return string|null Product size or null.
	 */
	protected function get_size( \WC_Product $product ): ?string {
		return $product->get_attribute( 'pa_size' ) ? $product->get_attribute( 'pa_size' ) : null;
	}

	/**
	 * Get product size system.
	 *
	 * @param \WC_Product $product Product object.
	 * @return string|null Product size system or null.
	 */
	protected function get_size_system( \WC_Product $product ): ?string {
		return $product->get_attribute( 'pa_size_system' ) ? $product->get_attribute( 'pa_size_system' ) : null;
	}

	/**
	 * Get product gender.
	 *
	 * @param \WC_Product $product Product object.
	 * @return string|null Product gender or null.
	 */
	protected function get_gender( \WC_Product $product ): ?string {
		return $product->get_attribute( 'pa_gender' ) ? $product->get_attribute( 'pa_gender' ) : null;
	}

	/**
	 * Get seller name.
	 *
	 * @return string|null Seller name or null.
	 */
	protected function get_seller_name(): ?string {
		$seller_name = $this->settings->get( 'seller_name' );
		return $seller_name ? (string) $seller_name : null;
	}

	/**
	 * Get seller URL.
	 *
	 * @return string|null Seller URL or null.
	 */
	protected function get_seller_url(): ?string {
		$seller_url = $this->settings->get( 'seller_url' );
		return $seller_url ? (string) $seller_url : null;
	}

	/**
	 * Get seller privacy policy URL.
	 *
	 * @return string|null Privacy policy URL or null.
	 */
	protected function get_seller_privacy_policy(): ?string {
		$privacy_url = $this->settings->get( 'privacy_url' );
		return $privacy_url ? (string) $privacy_url : null;
	}

	/**
	 * Get seller terms of service URL.
	 *
	 * @return string|null Terms of service URL or null.
	 */
	protected function get_seller_tos(): ?string {
		$tos_url = $this->settings->get( 'tos_url' );
		return $tos_url ? (string) $tos_url : null;
	}

	/**
	 * Get return policy URL.
	 *
	 * @return string|null Return policy URL or null.
	 */
	protected function get_return_policy(): ?string {
		$returns_url = $this->settings->get( 'returns_url' );
		return $returns_url ? (string) $returns_url : null;
	}

	/**
	 * Get return window.
	 *
	 * @return string|null Return window or null.
	 */
	protected function get_return_window(): ?string {
		$return_window = $this->settings->get( 'return_window' );
		return $return_window ? (string) $return_window : null;
	}

	/**
	 * Get shipping data.
	 *
	 * @return array Shipping data array.
	 */
	protected function get_shipping(): array {
		return $this->get_shipping_data();
	}

	/**
	 * Get pickup method.
	 *
	 * @return string|null Pickup method or null.
	 */
	protected function get_pickup_method(): ?string {
		return $this->has_local_pickup() ? 'in_store' : null;
	}

	/**
	 * Get pickup SLA.
	 *
	 * @return string|null Pickup SLA or null.
	 */
	protected function get_pickup_sla(): ?string {
		if ( $this->has_local_pickup() ) {
			$pickup_sla = $this->settings->get( 'pickup_sla' );
			return $pickup_sla ? (string) $pickup_sla : null;
		}
		return null;
	}

	/**
	 * Get product warning.
	 *
	 * @param \WC_Product $product Product object.
	 * @return string|null Product warning or null.
	 */
	protected function get_warning( \WC_Product $product ): ?string {
		return $this->get_meta_value( $product, '_oapfw_warning' );
	}

	/**
	 * Get product warning.
	 *
	 * @param \WC_Product $product Product object.
	 * @return string|null Product warning or null.
	 */
	protected function get_warning_url( \WC_Product $product ): ?string {
		return $this->get_meta_value( $product, '_oapfw_warning_url' );
	}

	/**
	 * Get product age restriction.
	 *
	 * @param \WC_Product $product Product object.
	 * @return string|null Product age restriction or null.
	 */
	protected function get_age_restriction( \WC_Product $product ): ?string {
		return $this->get_meta_value( $product, '_oapfw_age_restriction' );
	}

	/**
	 * Get product Q and A.
	 *
	 * @param \WC_Product $product Product object.
	 * @return string|null Product Q and A or null.
	 */
	protected function get_q_and_a( \WC_Product $product ): ?string {
		return $this->get_meta_value( $product, '_oapfw_q_and_a' );
	}

	/**
	 * Get category path.
	 *
	 * @param \WC_Product $product Product object.
	 * @return string|null Category path or null.
	 */
	private function get_category_path( \WC_Product $product ): ?string {
		$terms = get_the_terms( $product->get_id(), 'product_cat' );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return null;
		}

		$deepest_term = null;
		$max_depth    = -1;

		foreach ( $terms as $term ) {
			$depth = $this->get_category_depth( $term );
			if ( $depth > $max_depth ) {
				$max_depth    = $depth;
				$deepest_term = $term;
			}
		}

		if ( ! $deepest_term ) {
			return null;
		}

		return $this->build_category_path( $deepest_term );
	}

	/**
	 * Get category depth.
	 *
	 * @param \WP_Term $term Term object.
	 * @return int Category depth.
	 */
	private function get_category_depth( \WP_Term $term ): int {
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
	 * Build category path string.
	 *
	 * @param \WP_Term $term Term object.
	 * @return string Category path string.
	 */
	private function build_category_path( \WP_Term $term ): string {
		$path    = [ $term->name ];
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
	 * Format weight with unit.
	 *
	 * @param \WC_Product $product Product object.
	 * @return string|null Formatted weight or null.
	 */
	private function format_weight( \WC_Product $product ): ?string {
		$weight = $product->get_weight();
		if ( ! $weight ) {
			return null;
		}

		$unit = get_option( 'woocommerce_weight_unit' );
		return $weight . ' ' . $unit;
	}

	/**
	 * Format dimension with unit.
	 *
	 * @param string|null $dimension Dimension value.
	 * @return string|null Formatted dimension or null.
	 */
	private function format_dimension( ?string $dimension ): ?string {
		if ( ! $dimension ) {
			return null;
		}

		$unit = get_option( 'woocommerce_dimension_unit' );
		return $dimension . ' ' . $unit;
	}

	/**
	 * Format all dimensions.
	 *
	 * @param \WC_Product $product Product object.
	 * @return string|null Formatted dimensions or null.
	 */
	private function format_dimensions( \WC_Product $product ): ?string {
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
	 * Get main product image.
	 *
	 * @param \WC_Product      $product Product object.
	 * @param \WC_Product|null $parent_product  Parent product for fallback.
	 * @return string Product image URL or empty string.
	 */
	private function get_main_image( \WC_Product $product, ?\WC_Product $parent_product ): string {
		$image_id = $product->get_image_id();
		if ( ! $image_id && $parent_product ) {
			$image_id = $parent_product->get_image_id();
		}

		return $image_id ? wp_get_attachment_url( $image_id ) : '';
	}

	/**
	 * Get gallery images.
	 *
	 * @param \WC_Product      $product Product object.
	 * @param \WC_Product|null $parent_product  Parent product for fallback.
	 * @return array Gallery image URLs.
	 */
	private function get_gallery_images( \WC_Product $product, ?\WC_Product $parent_product ): array {
		$gallery_ids = $product->get_gallery_image_ids();
		if ( empty( $gallery_ids ) && $parent_product ) {
			$gallery_ids = $parent_product->get_gallery_image_ids();
		}

		return array_filter( array_map( 'wp_get_attachment_url', $gallery_ids ) );
	}

	/**
	 * Format price with currency.
	 *
	 * @param string|null $price Price value.
	 * @param string      $currency Currency code.
	 * @return string|null Formatted price or null.
	 */
	private function format_price( ?string $price, string $currency ): ?string {
		return $price ? sprintf( '%s %s', $price, $currency ) : null;
	}

	/**
	 * Get sale date range.
	 *
	 * @param \WC_Product $product Product object.
	 * @return string|null Sale date range or null.
	 */
	private function get_sale_date_range( \WC_Product $product ): ?string {
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
	 *
	 * @return array Shipping data array.
	 */
	private function get_shipping_data(): array {
		if ( null !== self::$cached_shipping_data ) {
			return self::$cached_shipping_data;
		}

		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			self::$cached_shipping_data = [];
			return self::$cached_shipping_data;
		}

		$shipping_data = [];
		$currency      = get_woocommerce_currency();
		$zones         = $this->get_cached_shipping_zones();

		foreach ( $zones as $zone ) {
			$locations = $zone['zone_locations'];

			foreach ( $zone['shipping_methods'] as $method ) {
				$method_title = $method->get_method_title();
				$price        = $this->get_shipping_price( $method );

				foreach ( $locations as $location ) {
					$shipping_string = $this->build_shipping_string( $location, $method_title, $price, $currency );
					if ( $shipping_string ) {
						$shipping_data[] = $shipping_string;
					}
				}
			}
		}

		self::$cached_shipping_data = array_values( array_unique( $shipping_data ) );
		return self::$cached_shipping_data;
	}

	/**
	 * Get cached shipping zones (prevents repeated API calls)
	 *
	 * @return array Shipping zones.
	 */
	private function get_cached_shipping_zones(): array {
		if ( null === self::$cached_shipping_zones ) {
			self::$cached_shipping_zones = \WC_Shipping_Zones::get_zones();
		}
		return self::$cached_shipping_zones;
	}

	/**
	 * Get shipping price from method.
	 *
	 * @param mixed $method Shipping method object.
	 * @return string Shipping price.
	 */
	private function get_shipping_price( $method ): string {
		if ( 'free_shipping' === $method->id ) {
			return '0.00';
		}

		if ( isset( $method->settings['cost'] ) && is_numeric( $method->settings['cost'] ) ) {
			return $method->settings['cost'];
		}

		return '';
	}

	/**
	 * Build shipping string for location.
	 *
	 * @param mixed  $location Location object.
	 * @param string $method_title Method title.
	 * @param string $price Price value.
	 * @param string $currency Currency code.
	 * @return string|null Shipping string or null.
	 */
	private function build_shipping_string( $location, string $method_title, string $price, string $currency ): ?string {
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

		$parts = array_filter( [ $country, $region, $method_title ] );

		if ( '' !== $price ) {
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
	 *
	 * @return bool True if local pickup is available.
	 */
	private function has_local_pickup(): bool {
		if ( null !== self::$cached_has_local_pickup ) {
			return self::$cached_has_local_pickup;
		}

		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			self::$cached_has_local_pickup = false;
			return self::$cached_has_local_pickup;
		}

		$zones = $this->get_cached_shipping_zones();

		foreach ( $zones as $zone ) {
			foreach ( $zone['shipping_methods'] as $method ) {
				if ( 'local_pickup' === $method->id ) {
					self::$cached_has_local_pickup = true;
					return self::$cached_has_local_pickup;
				}
			}
		}

		self::$cached_has_local_pickup = false;
		return self::$cached_has_local_pickup;
	}
}
