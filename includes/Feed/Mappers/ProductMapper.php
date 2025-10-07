<?php
namespace OAPFW\Feed\Mappers;

use OAPFW\Core\ProductMapperInterface;
use OAPFW\Core\SettingsRepositoryInterface;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Maps WooCommerce products to OpenAI feed format
 */
class ProductMapper implements ProductMapperInterface
{
    private SettingsRepositoryInterface $settings;

    public function __construct(SettingsRepositoryInterface $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Map WooCommerce product to feed row
     */
    public function mapProduct(\WC_Product $product, ?\WC_Product $parent = null): array
    {
        $currency = get_woocommerce_currency();
        $sku = $product->get_sku() ?: 'wc-' . $product->get_id();
        
        $row = [
            // OpenAI flags
            'enable_search'   => $this->settings->get('enable_search_default', 'true'),
            'enable_checkout' => $this->settings->get('enable_checkout_default', 'false'),

            // Basic Product Data
            'id'          => $sku,
            'gtin'        => $this->getMetaValue($product, '_gtin'),
            'mpn'         => $this->getMetaValue($product, '_mpn'),
            'title'       => $this->truncateText(wp_strip_all_tags($product->get_name()), 150),
            'description' => $this->getDescription($product),
            'link'        => get_permalink($product->get_id()),

            // Item Information
            'product_category' => $this->getCategoryPath($product),
            'brand'            => $this->getBrand($product, $parent) ?: 'Generic', // Default if missing
            'material'         => $product->get_attribute('pa_material') ?: null,
            'condition'        => $this->getMetaValue($product, '_oapfw_condition'),
            'age_group'        => $this->getMetaValue($product, '_oapfw_age_group'),

            // Dimensions & Weight (weight is required)
            'weight'     => $this->formatWeight($product) ?: '0 kg', // Default if missing
            'length'     => $this->formatDimension($product->get_length()),
            'width'      => $this->formatDimension($product->get_width()),
            'height'     => $this->formatDimension($product->get_height()),
            'dimensions' => $this->formatDimensions($product),

            // Media
            'image_link'            => $this->getMainImage($product, $parent),
            'additional_image_link' => $this->getGalleryImages($product, $parent),
            'video_link'            => $this->getMetaValue($product, '_oapfw_video_link'),
            'model_3d_link'         => $this->getMetaValue($product, '_oapfw_model_3d_link'),

            // Price & Promotions
            'price'      => $this->formatPrice($product->get_regular_price(), $currency),
            'sale_price' => $this->formatPrice($product->get_sale_price(), $currency),
            'sale_price_effective_date' => $this->getSaleDateRange($product),

            // Availability & Inventory
            'availability'        => $this->getAvailability($product),
            'inventory_quantity'  => $product->get_stock_quantity() ?? 0,
            'availability_date'   => $this->getMetaValue($product, '_oapfw_availability_date'),
            'expiration_date'     => $this->getMetaValue($product, '_oapfw_expiration_date'),

            // Variants
            'item_group_id'    => $this->getItemGroupId($parent),
            'item_group_title' => $this->getItemGroupTitle($parent),
            'color'            => $product->get_attribute('pa_color') ?: null,
            'size'             => $product->get_attribute('pa_size') ?: null,
            'size_system'      => $product->get_attribute('pa_size_system') ?: null,
            'gender'           => $product->get_attribute('pa_gender') ?: null,

            // Merchant Info
            'seller_name'           => $this->settings->get('seller_name') ?: null,
            'seller_url'            => $this->settings->get('seller_url') ?: null,
            'seller_privacy_policy' => $this->settings->get('privacy_url') ?: null,
            'seller_tos'            => $this->settings->get('tos_url') ?: null,
            'return_policy'         => $this->settings->get('returns_url') ?: null,
            'return_window'         => $this->settings->get('return_window') ?: null,

            // Additional fields
            'warning'          => $this->getMetaValue($product, '_oapfw_warning'),
            'warning_url'      => $this->getMetaValue($product, '_oapfw_warning_url'),
            'age_restriction'  => $this->getMetaValue($product, '_oapfw_age_restriction'),
            'q_and_a'          => $this->getMetaValue($product, '_oapfw_q_and_a'),
        ];

        // Apply per-product overrides
        $row = $this->applyProductOverrides($product, $row);
        
        // Add shipping and fulfillment data
        $row['shipping'] = $this->getShippingData();
        $this->addPickupData($row);

        // Validate and clean up
        $row = $this->validateAndCleanRow($row);

        return apply_filters('oapfw_map_product', $row, $product, $parent);
    }

    /**
     * Get meta value with fallback
     */
    private function getMetaValue(\WC_Product $product, string $key): ?string
    {
        $value = get_post_meta($product->get_id(), $key, true);
        return !empty($value) ? wp_strip_all_tags($value) : null;
    }

    /**
     * Get product description
     */
    private function getDescription(\WC_Product $product): string
    {
        $description = $product->get_description() ?: $product->get_short_description();
        return $this->truncateText(wp_strip_all_tags($description), 5000);
    }

    /**
     * Get brand from attribute or meta
     */
    private function getBrand(\WC_Product $product, ?\WC_Product $parent): ?string
    {
        $brand = $product->get_attribute('pa_brand');
        if (!$brand && $parent) {
            $brand = $parent->get_attribute('pa_brand');
        }
        if (!$brand) {
            $brand = $this->getMetaValue($product, '_brand');
        }
        return $brand;
    }

    /**
     * Get category path
     */
    private function getCategoryPath(\WC_Product $product): ?string
    {
        $terms = get_the_terms($product->get_id(), 'product_cat');
        if (!$terms || is_wp_error($terms)) {
            return null;
        }

        // Find deepest category
        $deepest_term = null;
        $max_depth = -1;

        foreach ($terms as $term) {
            $depth = $this->getCategoryDepth($term);
            if ($depth > $max_depth) {
                $max_depth = $depth;
                $deepest_term = $term;
            }
        }

        if (!$deepest_term) {
            return null;
        }

        return $this->buildCategoryPath($deepest_term);
    }

    /**
     * Get category depth
     */
    private function getCategoryDepth(\WP_Term $term): int
    {
        $depth = 0;
        $current = $term;
        
        while ($current && $current->parent) {
            $current = get_term($current->parent, 'product_cat');
            if (is_wp_error($current)) {
                break;
            }
            $depth++;
        }
        
        return $depth;
    }

    /**
     * Build category path string
     */
    private function buildCategoryPath(\WP_Term $term): string
    {
        $path = [$term->name];
        $current = $term;
        
        while ($current->parent) {
            $current = get_term($current->parent, 'product_cat');
            if (is_wp_error($current)) {
                break;
            }
            array_unshift($path, $current->name);
        }
        
        return implode(' > ', $path);
    }

    /**
     * Format weight with unit
     */
    private function formatWeight(\WC_Product $product): ?string
    {
        $weight = $product->get_weight();
        if (!$weight) {
            return null;
        }
        
        $unit = get_option('woocommerce_weight_unit');
        return $weight . ' ' . $unit;
    }

    /**
     * Format dimension with unit
     */
    private function formatDimension(?string $dimension): ?string
    {
        if (!$dimension) {
            return null;
        }
        
        $unit = get_option('woocommerce_dimension_unit');
        return $dimension . ' ' . $unit;
    }

    /**
     * Format all dimensions
     */
    private function formatDimensions(\WC_Product $product): ?string
    {
        $length = $product->get_length();
        $width = $product->get_width();
        $height = $product->get_height();
        
        if (!$length || !$width || !$height) {
            return null;
        }
        
        $unit = get_option('woocommerce_dimension_unit');
        return sprintf('%sx%sx%s %s', $length, $width, $height, $unit);
    }

    /**
     * Get main product image
     */
    private function getMainImage(\WC_Product $product, ?\WC_Product $parent): string
    {
        $image_id = $product->get_image_id();
        if (!$image_id && $parent) {
            $image_id = $parent->get_image_id();
        }
        
        return $image_id ? wp_get_attachment_url($image_id) : '';
    }

    /**
     * Get gallery images
     */
    private function getGalleryImages(\WC_Product $product, ?\WC_Product $parent): array
    {
        $gallery_ids = $product->get_gallery_image_ids();
        if (empty($gallery_ids) && $parent) {
            $gallery_ids = $parent->get_gallery_image_ids();
        }
        
        return array_filter(array_map('wp_get_attachment_url', $gallery_ids));
    }

    /**
     * Format price with currency
     */
    private function formatPrice(?string $price, string $currency): ?string
    {
        return $price ? sprintf('%s %s', $price, $currency) : null;
    }

    /**
     * Get sale date range
     */
    private function getSaleDateRange(\WC_Product $product): ?string
    {
        $sale_price = $product->get_sale_price();
        if (!$sale_price) {
            return null;
        }
        
        $sale_from = $product->get_date_on_sale_from();
        $sale_to = $product->get_date_on_sale_to();
        
        if ($sale_from && $sale_to) {
            return $sale_from->date_i18n('Y-m-d') . ' / ' . $sale_to->date_i18n('Y-m-d');
        }
        
        return null;
    }

    /**
     * Get availability status
     */
    private function getAvailability(\WC_Product $product): string
    {
        $stock_status = $product->get_stock_status();
        
        switch ($stock_status) {
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
     * Get item group ID for variations
     */
    private function getItemGroupId(?\WC_Product $parent): ?string
    {
        if (!$parent) {
            return null;
        }
        
        return $parent->get_sku() ?: 'wc-' . $parent->get_id();
    }

    /**
     * Get item group title for variations
     */
    private function getItemGroupTitle(?\WC_Product $parent): ?string
    {
        return $parent ? wp_strip_all_tags($parent->get_name()) : null;
    }

    /**
     * Apply per-product flag overrides
     */
    private function applyProductOverrides(\WC_Product $product, array $row): array
    {
        $override_search = get_post_meta($product->get_id(), '_oapfw_enable_search', true);
        $override_checkout = get_post_meta($product->get_id(), '_oapfw_enable_checkout', true);
        
        if ($override_search !== '') {
            $row['enable_search'] = $this->boolString($override_search);
        }
        
        if ($override_checkout !== '') {
            $row['enable_checkout'] = $this->boolString($override_checkout);
        }
        
        return $row;
    }

    /**
     * Get shipping data from WooCommerce zones
     */
    private function getShippingData(): array
    {
        if (!class_exists('WC_Shipping_Zones')) {
            return [];
        }
        
        $shipping_data = [];
        $currency = get_woocommerce_currency();
        $zones = \WC_Shipping_Zones::get_zones();
        
        foreach ($zones as $zone) {
            $locations = $zone['zone_locations'];
            
            foreach ($zone['shipping_methods'] as $method) {
                $method_title = $method->get_method_title();
                $price = $this->getShippingPrice($method);
                
                foreach ($locations as $location) {
                    $shipping_string = $this->buildShippingString($location, $method_title, $price, $currency);
                    if ($shipping_string) {
                        $shipping_data[] = $shipping_string;
                    }
                }
            }
        }
        
        return array_values(array_unique($shipping_data));
    }

    /**
     * Get shipping price from method
     */
    private function getShippingPrice($method): string
    {
        if ($method->id === 'free_shipping') {
            return '0.00';
        }
        
        if (isset($method->settings['cost']) && is_numeric($method->settings['cost'])) {
            return $method->settings['cost'];
        }
        
        return '';
    }

    /**
     * Build shipping string for location
     */
    private function buildShippingString($location, string $method_title, string $price, string $currency): ?string
    {
        $country = '';
        $region = '';
        
        switch ($location->type) {
            case 'country':
                $country = $location->code;
                break;
            case 'state':
                list($country, $region) = array_pad(explode(':', $location->code), 2, '');
                break;
            case 'continent':
                $country = $location->code;
                break;
            default:
                return null;
        }
        
        $parts = array_filter([$country, $region, $method_title]);
        
        if ($price !== '') {
            $parts[] = sprintf('%s %s', $price, $currency);
        }
        
        return implode(':', array_map(function($part) {
            return trim($part, ':');
        }, $parts));
    }

    /**
     * Add pickup data if available
     */
    private function addPickupData(array &$row): void
    {
        if ($this->hasLocalPickup()) {
            $row['pickup_method'] = 'in_store';
            
            $pickup_sla = $this->settings->get('pickup_sla');
            if (!empty($pickup_sla)) {
                $row['pickup_sla'] = $pickup_sla;
            }
        }
    }

    /**
     * Check if local pickup is available
     */
    private function hasLocalPickup(): bool
    {
        if (!class_exists('WC_Shipping_Zones')) {
            return false;
        }
        
        $zones = \WC_Shipping_Zones::get_zones();
        
        foreach ($zones as $zone) {
            foreach ($zone['shipping_methods'] as $method) {
                if ($method->id === 'local_pickup') {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Validate and clean row data
     */
    private function validateAndCleanRow(array $row): array
    {
        // Ensure boolean strings
        $row['enable_search'] = $this->boolString($row['enable_search']);
        $row['enable_checkout'] = $this->boolString($row['enable_checkout']);
        
        // Checkout requires search
        if ($row['enable_checkout'] === 'true' && $row['enable_search'] !== 'true') {
            $row['enable_checkout'] = 'false';
        }
        
        // Ensure title/description limits
        if (!empty($row['title'])) {
            $row['title'] = $this->truncateText($row['title'], 150);
        }
        
        if (!empty($row['description'])) {
            $row['description'] = $this->truncateText($row['description'], 5000);
        }
        
        // GTIN is now required since WooCommerce doesn't have native MPN support
        // Set a fallback GTIN if missing (will be flagged by validation)
        if (empty($row['gtin'])) {
            $row['gtin'] = 'MISSING'; // Will be caught by validator
        }
        
        // Remove null and empty values
        return array_filter($row, function($value) {
            return $value !== null && $value !== '';
        });
    }

    /**
     * Convert value to boolean string
     */
    private function boolString($value): string
    {
        $value = strtolower((string) $value);
        return ($value === 'true' || $value === '1' || $value === 'yes') ? 'true' : 'false';
    }

    /**
     * Truncate text to specified length
     */
    private function truncateText(string $text, int $max_length): string
    {
        if (mb_strlen($text) > $max_length) {
            return mb_substr($text, 0, $max_length);
        }
        return $text;
    }
}