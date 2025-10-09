<?php

declare(strict_types=1);

namespace OAPFW\Feed\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OpenAI Product Feed Schema Definition
 * Based on https://developers.openai.com/commerce/specs/feed
 */
class OpenAIFeedSchema {

	/**
	 * Cached schema to avoid rebuilding on every call
	 *
	 * @var array|null
	 */
	private static ?array $cached_schema = null;

	/**
	 * Get the complete feed schema definition (cached)
	 */
	public static function getSchema(): array {
		if ( self::$cached_schema !== null ) {
			return self::$cached_schema;
		}

		self::$cached_schema = array(
			// OpenAI flags (required)
			'enable_search'             => array(
				'required'    => true,
				'type'        => 'boolean_string',
				'default'     => 'true',
				'description' => 'Enable product in search results',
			),
			'enable_checkout'           => array(
				'required'    => true,
				'type'        => 'boolean_string',
				'default'     => 'false',
				'description' => 'Enable direct checkout',
				'depends_on'  => array( 'enable_search' => 'true' ),
			),

			// Basic product data (required)
			'id'                        => array(
				'required'    => true,
				'type'        => 'string',
				'max_length'  => 100,
				'description' => 'Unique product identifier',
			),
			'title'                     => array(
				'required'    => true,
				'type'        => 'string',
				'max_length'  => 150,
				'description' => 'Product name',
			),
			'description'               => array(
				'required'    => true,
				'type'        => 'string',
				'max_length'  => 5000,
				'description' => 'Product description',
			),
			'link'                      => array(
				'required'    => true,
				'type'        => 'url',
				'description' => 'Product page URL',
			),

			// Product identifiers
			'gtin'                      => array(
				'required'      => true,
				'type'          => 'string',
				'description'   => 'Global Trade Item Number',
			),
			'mpn'                       => array(
				'required'    => false,
				'type'        => 'string',
				'description' => 'Manufacturer Part Number',
			),

			// Item information
			'product_category'          => array(
				'required'    => false,
				'type'        => 'string',
				'description' => 'Product category path',
			),
			'brand'                     => array(
				'required'          => true,
				'type'              => 'string',
				'description'       => 'Product brand',
				'exempt_categories' => array( 'books', 'movies', 'music', 'media' ),
				'default'           => 'Generic',
			),
			'material'                  => array(
				'required'    => false,
				'type'        => 'string',
				'description' => 'Product material',
			),
			'condition'                 => array(
				'required'    => false,
				'type'        => 'enum',
				'values'      => array( 'new', 'refurbished', 'used' ),
				'description' => 'Product condition',
			),
			'age_group'                 => array(
				'required'    => false,
				'type'        => 'enum',
				'values'      => array( 'newborn', 'infant', 'toddler', 'kids', 'adult' ),
				'description' => 'Target age group',
			),

			// Dimensions & weight
			'weight'                    => array(
				'required'    => true,
				'type'        => 'string_with_unit',
				'description' => 'Product weight with unit',
				'default'     => '0 kg',
			),
			'length'                    => array(
				'required'    => false,
				'type'        => 'string_with_unit',
				'description' => 'Product length with unit',
			),
			'width'                     => array(
				'required'    => false,
				'type'        => 'string_with_unit',
				'description' => 'Product width with unit',
			),
			'height'                    => array(
				'required'    => false,
				'type'        => 'string_with_unit',
				'description' => 'Product height with unit',
			),
			'dimensions'                => array(
				'required'    => false,
				'type'        => 'string',
				'description' => 'Combined dimensions (LxWxH unit)',
			),

			// Media
			'image_link'                => array(
				'required'    => false,
				'type'        => 'url',
				'description' => 'Main product image URL',
			),
			'additional_image_link'     => array(
				'required'    => false,
				'type'        => 'array',
				'description' => 'Additional product images',
			),
			'video_link'                => array(
				'required'    => false,
				'type'        => 'url',
				'description' => 'Product video URL',
			),
			'model_3d_link'             => array(
				'required'    => false,
				'type'        => 'url',
				'description' => '3D model URL',
			),

			// Price & promotions
			'price'                     => array(
				'required'    => false,
				'type'        => 'price',
				'description' => 'Regular price with currency',
			),
			'sale_price'                => array(
				'required'    => false,
				'type'        => 'price',
				'description' => 'Sale price with currency',
				'validation'  => 'validateSalePrice',
			),
			'sale_price_effective_date' => array(
				'required'    => false,
				'type'        => 'date_range',
				'description' => 'Sale price date range (YYYY-MM-DD / YYYY-MM-DD)',
			),

			// Availability & inventory
			'availability'              => array(
				'required'    => true,
				'type'        => 'enum',
				'values'      => array( 'in_stock', 'out_of_stock', 'preorder' ),
				'description' => 'Product availability status',
			),
			'inventory_quantity'        => array(
				'required'    => true,
				'type'        => 'integer',
				'description' => 'Available quantity',
				'default'     => 0,
			),
			'availability_date'         => array(
				'required'      => false,
				'required_when' => array( 'availability' => 'preorder' ),
				'type'          => 'date',
				'description'   => 'When product becomes available',
			),
			'expiration_date'           => array(
				'required'    => false,
				'type'        => 'date',
				'description' => 'Product expiration date',
			),

			// Variants
			'item_group_id'             => array(
				'required'    => false,
				'type'        => 'string',
				'description' => 'Parent product ID for variations',
			),
			'item_group_title'          => array(
				'required'    => false,
				'type'        => 'string',
				'description' => 'Parent product title',
			),
			'color'                     => array(
				'required'    => false,
				'type'        => 'string',
				'description' => 'Product color',
			),
			'size'                      => array(
				'required'    => false,
				'type'        => 'string',
				'description' => 'Product size',
			),
			'size_system'               => array(
				'required'    => false,
				'type'        => 'string',
				'description' => 'Size system (US, EU, UK, etc.)',
			),
			'gender'                    => array(
				'required'    => false,
				'type'        => 'enum',
				'values'      => array( 'male', 'female', 'unisex' ),
				'description' => 'Target gender',
			),

			// Merchant info
			'seller_name'               => array(
				'required'    => false,
				'type'        => 'string',
				'description' => 'Merchant name',
			),
			'seller_url'                => array(
				'required'    => false,
				'type'        => 'url',
				'description' => 'Merchant website',
			),
			'seller_privacy_policy'     => array(
				'required'    => false,
				'type'        => 'url',
				'description' => 'Privacy policy URL',
			),
			'seller_tos'                => array(
				'required'    => false,
				'type'        => 'url',
				'description' => 'Terms of service URL',
			),
			'return_policy'             => array(
				'required'    => false,
				'type'        => 'url',
				'description' => 'Return policy URL',
			),
			'return_window'             => array(
				'required'    => false,
				'type'        => 'string',
				'description' => 'Return window (e.g., "30 days")',
			),

			// Shipping & fulfillment
			'shipping'                  => array(
				'required'    => false,
				'type'        => 'array',
				'description' => 'Shipping options',
			),
			'pickup_method'             => array(
				'required'    => false,
				'type'        => 'enum',
				'values'      => array( 'in_store', 'curbside' ),
				'description' => 'Pickup method',
			),
			'pickup_sla'                => array(
				'required'    => false,
				'type'        => 'string',
				'description' => 'Pickup service level agreement',
			),

			// Additional fields
			'warning'                   => array(
				'required'    => false,
				'type'        => 'string',
				'description' => 'Product warning text',
			),
			'warning_url'               => array(
				'required'    => false,
				'type'        => 'url',
				'description' => 'Warning details URL',
			),
			'age_restriction'           => array(
				'required'    => false,
				'type'        => 'integer',
				'description' => 'Minimum age requirement',
			),
			'q_and_a'                   => array(
				'required'    => false,
				'type'        => 'string',
				'description' => 'Questions and answers',
			),
		);

		return self::$cached_schema;
	}

