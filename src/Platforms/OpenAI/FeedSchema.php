<?php
/**
 *  Open A I Feed Schema class.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

declare(strict_types=1);

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Platforms\OpenAI;

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
			// OpenAI flags (required).
			'enable_search'             => [
				'required'    => true,
				'type'        => 'boolean_string',
				'default'     => 'true',
				'description' => 'Enable product in search results',
			],
			'enable_checkout'           => [
				'required'    => true,
				'type'        => 'boolean_string',
				'default'     => 'false',
				'description' => 'Enable direct checkout',
				'depends_on'  => [ 'enable_search' => 'true' ],
			],

			// Basic product data (required).
			'id'                        => [
				'required'    => true,
				'type'        => 'string',
				'max_length'  => 100,
				'description' => 'Unique product identifier',
			],
			'title'                     => [
				'required'    => true,
				'type'        => 'string',
				'max_length'  => 150,
				'description' => 'Product name',
			],
			'description'               => [
				'required'    => true,
				'type'        => 'string',
				'max_length'  => 5000,
				'description' => 'Product description',
			],
			'link'                      => [
				'required'    => true,
				'type'        => 'url',
				'description' => 'Product page URL',
			],

			// Product identifiers.
			'gtin'                      => [
				'required'    => true,
				'type'        => 'string',
				'description' => 'Global Trade Item Number',
			],
			'mpn'                       => [
				'required'    => false,
				'type'        => 'string',
				'description' => 'Manufacturer Part Number',
			],

			// Item information.
			'product_category'          => [
				'required'    => false,
				'type'        => 'string',
				'description' => 'Product category path',
			],
			'brand'                     => [
				'required'          => true,
				'type'              => 'string',
				'description'       => 'Product brand',
				'exempt_categories' => [ 'books', 'movies', 'music', 'media' ],
				'default'           => 'Generic',
			],
			'material'                  => [
				'required'    => false,
				'type'        => 'string',
				'description' => 'Product material',
			],
			'condition'                 => [
				'required'    => false,
				'type'        => 'enum',
				'values'      => [ 'new', 'refurbished', 'used' ],
				'description' => 'Product condition',
			],
			'age_group'                 => [
				'required'    => false,
				'type'        => 'enum',
				'values'      => [ 'newborn', 'infant', 'toddler', 'kids', 'adult' ],
				'description' => 'Target age group',
			],

			// Dimensions & weight.
			'weight'                    => [
				'required'    => true,
				'type'        => 'string_with_unit',
				'description' => 'Product weight with unit',
				'default'     => '0 kg',
			],
			'length'                    => [
				'required'    => false,
				'type'        => 'string_with_unit',
				'description' => 'Product length with unit',
			],
			'width'                     => [
				'required'    => false,
				'type'        => 'string_with_unit',
				'description' => 'Product width with unit',
			],
			'height'                    => [
				'required'    => false,
				'type'        => 'string_with_unit',
				'description' => 'Product height with unit',
			],
			'dimensions'                => [
				'required'    => false,
				'type'        => 'string',
				'description' => 'Combined dimensions (LxWxH unit)',
			],

			// Media.
			'image_link'                => [
				'required'    => false,
				'type'        => 'url',
				'description' => 'Main product image URL',
			],
			'additional_image_link'     => [
				'required'    => false,
				'type'        => 'array',
				'description' => 'Additional product images',
			],
			'video_link'                => [
				'required'    => false,
				'type'        => 'url',
				'description' => 'Product video URL',
			],
			'model_3d_link'             => [
				'required'    => false,
				'type'        => 'url',
				'description' => '3D model URL',
			],

			// Price & promotions.
			'price'                     => [
				'required'    => false,
				'type'        => 'price',
				'description' => 'Regular price with currency',
			],
			'sale_price'                => [
				'required'    => false,
				'type'        => 'price',
				'description' => 'Sale price with currency',
				'validation'  => 'validateSalePrice',
			],
			'sale_price_effective_date' => [
				'required'    => false,
				'type'        => 'date_range',
				'description' => 'Sale price date range (YYYY-MM-DD / YYYY-MM-DD)',
			],

			// Availability & inventory.
			'availability'              => [
				'required'    => true,
				'type'        => 'enum',
				'values'      => [ 'in_stock', 'out_of_stock', 'preorder' ],
				'description' => 'Product availability status',
			],
			'inventory_quantity'        => [
				'required'    => true,
				'type'        => 'integer',
				'description' => 'Available quantity',
				'default'     => 0,
			],
			'availability_date'         => [
				'required'      => false,
				'required_when' => [ 'availability' => 'preorder' ],
				'type'          => 'date',
				'description'   => 'When product becomes available',
			],
			'expiration_date'           => [
				'required'    => false,
				'type'        => 'date',
				'description' => 'Product expiration date',
			],

			// Variants.
			'item_group_id'             => [
				'required'    => false,
				'type'        => 'string',
				'description' => 'Parent product ID for variations',
			],
			'item_group_title'          => [
				'required'    => false,
				'type'        => 'string',
				'description' => 'Parent product title',
			],
			'color'                     => [
				'required'    => false,
				'type'        => 'string',
				'description' => 'Product color',
			],
			'size'                      => [
				'required'    => false,
				'type'        => 'string',
				'description' => 'Product size',
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
				'description' => 'Target gender',
			],

			// Merchant info.
			'seller_name'               => [
				'required'    => false,
				'type'        => 'string',
				'description' => 'Merchant name',
			],
			'seller_url'                => [
				'required'    => false,
				'type'        => 'url',
				'description' => 'Merchant website',
			],
			'seller_privacy_policy'     => [
				'required'    => false,
				'type'        => 'url',
				'description' => 'Privacy policy URL',
			],
			'seller_tos'                => [
				'required'    => false,
				'type'        => 'url',
				'description' => 'Terms of service URL',
			],
			'return_policy'             => [
				'required'    => false,
				'type'        => 'url',
				'description' => 'Return policy URL',
			],
			'return_window'             => [
				'required'    => false,
				'type'        => 'string',
				'description' => 'Return window (e.g., "30 days")',
			],

			// Shipping & fulfillment.
			'shipping'                  => [
				'required'    => false,
				'type'        => 'array',
				'description' => 'Shipping options',
			],
			'pickup_method'             => [
				'required'    => false,
				'type'        => 'enum',
				'values'      => [ 'in_store', 'curbside' ],
				'description' => 'Pickup method',
			],
			'pickup_sla'                => [
				'required'    => false,
				'type'        => 'string',
				'description' => 'Pickup service level agreement',
			],

			// Additional fields.
			'warning'                   => [
				'required'    => false,
				'type'        => 'string',
				'description' => 'Product warning text',
			],
			'warning_url'               => [
				'required'    => false,
				'type'        => 'url',
				'description' => 'Warning details URL',
			],
			'age_restriction'           => [
				'required'    => false,
				'type'        => 'integer',
				'description' => 'Minimum age requirement',
			],
			'q_and_a'                   => [
				'required'    => false,
				'type'        => 'string',
				'description' => 'Questions and answers',
			],
		];

		return self::$cached_schema;
	}

	/**
	 * Get required fields only
	 */
	public static function get_required_fields(): array {
		return array_keys(
			array_filter(
				self::get_schema(),
				function ( $field ) {
					return true === $field['required'];
				}
			)
		);
	}

	/**
	 * Get conditional required fields
	 */
	public static function get_conditional_fields(): array {
		return array_filter(
			self::get_schema(),
			function ( $field ) {
				return isset( $field['required_when'] );
			}
		);
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
