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
	 * Get the complete feed schema definition
	 */
	public static function getSchema(): array {
		return array(
			// OpenAI flags (required)
			'enable_search'             => array(
				'required'    => true,
				'type'        => 'boolean_string',
				'default'     => 'true',
				'description' => 'Enable product in search results',
				'mapper'      => 'getEnableSearch',
			),
			'enable_checkout'           => array(
				'required'    => true,
				'type'        => 'boolean_string',
				'default'     => 'false',
				'description' => 'Enable direct checkout',
				'mapper'      => 'getEnableCheckout',
				'depends_on'  => array( 'enable_search' => 'true' ),
			),

			// Basic product data (required)
			'id'                        => array(
				'required'    => true,
				'type'        => 'string',
				'max_length'  => 100,
				'description' => 'Unique product identifier',
				'mapper'      => 'getId',
			),
			'title'                     => array(
				'required'    => true,
				'type'        => 'string',
				'max_length'  => 150,
				'description' => 'Product name',
				'mapper'      => 'getTitle',
			),
			'description'               => array(
				'required'    => true,
				'type'        => 'string',
				'max_length'  => 5000,
				'description' => 'Product description',
				'mapper'      => 'getDescription',
			),
			'link'                      => array(
				'required'    => true,
				'type'        => 'url',
				'description' => 'Product page URL',
				'mapper'      => 'getLink',
			),

			// Product identifiers
			'gtin'                      => array(
				'required'      => true,
				'type'          => 'string',
				'pattern'       => '/^\d{8,14}$/',
				'description'   => 'Global Trade Item Number (8-14 digits)',
				'mapper'        => 'getGtin',
				'error_message' => 'GTIN invalid (must be 8–14 digits only)',
			),
			'mpn'                       => array(
				'required'    => false,
				'type'        => 'string',
				'description' => 'Manufacturer Part Number',
				'mapper'      => 'getMpn',
			),

			// Item information
			'product_category'          => array(
				'required'    => false,
				'type'        => 'string',
				'description' => 'Product category path',
				'mapper'      => 'getProductCategory',
			),
			'brand'                     => array(
				'required'          => true,
				'type'              => 'string',
				'description'       => 'Product brand',
				'mapper'            => 'getBrand',
				'exempt_categories' => array( 'books', 'movies', 'music', 'media' ),
				'default'           => 'Generic',
			),
			'material'                  => array(
				'required'    => false,
				'type'        => 'string',
				'description' => 'Product material',
				'mapper'      => 'getMaterial',
			),
			'condition'                 => array(
				'required'    => false,
				'type'        => 'enum',
				'values'      => array( 'new', 'refurbished', 'used' ),
				'description' => 'Product condition',
				'mapper'      => 'getCondition',
			),
			'age_group'                 => array(
				'required'    => false,
				'type'        => 'enum',
				'values'      => array( 'newborn', 'infant', 'toddler', 'kids', 'adult' ),
				'description' => 'Target age group',
				'mapper'      => 'getAgeGroup',
			),

			// Dimensions & weight
			'weight'                    => array(
				'required'    => true,
				'type'        => 'string_with_unit',
				'description' => 'Product weight with unit',
				'mapper'      => 'getWeight',
				'default'     => '0 kg',
			),
			'length'                    => array(
				'required'    => false,
				'type'        => 'string_with_unit',
				'description' => 'Product length with unit',
				'mapper'      => 'getLength',
			),
			'width'                     => array(
				'required'    => false,
				'type'        => 'string_with_unit',
				'description' => 'Product width with unit',
				'mapper'      => 'getWidth',
			),
			'height'                    => array(
				'required'    => false,
				'type'        => 'string_with_unit',
				'description' => 'Product height with unit',
				'mapper'      => 'getHeight',
			),
			'dimensions'                => array(
				'required'    => false,
				'type'        => 'string',
				'description' => 'Combined dimensions (LxWxH unit)',
				'mapper'      => 'getDimensions',
			),

			// Media
			'image_link'                => array(
				'required'    => false,
				'type'        => 'url',
				'description' => 'Main product image URL',
				'mapper'      => 'getImageLink',
			),
			'additional_image_link'     => array(
				'required'    => false,
				'type'        => 'array',
				'description' => 'Additional product images',
				'mapper'      => 'getAdditionalImageLink',
			),
			'video_link'                => array(
				'required'    => false,
				'type'        => 'url',
				'description' => 'Product video URL',
				'mapper'      => 'getVideoLink',
			),
			'model_3d_link'             => array(
				'required'    => false,
				'type'        => 'url',
				'description' => '3D model URL',
				'mapper'      => 'getModel3dLink',
			),

			// Price & promotions
			'price'                     => array(
				'required'    => false,
				'type'        => 'price',
				'description' => 'Regular price with currency',
				'mapper'      => 'getPrice',
			),
			'sale_price'                => array(
				'required'    => false,
				'type'        => 'price',
				'description' => 'Sale price with currency',
				'mapper'      => 'getSalePrice',
				'validation'  => 'validateSalePrice',
			),
			'sale_price_effective_date' => array(
				'required'    => false,
				'type'        => 'date_range',
				'description' => 'Sale price date range (YYYY-MM-DD / YYYY-MM-DD)',
				'mapper'      => 'getSalePriceEffectiveDate',
			),

			// Availability & inventory
			'availability'              => array(
				'required'    => true,
				'type'        => 'enum',
				'values'      => array( 'in_stock', 'out_of_stock', 'preorder' ),
				'description' => 'Product availability status',
				'mapper'      => 'getAvailability',
			),
			'inventory_quantity'        => array(
				'required'    => true,
				'type'        => 'integer',
				'description' => 'Available quantity',
				'mapper'      => 'getInventoryQuantity',
				'default'     => 0,
			),
			'availability_date'         => array(
				'required_when' => array( 'availability' => 'preorder' ),
				'type'          => 'date',
				'description'   => 'When product becomes available',
				'mapper'        => 'getAvailabilityDate',
			),
			'expiration_date'           => array(
				'required'    => false,
				'type'        => 'date',
				'description' => 'Product expiration date',
				'mapper'      => 'getExpirationDate',
			),

			// Variants
			'item_group_id'             => array(
				'required'    => false,
				'type'        => 'string',
				'description' => 'Parent product ID for variations',
				'mapper'      => 'getItemGroupId',
			),
			'item_group_title'          => array(
				'required'    => false,
				'type'        => 'string',
				'description' => 'Parent product title',
				'mapper'      => 'getItemGroupTitle',
			),
			'color'                     => array(
				'required'    => false,
				'type'        => 'string',
				'description' => 'Product color',
				'mapper'      => 'getColor',
			),
			'size'                      => array(
				'required'    => false,
				'type'        => 'string',
				'description' => 'Product size',
				'mapper'      => 'getSize',
			),
			'size_system'               => array(
				'required'    => false,
				'type'        => 'string',
				'description' => 'Size system (US, EU, UK, etc.)',
				'mapper'      => 'getSizeSystem',
			),
			'gender'                    => array(
				'required'    => false,
				'type'        => 'enum',
				'values'      => array( 'male', 'female', 'unisex' ),
				'description' => 'Target gender',
				'mapper'      => 'getGender',
			),

			// Merchant info
			'seller_name'               => array(
				'required'    => false,
				'type'        => 'string',
				'description' => 'Merchant name',
				'mapper'      => 'getSellerName',
			),
			'seller_url'                => array(
				'required'    => false,
				'type'        => 'url',
				'description' => 'Merchant website',
				'mapper'      => 'getSellerUrl',
			),
			'seller_privacy_policy'     => array(
				'required'    => false,
				'type'        => 'url',
				'description' => 'Privacy policy URL',
				'mapper'      => 'getSellerPrivacyPolicy',
			),
			'seller_tos'                => array(
				'required'    => false,
				'type'        => 'url',
				'description' => 'Terms of service URL',
				'mapper'      => 'getSellerTos',
			),
			'return_policy'             => array(
				'required'    => false,
				'type'        => 'url',
				'description' => 'Return policy URL',
				'mapper'      => 'getReturnPolicy',
			),
			'return_window'             => array(
				'required'    => false,
				'type'        => 'string',
				'description' => 'Return window (e.g., "30 days")',
				'mapper'      => 'getReturnWindow',
			),

			// Shipping & fulfillment
			'shipping'                  => array(
				'required'    => false,
				'type'        => 'array',
				'description' => 'Shipping options',
				'mapper'      => 'getShipping',
			),
			'pickup_method'             => array(
				'required'    => false,
				'type'        => 'enum',
				'values'      => array( 'in_store', 'curbside' ),
				'description' => 'Pickup method',
				'mapper'      => 'getPickupMethod',
			),
			'pickup_sla'                => array(
				'required'    => false,
				'type'        => 'string',
				'description' => 'Pickup service level agreement',
				'mapper'      => 'getPickupSla',
			),

			// Additional fields
			'warning'                   => array(
				'required'    => false,
				'type'        => 'string',
				'description' => 'Product warning text',
				'mapper'      => 'getWarning',
			),
			'warning_url'               => array(
				'required'    => false,
				'type'        => 'url',
				'description' => 'Warning details URL',
				'mapper'      => 'getWarningUrl',
			),
			'age_restriction'           => array(
				'required'    => false,
				'type'        => 'integer',
				'description' => 'Minimum age requirement',
				'mapper'      => 'getAgeRestriction',
			),
			'q_and_a'                   => array(
				'required'    => false,
				'type'        => 'string',
				'description' => 'Questions and answers',
				'mapper'      => 'getQAndA',
			),
		);
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
		if ( $fieldConfig['required'] === true ) {
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
