<?php
/**
 *  Feed Validator class.
 *
 * @package OAPFW
 */

declare(strict_types=1);

namespace OAPFW\Platforms\OpenAI\Validators;

use OAPFW\Core\Interfaces\ValidatorInterface;
use OAPFW\Platforms\OpenAI\Schema\OpenAIFeedSchema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schema-driven feed data validator
 *
 * Validates product feed data against OpenAI Product Feed specification.
 * Handles field-level validation, data type checking, and business rule validation.
 */
class FeedValidator implements ValidatorInterface {

	/**
	 * OpenAI feed schema configuration array.
	 *
	 * @var array
	 */
	private array $schema;

	/**
	 * Initialize the validator with OpenAI feed schema
	 *
	 * Loads the complete OpenAI Product Feed schema for validation.
	 */
	public function __construct() {
		$this->schema = OpenAIFeedSchema::get_schema();
	}

	/**
	 * Validate single feed row using schema
	 *
	 * @param array $row Product data row to validate.
	 * @return array Array of validation issues.
	 */
	public function validate_row( array $row ): array {
		$issues = [];

		foreach ( $this->schema as $field => $config ) {
			$this->validate_field( $row, $field, $config, $issues );
		}

		// Additional custom validations.
		$this->validate_brand_requirement( $row, $issues );
		$this->validate_prices( $row, $issues );
		$this->validate_sale_dates( $row, $issues );

		return $issues;
	}

	/**
	 * Validate individual field based on schema
	 *
	 * @param array  $row Product data row.
	 * @param string $field Field name to validate.
	 * @param array  $config Field configuration from schema.
	 * @param array  $issues Reference to issues array.
	 */
	private function validate_field( array $row, string $field, array $config, array &$issues ): void {
		$value = $row[ $field ] ?? null;

		if ( OpenAIFeedSchema::is_field_required( $field, $row ) ) {
			if ( empty( $value ) && '0' !== $value ) {
				$message  = $config['error_message'] ?? "Missing {$field}";
				$issues[] = $message;
				return;
			}
		}

		if ( empty( $value ) && '0' !== $value ) {
			return;
		}

		$this->validate_field_type( $field, $value, $config, $issues );
		$this->validate_field_pattern( $field, $value, $config, $issues );
		$this->validate_field_enum( $field, $value, $config, $issues );
		$this->validate_field_dependencies( $field, $value, $config, $row, $issues );
	}

