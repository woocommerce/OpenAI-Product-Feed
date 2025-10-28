<?php
/**
 *  Open A I Feed Schema class.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

declare(strict_types=1);

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Integrations\OpenAi;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OpenAI Product Feed Schema Definition
 * Based on https://developers.openai.com/commerce/specs/feed
 */
class FeedSchema {

	/**
	 * Cached schema to avoid rebuilding on every call
	 *
	 * @var array|null
	 */
	private static ?array $cached_schema = null;

	/**
	 * Get the complete feed schema definition (cached)
	 */
	public static function get_schema(): array {
		if ( null !== self::$cached_schema ) {
			return self::$cached_schema;
		}

		self::$cached_schema = [
			// OpenAI Flags.
			'enable_search'             => [
				'required'    => true,
				'type'        => 'boolean_string',
				'default'     => 'true',
				'description' => 'Controls whether the product can be surfaced in ChatGPT search results',
			],
			'enable_checkout'           => [
				'required'    => true,
				'type'        => 'boolean_string',
				'default'     => 'false',
				'description' => 'Allows direct purchase inside ChatGPT',
				'depends_on'  => [ 'enable_search' => 'true' ],
			],

			// Basic Product Data.
			'id'                        => [
				'required'    => true,
				'type'        => 'string',
				'max_length'  => 100,
				'description' => 'Merchant product ID (unique)',
			],
			'gtin'                      => [
				'required'    => false,
				'type'        => 'string',
				'description' => 'Universal product identifier (GTIN, UPC, ISBN)',
			],
			'mpn'                       => [
				'required'      => false,
				'required_when' => [ 'gtin' => '' ],
				'type'          => 'string',
				'max_length'    => 70,
				'description'   => 'Manufacturer part number',
			],
			'title'                     => [
				'required'    => true,
				'type'        => 'string',
				'max_length'  => 150,
				'description' => 'Product title',
			],
			'description'               => [
				'required'    => true,
				'type'        => 'string',
				'max_length'  => 5000,
				'description' => 'Full product description',
			],
			'link'                      => [
				'required'    => true,
				'type'        => 'url',
				'description' => 'Product detail page URL',
			],

			// Item Information.
			'condition'                 => [
				'required'    => false,
				'type'        => 'enum',
				'values'      => [ 'new', 'refurbished', 'used' ],
				'description' => 'Condition of product',
			],
			'product_category'          => [
				'required'    => true,
				'type'        => 'string',
				'description' => 'Category path',
			],
			'brand'                     => [
				'required'          => true,
				'type'              => 'string',
				'max_length'        => 70,
				'description'       => 'Product brand',
				'exempt_categories' => [ 'books', 'movies', 'music' ],
				'default'           => 'Generic',
			],
			'material'                  => [
				'required'    => true,
				'type'        => 'string',
				'max_length'  => 100,
				'description' => 'Primary material(s)',
				'default'     => 'Generic',
			],
			'dimensions'                => [
				'required'    => false,
				'type'        => 'string',
				'description' => 'Overall dimensions (LxWxH unit)',
			],
			'length'                    => [
				'required'    => false,
				'type'        => 'string_with_unit',
				'description' => 'Individual dimension',
			],
			'width'                     => [
				'required'    => false,
				'type'        => 'string_with_unit',
				'description' => 'Individual dimension',
			],
			'height'                    => [
				'required'    => false,
				'type'        => 'string_with_unit',
				'description' => 'Individual dimension',
			],
			'weight'                    => [
				'required'    => true,
				'type'        => 'string_with_unit',
				'description' => 'Product weight',
			],
			'age_group'                 => [
				'required'    => false,
				'type'        => 'enum',
				'values'      => [ 'newborn', 'infant', 'toddler', 'kids', 'adult' ],
				'description' => 'Target demographic',
			],

			// Media.
			'image_link'                => [
				'required'    => true,
				'type'        => 'url',
				'description' => 'Main product image URL',
			],
			'additional_image_link'     => [
				'required'    => false,
				'type'        => 'array',
				'description' => 'Extra images',
			],
			'video_link'                => [
				'required'    => false,
				'type'        => 'url',
				'description' => 'Product video',
			],
			'model_3d_link'             => [
				'required'    => false,
				'type'        => 'url',
				'description' => '3D model',
			],

			// Price & Promotions.
			'price'                     => [
				'required'    => true,
				'type'        => 'price',
				'description' => 'Regular price',
			],
			'applicable_taxes_fees'     => [
				'required'    => false,
				'type'        => 'price',
				'description' => 'Additional taxes/fees',
			],
			'sale_price'                => [
				'required'    => false,
				'type'        => 'price',
				'description' => 'Discounted price',
				'validation'  => 'validateSalePrice',
			],
			'sale_price_effective_date' => [
				'required'      => false,
				'required_when' => [ 'sale_price' => 'present' ],
				'type'          => 'date_range',
				'description'   => 'Sale window (YYYY-MM-DD / YYYY-MM-DD)',
			],
			'unit_pricing_measure'      => [
				'required'    => false,
				'type'        => 'string_with_unit',
				'description' => 'Unit price measure',
			],
			'base_measure'              => [
				'required'    => false,
				'type'        => 'string_with_unit',
				'description' => 'Base measure',
			],
			'pricing_trend'             => [
				'required'    => false,
				'type'        => 'string',
				'max_length'  => 80,
				'description' => 'Lowest price in N months',
			],

			// Availability & Inventory.
			'availability'              => [
				'required'    => true,
				'type'        => 'enum',
				'values'      => [ 'in_stock', 'out_of_stock', 'preorder' ],
				'description' => 'Product availability',
			],
			'availability_date'         => [
				'required'      => false,
				'required_when' => [ 'availability' => 'preorder' ],
				'type'          => 'date',
				'description'   => 'Availability date if preorder',
			],
			'inventory_quantity'        => [
				'required'    => true,
				'type'        => 'integer',
				'description' => 'Stock count',
			],
			'expiration_date'           => [
				'required'    => false,
				'type'        => 'date',
				'description' => 'Remove product after date',
			],
			'pickup_method'             => [
				'required'    => false,
				'type'        => 'enum',
				'values'      => [ 'in_store', 'reserve', 'not_supported' ],
				'description' => 'Pickup options',
			],
			'pickup_sla'                => [
				'required'    => false,
				'type'        => 'string',
				'description' => 'Pickup SLA',
			],

			// Variants.
			'item_group_id'             => [
				'required'    => false,
				'type'        => 'string',
				'max_length'  => 70,
				'description' => 'Variant group ID',
			],
			'item_group_title'          => [
				'required'    => false,
				'type'        => 'string',
				'max_length'  => 150,
				'description' => 'Group product title',
			],
			'color'                     => [
				'required'    => false,
				'type'        => 'string',
				'max_length'  => 40,
				'description' => 'Variant color',
			],
			'size'                      => [
				'required'    => false,
				'type'        => 'string',
				'max_length'  => 20,
				'description' => 'Variant size',
			],
			'size_system'               => [
				'required'    => false,
				'type'        => 'string',
				'description' => 'Size system (US, EU, UK, etc.)',
			],
			'gender'                    => [
				'required'    => false,
				'type'        => 'enum',
				'values'      => [ 'male', 'female', 'unisex' ],
				'description' => 'Gender target',
			],
			'offer_id'                  => [
				'required'    => false,
				'type'        => 'string',
				'description' => 'Offer ID (SKU+seller+price)',
			],
			// @TODO: Implement custom attributes for these custom_* fields.
			'custom_variant1_category'  => [
				'required'    => false,
				'type'        => 'string',
				'description' => 'Custom variant dimension 1',
			],
			'custom_variant1_option'    => [
				'required'    => false,
				'type'        => 'string',
				'description' => 'Custom variant 1 option',
			],
			'custom_variant2_category'  => [
				'required'    => false,
				'type'        => 'string',
				'description' => 'Custom variant dimension 2',
			],
			'custom_variant2_option'    => [
				'required'    => false,
				'type'        => 'string',
				'description' => 'Custom variant 2 option',
			],
			'custom_variant3_category'  => [
				'required'    => false,
				'type'        => 'string',
				'description' => 'Custom variant dimension 3',
			],
			'custom_variant3_option'    => [
				'required'    => false,
				'type'        => 'string',
				'description' => 'Custom variant 3 option',
			],

			// Fulfillment.
			'shipping'                  => [
				'required'    => false,
				'type'        => 'string',
				'description' => 'Shipping method/cost/region (Multiple entries allowed; use colon separators)',
			],
			'delivery_estimate'         => [
				'required'    => false,
				'type'        => 'date',
				'description' => 'Estimated arrival date',
			],

			// Merchant Info.
			'seller_name'               => [
				'required'    => true,
				'type'        => 'string',
				'max_length'  => 70,
				'description' => 'Seller name',
			],
			'seller_url'                => [
				'required'    => true,
				'type'        => 'url',
				'description' => 'Seller page',
			],
			'seller_privacy_policy'     => [
				'required'      => false,
				'required_when' => [ 'enable_checkout' => 'true' ],
				'type'          => 'url',
				'description'   => 'Seller-specific policies',
			],
			'seller_tos'                => [
				'required'      => false,
				'required_when' => [ 'enable_checkout' => 'true' ],
				'type'          => 'url',
				'description'   => 'Seller-specific terms of service',
			],

			// Returns.
			'return_policy'             => [
				'required'    => true,
				'type'        => 'url',
				'description' => 'Return policy URL',
			],
			'return_window'             => [
				'required'    => true,
				'type'        => 'integer',
				'description' => 'Days allowed for return',
			],

			// Performance Signals.
			'popularity_score'          => [
				'required'    => false,
				'type'        => 'number',
				'description' => 'Popularity indicator',
			],
			'return_rate'               => [
				'required'    => false,
				'type'        => 'number',
				'description' => 'Return rate',
			],

			// Compliance.
			'warning'                   => [
				'required'    => false,
				'type'        => 'string',
				'description' => 'Product disclaimers',
			],
			'warning_url'               => [
				'required'    => false,
				'type'        => 'url',
				'description' => 'Warning details URL',
			],
			'age_restriction'           => [
				'required'    => false,
				'type'        => 'integer',
				'description' => 'Minimum purchase age',
			],

			// Reviews and Q&A.
			'product_review_count'      => [
				'required'    => false,
				'type'        => 'integer',
				'description' => 'Number of product reviews',
			],
			'product_review_rating'     => [
				'required'    => false,
				'type'        => 'number',
				'description' => 'Average review score',
			],
			'store_review_count'        => [
				'required'    => false,
				'type'        => 'integer',
				'description' => 'Number of brand/store reviews',
			],
			'store_review_rating'       => [
				'required'    => false,
				'type'        => 'number',
				'description' => 'Average store rating',
			],
			'q_and_a'                   => [
				'required'    => false,
				'type'        => 'string',
				'description' => 'FAQ content',
			],
			'raw_review_data'           => [
				'required'    => false,
				'type'        => 'string',
				'description' => 'Raw review payload',
			],

			// Related Products.
			'related_product_id'        => [
				'required'    => false,
				'type'        => 'string',
				'description' => 'Associated product IDs',
			],
			'relationship_type'         => [
				'required'    => false,
				'type'        => 'enum',
				'values'      => [ 'part_of_set', 'required_part', 'often_bought_with', 'substitute', 'different_brand', 'accessory' ],
				'description' => 'Relationship type',
			],

			// Geo Tagging.
			'geo_price'                 => [
				'required'    => false,
				'type'        => 'price',
				'description' => 'Price by region',
			],
			'geo_availability'          => [
				'required'    => false,
				'type'        => 'string',
				'description' => 'Availability per region',
			],

		];

		return self::$cached_schema;
	}

	/**
	 * Get field configuration
	 *
	 * @param string $field Field name.
	 * @return array|null Field configuration or null if not found.
	 */
	public static function get_field( string $field ): ?array {
		$schema = self::get_schema();
		return $schema[ $field ] ?? null;
	}

	/**
	 * Check if field is required
	 *
	 * @param string $field Field name to check.
	 * @param array  $data  Product data for conditional checks.
	 * @return bool True if field is required, false otherwise.
	 */
	public static function is_field_required( string $field, array $data = [] ): bool {
		$field_config = self::get_field( $field );
		if ( ! $field_config ) {
			return false;
		}

		if ( isset( $field_config['required'] ) && true === $field_config['required'] ) {
			return true;
		}

		if ( isset( $field_config['required_when'] ) ) {
			foreach ( $field_config['required_when'] as $depend_field => $depend_value ) {
				$current_value = $data[ $depend_field ] ?? null;
				if ( $depend_value === $current_value ) {
					return true;
				}
			}
		}

		return false;
	}
}
