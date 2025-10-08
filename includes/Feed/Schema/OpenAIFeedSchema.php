<?php
namespace OAPFW\Feed\Schema;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * OpenAI Product Feed Schema Definition
 * Based on https://developers.openai.com/commerce/specs/feed
 */
class OpenAIFeedSchema
{
    /**
     * Get the complete feed schema definition
     */
    public static function getSchema(): array
    {
        return [
            // OpenAI flags (required)
            'enable_search' => [
                'required' => true,
                'type' => 'boolean_string',
                'default' => 'true',
                'description' => 'Enable product in search results',
                'mapper' => 'getEnableSearch'
            ],
            'enable_checkout' => [
                'required' => true,
                'type' => 'boolean_string',
                'default' => 'false',
                'description' => 'Enable direct checkout',
                'mapper' => 'getEnableCheckout',
                'depends_on' => ['enable_search' => 'true']
            ],

            // Basic product data (required)
            'id' => [
                'required' => true,
                'type' => 'string',
                'max_length' => 100,
                'description' => 'Unique product identifier',
                'mapper' => 'getId'
            ],
            'title' => [
                'required' => true,
                'type' => 'string',
                'max_length' => 150,
                'description' => 'Product name',
                'mapper' => 'getTitle'
            ],
            'description' => [
                'required' => true,
                'type' => 'string',
                'max_length' => 5000,
                'description' => 'Product description',
                'mapper' => 'getDescription'
            ],
            'link' => [
                'required' => true,
                'type' => 'url',
                'description' => 'Product page URL',
                'mapper' => 'getLink'
            ],

            // Product identifiers
            'gtin' => [
                'required' => true,
                'type' => 'string',
                'pattern' => '/^\d{8,14}$/',
                'description' => 'Global Trade Item Number (8-14 digits)',
                'mapper' => 'getGtin',
                'error_message' => 'GTIN invalid (must be 8–14 digits only)'
            ],
            'mpn' => [
                'required' => false,
                'type' => 'string',
                'description' => 'Manufacturer Part Number',
                'mapper' => 'getMpn'
            ],

            // Item information
            'product_category' => [
                'required' => false,
                'type' => 'string',
                'description' => 'Product category path',
                'mapper' => 'getProductCategory'
            ],
            'brand' => [
                'required' => true,
                'type' => 'string',
                'description' => 'Product brand',
                'mapper' => 'getBrand',
                'exempt_categories' => ['books', 'movies', 'music', 'media'],
                'default' => 'Generic'
            ],
            'material' => [
                'required' => false,
                'type' => 'string',
                'description' => 'Product material',
                'mapper' => 'getMaterial'
            ],
            'condition' => [
                'required' => false,
                'type' => 'enum',
                'values' => ['new', 'refurbished', 'used'],
                'description' => 'Product condition',
                'mapper' => 'getCondition'
            ],
            'age_group' => [
                'required' => false,
                'type' => 'enum',
                'values' => ['newborn', 'infant', 'toddler', 'kids', 'adult'],
                'description' => 'Target age group',
                'mapper' => 'getAgeGroup'
            ],

            // Dimensions & weight
            'weight' => [
                'required' => true,
                'type' => 'string_with_unit',
                'description' => 'Product weight with unit',
                'mapper' => 'getWeight',
                'default' => '0 kg'
            ],
            'length' => [
                'required' => false,
                'type' => 'string_with_unit',
                'description' => 'Product length with unit',
                'mapper' => 'getLength'
            ],
            'width' => [
                'required' => false,
                'type' => 'string_with_unit',
                'description' => 'Product width with unit',
                'mapper' => 'getWidth'
            ],
            'height' => [
                'required' => false,
                'type' => 'string_with_unit',
                'description' => 'Product height with unit',
                'mapper' => 'getHeight'
            ],
            'dimensions' => [
                'required' => false,
                'type' => 'string',
                'description' => 'Combined dimensions (LxWxH unit)',
                'mapper' => 'getDimensions'
            ],

            // Media
            'image_link' => [
                'required' => false,
                'type' => 'url',
                'description' => 'Main product image URL',
                'mapper' => 'getImageLink'
            ],
            'additional_image_link' => [
                'required' => false,
                'type' => 'array',
                'description' => 'Additional product images',
                'mapper' => 'getAdditionalImageLink'
            ],
            'video_link' => [
                'required' => false,
                'type' => 'url',
                'description' => 'Product video URL',
                'mapper' => 'getVideoLink'
            ],
            'model_3d_link' => [
                'required' => false,
                'type' => 'url',
                'description' => '3D model URL',
                'mapper' => 'getModel3dLink'
            ],

            // Price & promotions
            'price' => [
                'required' => false,
                'type' => 'price',
                'description' => 'Regular price with currency',
                'mapper' => 'getPrice'
            ],
            'sale_price' => [
                'required' => false,
                'type' => 'price',
                'description' => 'Sale price with currency',
                'mapper' => 'getSalePrice',
                'validation' => 'validateSalePrice'
            ],
            'sale_price_effective_date' => [
                'required' => false,
                'type' => 'date_range',
                'description' => 'Sale price date range (YYYY-MM-DD / YYYY-MM-DD)',
                'mapper' => 'getSalePriceEffectiveDate'
            ],

            // Availability & inventory
            'availability' => [
                'required' => true,
                'type' => 'enum',
                'values' => ['in_stock', 'out_of_stock', 'preorder'],
                'description' => 'Product availability status',
                'mapper' => 'getAvailability'
            ],
            'inventory_quantity' => [
                'required' => true,
                'type' => 'integer',
                'description' => 'Available quantity',
                'mapper' => 'getInventoryQuantity',
                'default' => 0
            ],
            'availability_date' => [
                'required_when' => ['availability' => 'preorder'],
                'type' => 'date',
                'description' => 'When product becomes available',
                'mapper' => 'getAvailabilityDate'
            ],
            'expiration_date' => [
                'required' => false,
                'type' => 'date',
                'description' => 'Product expiration date',
                'mapper' => 'getExpirationDate'
            ],

            // Variants
            'item_group_id' => [
                'required' => false,
                'type' => 'string',
                'description' => 'Parent product ID for variations',
                'mapper' => 'getItemGroupId'
            ],
            'item_group_title' => [
                'required' => false,
                'type' => 'string',
                'description' => 'Parent product title',
                'mapper' => 'getItemGroupTitle'
            ],
            'color' => [
                'required' => false,
                'type' => 'string',
                'description' => 'Product color',
                'mapper' => 'getColor'
            ],
            'size' => [
                'required' => false,
                'type' => 'string',
                'description' => 'Product size',
                'mapper' => 'getSize'
            ],
            'size_system' => [
                'required' => false,
                'type' => 'string',
                'description' => 'Size system (US, EU, UK, etc.)',
                'mapper' => 'getSizeSystem'
            ],
            'gender' => [
                'required' => false,
                'type' => 'enum',
                'values' => ['male', 'female', 'unisex'],
                'description' => 'Target gender',
                'mapper' => 'getGender'
            ],

            // Merchant info
            'seller_name' => [
                'required' => false,
                'type' => 'string',
                'description' => 'Merchant name',
                'mapper' => 'getSellerName'
            ],
            'seller_url' => [
                'required' => false,
                'type' => 'url',
                'description' => 'Merchant website',
                'mapper' => 'getSellerUrl'
            ],
            'seller_privacy_policy' => [
                'required' => false,
                'type' => 'url',
                'description' => 'Privacy policy URL',
                'mapper' => 'getSellerPrivacyPolicy'
            ],
            'seller_tos' => [
                'required' => false,
                'type' => 'url',
                'description' => 'Terms of service URL',
                'mapper' => 'getSellerTos'
            ],
            'return_policy' => [
                'required' => false,
                'type' => 'url',
                'description' => 'Return policy URL',
                'mapper' => 'getReturnPolicy'
            ],
            'return_window' => [
                'required' => false,
                'type' => 'string',
                'description' => 'Return window (e.g., "30 days")',
                'mapper' => 'getReturnWindow'
            ],

            // Shipping & fulfillment
            'shipping' => [
                'required' => false,
                'type' => 'array',
                'description' => 'Shipping options',
                'mapper' => 'getShipping'
            ],
            'pickup_method' => [
                'required' => false,
                'type' => 'enum',
                'values' => ['in_store', 'curbside'],
                'description' => 'Pickup method',
                'mapper' => 'getPickupMethod'
            ],
            'pickup_sla' => [
                'required' => false,
                'type' => 'string',
                'description' => 'Pickup service level agreement',
                'mapper' => 'getPickupSla'
            ],

            // Additional fields
            'warning' => [
                'required' => false,
                'type' => 'string',
                'description' => 'Product warning text',
                'mapper' => 'getWarning'
            ],
            'warning_url' => [
                'required' => false,
                'type' => 'url',
                'description' => 'Warning details URL',
                'mapper' => 'getWarningUrl'
            ],
            'age_restriction' => [
                'required' => false,
                'type' => 'integer',
                'description' => 'Minimum age requirement',
                'mapper' => 'getAgeRestriction'
            ],
            'q_and_a' => [
                'required' => false,
                'type' => 'string',
                'description' => 'Questions and answers',
                'mapper' => 'getQAndA'
            ]
        ];
    }

    /**
     * Get required fields only
     */
    public static function getRequiredFields(): array
    {
        return array_keys(array_filter(self::getSchema(), function($field) {
            return $field['required'] === true;
        }));
    }

    /**
     * Get conditional required fields
     */
    public static function getConditionalFields(): array
    {
        return array_filter(self::getSchema(), function($field) {
            return isset($field['required_when']);
        });
    }

    /**
     * Get field configuration
     */
    public static function getField(string $field): ?array
    {
        $schema = self::getSchema();
        return $schema[$field] ?? null;
    }

    /**
     * Check if field is required
     */
    public static function isFieldRequired(string $field, array $data = []): bool
    {
        $fieldConfig = self::getField($field);
        if (!$fieldConfig) {
            return false;
        }

        // Direct requirement
        if ($fieldConfig['required'] === true) {
            return true;
        }

        // Conditional requirement
        if (isset($fieldConfig['required_when'])) {
            foreach ($fieldConfig['required_when'] as $dependField => $dependValue) {
                if (($data[$dependField] ?? null) === $dependValue) {
                    return true;
                }
            }
        }

        return false;
    }
}