	/**
	 * Get required fields only
	 */
	public static function getRequiredFields(): array {
		return array_keys(
			array_filter(
				self::getSchema(),
				function ( $field ) {
					return $field['required'] === true;
				}
			)
		);
	}

	/**
	 * Get conditional required fields
	 */
	public static function getConditionalFields(): array {
		return array_filter(
			self::getSchema(),
			function ( $field ) {
				return isset( $field['required_when'] );
			}
		);
	}

	/**
	 * Get field configuration
	 */
	public static function getField( string $field ): ?array {
		$schema = self::getSchema();
		return $schema[ $field ] ?? null;
	}

	/**
	 * Check if field is required
	 */
	public static function isFieldRequired( string $field, array $data = array() ): bool {
		$fieldConfig = self::getField( $field );
		if ( ! $fieldConfig ) {
			return false;
		}

		// Direct requirement
		if ( isset( $fieldConfig['required'] ) && $fieldConfig['required'] === true ) {
			return true;
		}

		// Conditional requirement
		if ( isset( $fieldConfig['required_when'] ) ) {
			foreach ( $fieldConfig['required_when'] as $dependField => $dependValue ) {
				if ( ( $data[ $dependField ] ?? null ) === $dependValue ) {
					return true;
				}
			}
		}

		return false;
	}
}
