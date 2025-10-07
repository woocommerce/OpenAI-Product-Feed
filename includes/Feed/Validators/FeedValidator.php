<?php
namespace OAPFW\Feed\Validators;

use OAPFW\Core\ValidatorInterface;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Feed data validator
 */
class FeedValidator implements ValidatorInterface
{
    /**
     * Validate single feed row
     */
    public function validateRow(array $row): array
    {
        $issues = [];
        
        // Required fields (per OpenAI specification)
        $this->validateRequiredField($row, 'id', 'Missing id', $issues);
        $this->validateRequiredField($row, 'title', 'Missing title', $issues);
        $this->validateRequiredField($row, 'description', 'Missing description', $issues);
        $this->validateRequiredField($row, 'link', 'Missing link', $issues);
        $this->validateRequiredField($row, 'availability', 'Missing availability', $issues);
        $this->validateRequiredField($row, 'inventory_quantity', 'Missing inventory_quantity', $issues);
        $this->validateRequiredField($row, 'enable_search', 'Missing enable_search', $issues);
        $this->validateRequiredField($row, 'enable_checkout', 'Missing enable_checkout', $issues);
        
        // Brand is required (except for movies, books, music)
        $this->validateBrandRequirement($row, $issues);
        
        // Weight is required
        $this->validateRequiredField($row, 'weight', 'Missing weight', $issues);
        
        // GTIN validation - now required since WooCommerce doesn't have MPN
        $this->validateGtinRequirement($row, $issues);
        
        // Price validation
        $this->validatePrices($row, $issues);
        
        // Sale date validation
        $this->validateSaleDates($row, $issues);
        
        // Checkout/search dependency
        $this->validateCheckoutDependency($row, $issues);
        
        // Availability validation
        $this->validateAvailability($row, $issues);
        
        // Preorder validation
        $this->validatePreorder($row, $issues);
        
        return $issues;
    }

    /**
     * Validate entire feed
     */
    public function validateFeed(array $rows): array
    {
        $all_issues = [];
        
        foreach ($rows as $index => $row) {
            $row_issues = $this->validateRow($row);
            if ($row_issues) {
                $all_issues[] = [
                    'id' => $row['id'] ?? ('#' . $index),
                    'issues' => $row_issues
                ];
            }
        }
        
        return $all_issues;
    }

    /**
     * Validate required field
     */
    private function validateRequiredField(array $row, string $field, string $message, array &$issues): void
    {
        if (empty($row[$field]) && $row[$field] !== '0') {
            $issues[] = $message;
        }
    }

    /**
     * Validate GTIN requirement and format
     */
    private function validateGtinRequirement(array $row, array &$issues): void
    {
        if (empty($row['gtin']) || $row['gtin'] === 'MISSING') {
            $issues[] = 'GTIN is required (8-14 digit universal product identifier)';
        } elseif (!preg_match('/^\d{8,14}$/', (string)$row['gtin'])) {
            $issues[] = 'GTIN invalid (must be 8–14 digits only)';
        }
    }

    /**
     * Validate brand requirement
     */
    private function validateBrandRequirement(array $row, array &$issues): void
    {
        // Brand is required except for movies, books, music categories
        $category = strtolower($row['product_category'] ?? '');
        $exempt_categories = ['books', 'movies', 'music', 'media'];
        
        $is_exempt = false;
        foreach ($exempt_categories as $exempt) {
            if (strpos($category, $exempt) !== false) {
                $is_exempt = true;
                break;
            }
        }
        
        if (!$is_exempt && empty($row['brand'])) {
            $issues[] = 'Brand is required (except for movies, books, music)';
        }
    }

    /**
     * Validate price relationships
     */
    private function validatePrices(array $row, array &$issues): void
    {
        if (!empty($row['sale_price']) && !empty($row['price'])) {
            $sale_price = $this->extractNumericValue($row['sale_price']);
            $regular_price = $this->extractNumericValue($row['price']);
            
            if ($sale_price > $regular_price) {
                $issues[] = 'sale_price must be <= price';
            }
        }
    }

    /**
     * Validate sale date range
     */
    private function validateSaleDates(array $row, array &$issues): void
    {
        if (!empty($row['sale_price_effective_date']) && strpos($row['sale_price_effective_date'], '/') !== false) {
            [$start, $end] = array_map('trim', explode('/', $row['sale_price_effective_date']));
            
            if ($start && $end && $start > $end) {
                $issues[] = 'sale window start must precede end';
            }
        }
    }

    /**
     * Validate checkout dependency on search
     */
    private function validateCheckoutDependency(array $row, array &$issues): void
    {
        if (!empty($row['enable_checkout']) && 
            $row['enable_checkout'] === 'true' && 
            ($row['enable_search'] ?? '') !== 'true') {
            $issues[] = 'enable_checkout requires enable_search=true';
        }
    }

    /**
     * Validate availability values
     */
    private function validateAvailability(array $row, array &$issues): void
    {
        if (!empty($row['availability'])) {
            $valid_values = ['in_stock', 'out_of_stock', 'preorder'];
            if (!in_array($row['availability'], $valid_values, true)) {
                $issues[] = 'availability must be in_stock|out_of_stock|preorder';
            }
        }
    }

    /**
     * Validate preorder requirements
     */
    private function validatePreorder(array $row, array &$issues): void
    {
        if (!empty($row['availability']) && 
            $row['availability'] === 'preorder' && 
            empty($row['availability_date'])) {
            $issues[] = 'availability_date required for preorder';
        }
    }

    /**
     * Extract numeric value from price string
     */
    private function extractNumericValue(string $value): float
    {
        if (preg_match('/([0-9]+(?:\.[0-9]+)?)/', $value, $matches)) {
            return (float) $matches[1];
        }
        return 0.0;
    }
}