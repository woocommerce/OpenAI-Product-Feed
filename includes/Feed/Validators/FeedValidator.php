<?php
namespace OAPFW\Feed\Validators;

use OAPFW\Core\ValidatorInterface;
use OAPFW\Feed\Schema\OpenAIFeedSchema;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Schema-driven feed data validator
 */
class FeedValidator implements ValidatorInterface
{
    private array $schema;

    public function __construct()
    {
        $this->schema = OpenAIFeedSchema::getSchema();
    }

    /**
     * Validate single feed row using schema
     */
    public function validateRow(array $row): array
    {
        $issues = [];
        
        foreach ($this->schema as $field => $config) {
            $this->validateField($row, $field, $config, $issues);
        }
        
        // Additional custom validations
        $this->validateBrandRequirement($row, $issues);
        $this->validatePrices($row, $issues);
        $this->validateSaleDates($row, $issues);
        
        return $issues;
    }

    /**
     * Validate individual field based on schema
     */
    private function validateField(array $row, string $field, array $config, array &$issues): void
    {
        $value = $row[$field] ?? null;
        
        // Check required fields
        if (OpenAIFeedSchema::isFieldRequired($field, $row)) {
            if (empty($value) && $value !== '0') {
                $message = $config['error_message'] ?? "Missing {$field}";
                $issues[] = $message;
                return;
            }
        }
        
        // Skip validation if field is empty and not required
        if (empty($value) && $value !== '0') {
            return;
        }
        
        // Type and format validation
        $this->validateFieldType($field, $value, $config, $issues);
        $this->validateFieldPattern($field, $value, $config, $issues);
        $this->validateFieldEnum($field, $value, $config, $issues);
        $this->validateFieldDependencies($field, $value, $config, $row, $issues);
    }

    /**
     * Validate field type
     */
    private function validateFieldType(string $field, $value, array $config, array &$issues): void
    {
        switch ($config['type']) {
            case 'integer':
                if (!is_numeric($value)) {
                    $issues[] = "{$field} must be a number";
                }
                break;
            
            case 'url':
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    $issues[] = "{$field} must be a valid URL";
                }
                break;
            
            case 'boolean_string':
                if (!in_array($value, ['true', 'false'], true)) {
                    $issues[] = "{$field} must be 'true' or 'false'";
                }
                break;
        }
    }

    /**
     * Validate field pattern
     */
    private function validateFieldPattern(string $field, $value, array $config, array &$issues): void
    {
        if (isset($config['pattern']) && !preg_match($config['pattern'], (string)$value)) {
            $message = $config['error_message'] ?? "{$field} format is invalid";
            $issues[] = $message;
        }
    }

    /**
     * Validate enum values
     */
    private function validateFieldEnum(string $field, $value, array $config, array &$issues): void
    {
        if (isset($config['values']) && !in_array($value, $config['values'], true)) {
            $valid = implode('|', $config['values']);
            $issues[] = "{$field} must be {$valid}";
        }
    }

    /**
     * Validate field dependencies
     */
    private function validateFieldDependencies(string $field, $value, array $config, array $row, array &$issues): void
    {
        if (isset($config['depends_on'])) {
            foreach ($config['depends_on'] as $depField => $depValue) {
                if ($value === 'true' && ($row[$depField] ?? null) !== $depValue) {
                    $issues[] = "{$field} requires {$depField}={$depValue}";
                }
            }
        }
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
     * Validate brand requirement (custom logic for exempt categories)
     */
    private function validateBrandRequirement(array $row, array &$issues): void
    {
        $brandConfig = $this->schema['brand'];
        $category = strtolower($row['product_category'] ?? '');
        $exemptCategories = $brandConfig['exempt_categories'] ?? [];
        
        $isExempt = false;
        foreach ($exemptCategories as $exempt) {
            if (strpos($category, $exempt) !== false) {
                $isExempt = true;
                break;
            }
        }
        
        if (!$isExempt && empty($row['brand'])) {
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