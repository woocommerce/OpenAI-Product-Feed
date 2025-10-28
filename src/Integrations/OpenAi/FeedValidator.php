<?php
/**
 *  Feed Validator class.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

declare(strict_types=1);

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Integrations\OpenAi;

use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\FeedValidatorInterface;
use Automattic\WooCommerce\ProductFeedForOpenAI\Integrations\OpenAi\FeedSchema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schema-driven feed data validator
 *
 * Validates product feed data against OpenAI Product Feed specification.
 * Handles field-level validation, data type checking, and business rule validation.
 */
final class FeedValidator implements FeedValidatorInterface {

	/**
	 * OpenAI feed schema configuration array.
	 *
	 * @var array
	 */
	private array $schema;

	/**
	 * Dependency Injector.
	 *
	 * Loads the complete OpenAI Product Feed schema for validation.
	 */
	public function init() {
		$this->schema = FeedSchema::get_schema();
	}

	/**
	 * Validate single feed row using schema
	 *
	 * @param array       $entry   Product data row to validate.
	 * @param \WC_Product $product The related product. Will be updated with validation status.
	 * @return array Array of validation issues.
	 */
	public function validate_entry( array $entry, \WC_Product $product ): array { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$issues = [];

		foreach ( $this->schema as $field => $config ) {
			$this->validate_field( $entry, $field, $config, $issues );
		}

		// Additional custom validations.
		$this->validate_brand_requirement( $entry, $issues );
		$this->validate_sale_dates( $entry, $issues );

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

		if ( FeedSchema::is_field_required( $field, $row ) ) {
			if ( empty( $value ) && '0' !== $value ) {
				$message  = $config['error_message'] ?? 'Missing required field: ' . $config['description'];
				$issues[] = $message;
				return;
			}
		}

		/**
		 * From specs: enable_search must be true in order for enable_checkout to be enabled for the product.
		 */
		if ( 'enable_checkout' === $field
			&& 'true' === $value
			&& 'false' === ( $row['enable_search'] ?? null )
		) {
			$issues[] = 'enable_checkout requires enable_search=true';
		}

		if ( empty( $value ) && '0' !== $value ) {
			return;
		}

		$this->validate_field_type( $field, $value, $config, $issues );
		$this->validate_field_enum( $field, $value, $config, $issues );
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