	/**
	 * Validate field type
	 *
	 * @param string $field Field name.
	 * @param mixed  $value Field value.
	 * @param array  $config Field configuration.
	 * @param array  $issues Reference to issues array.
	 */
	private function validate_field_type( string $field, $value, array $config, array &$issues ): void {
		switch ( $config['type'] ) {
			case 'integer':
				if ( ! is_numeric( $value ) ) {
					$issues[] = "{$field} must be a number";
				}
				break;

			case 'url':
				if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
					$issues[] = "{$field} must be a valid URL";
				}
				break;

			case 'boolean_string':
				if ( ! in_array( $value, [ 'true', 'false', true ], true ) ) {
					$issues[] = "{$field} must be 'true' or 'false'";
				}
				break;
		}
	}

	/**
	 * Validate field pattern
	 *
	 * @param string $field Field name.
	 * @param mixed  $value Field value.
	 * @param array  $config Field configuration.
	 * @param array  $issues Reference to issues array.
	 */
	private function validate_field_pattern( string $field, $value, array $config, array &$issues ): void {
		if ( isset( $config['pattern'] ) && ! preg_match( $config['pattern'], (string) $value ) ) {
			$message  = $config['error_message'] ?? "{$field} format is invalid";
			$issues[] = $message;
		}
	}

	/**
	 * Validate enum values
	 *
	 * @param string $field Field name.
	 * @param mixed  $value Field value.
	 * @param array  $config Field configuration.
	 * @param array  $issues Reference to issues array.
	 */
	private function validate_field_enum( string $field, $value, array $config, array &$issues ): void {
		if ( isset( $config['values'] ) && ! in_array( $value, $config['values'], true ) ) {
			$valid    = implode( '|', $config['values'] );
			$issues[] = "{$field} must be {$valid}";
		}
	}

	/**
	 * Validate field dependencies
	 *
	 * @param string $field Field name.
	 * @param mixed  $value Field value.
	 * @param array  $config Field configuration.
	 * @param array  $row Product data row.
	 * @param array  $issues Reference to issues array.
	 */
	private function validate_field_dependencies( string $field, $value, array $config, array $row, array &$issues ): void {
		if ( isset( $config['depends_on'] ) ) {
			foreach ( $config['depends_on'] as $dep_field => $dep_value ) {
				$current_value = $row[ $dep_field ] ?? null;
				if ( 'true' === $value && $dep_value !== $current_value ) {
					$issues[] = "{$field} requires {$dep_field}={$dep_value}";
				}
			}
		}
	}

	/**
	 * Validate entire feed
	 *
	 * @param array $rows Array of product data rows.
	 * @return array Array of validation issues.
	 */
	public function validate_feed( array $rows ): array {
		$all_issues = [];

		if ( empty( $rows ) ) {
			$all_issues[] = [
				'id'     => 'feed',
				'issues' => [ 'Feed is empty - no products to export' ],
			];
			return $all_issues;
		}

		foreach ( $rows as $index => $row ) {
			$row_issues = $this->validate_row( $row );
			if ( $row_issues ) {
				$all_issues[] = [
					'id'     => $row['id'] ?? ( '#' . $index ),
					'issues' => $row_issues,
				];
			}
		}

		return $all_issues;
	}


	/**
	 * Validate brand requirement (custom logic for exempt categories)
	 *
	 * @param array $row Product data row.
	 * @param array $issues Reference to issues array.
	 */
	private function validate_brand_requirement( array $row, array &$issues ): void {
		$brand_config      = $this->schema['brand'];
		$category          = strtolower( $row['product_category'] ?? '' );
		$exempt_categories = $brand_config['exempt_categories'] ?? [];

		$is_exempt = false;
		foreach ( $exempt_categories as $exempt ) {
			if ( strpos( $category, $exempt ) !== false ) {
				$is_exempt = true;
				break;
			}
		}

		if ( ! $is_exempt && empty( $row['brand'] ) ) {
			$issues[] = 'Brand is required (except for movies, books, music)';
		}
	}

	/**
	 * Validate price relationships
	 *
	 * @param array $row Product data row.
	 * @param array $issues Reference to issues array.
	 */
	private function validate_prices( array $row, array &$issues ): void {
		if ( ! empty( $row['sale_price'] ) && ! empty( $row['price'] ) ) {
			$sale_price    = $this->extract_numeric_value( $row['sale_price'] );
			$regular_price = $this->extract_numeric_value( $row['price'] );

			if ( $sale_price > $regular_price ) {
				$issues[] = 'sale_price must be <= price';
			}
		}
	}

	/**
	 * Validate sale date range
	 *
	 * @param array $row Product data row.
	 * @param array $issues Reference to issues array.
	 */
	private function validate_sale_dates( array $row, array &$issues ): void {
		if ( ! empty( $row['sale_price_effective_date'] ) && strpos( $row['sale_price_effective_date'], '/' ) !== false ) {
			[$start, $end] = array_map( 'trim', explode( '/', $row['sale_price_effective_date'] ) );

			if ( $start && $end && $start > $end ) {
				$issues[] = 'sale window start must precede end';
			}
		}
	}


	/**
	 * Extract numeric value from price string
	 *
	 * @param string $value Price value string.
	 * @return float Extracted numeric value.
	 */
	private function extract_numeric_value( string $value ): float {
		if ( preg_match( '/([0-9]+(?:\.[0-9]+)?)/', $value, $matches ) ) {
			return (float) $matches[1];
		}
		return 0.0;
	}
}
