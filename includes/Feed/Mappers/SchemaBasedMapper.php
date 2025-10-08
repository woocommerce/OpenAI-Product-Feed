<?php

declare(strict_types=1);

namespace OAPFW\Feed\Mappers;

use OAPFW\Feed\Schema\OpenAIFeedSchema;
use OAPFW\Core\SettingsRepositoryInterface;
use OAPFW\Utils\StringHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schema-driven base mapper for OpenAI feed
 */
abstract class SchemaBasedMapper {

	protected SettingsRepositoryInterface $settings;
	protected array $schema;
	protected array $product_meta_cache = array();

	public function __construct( SettingsRepositoryInterface $settings ) {
		$this->settings = $settings;
		$this->schema   = OpenAIFeedSchema::getSchema();
	}

	/**
	 * Map product using schema definition
	 */
	protected function mapProductBySchema( \WC_Product $product, ?\WC_Product $parent = null ): array {
		$row = array();

		foreach ( $this->schema as $field => $config ) {
			$row[ $field ] = $this->mapField( $product, $parent, $field, $config );
		}

		$row = $this->validateAndCleanRow( $row );

		return apply_filters( 'oapfw_map_product', $row, $product, $parent );
	}

	/**
	 * Map individual field based on configuration
	 */
	protected function mapField( \WC_Product $product, ?\WC_Product $parent, string $field, array $config ) {
		$mapperMethod = $config['mapper'] ?? null;

		if ( $mapperMethod && method_exists( $this, $mapperMethod ) ) {
			$value = $this->$mapperMethod( $product, $parent );
		} else {
			$value = $this->getMetaValue( $product, "_oapfw_{$field}" );
		}

		// Apply default if empty and default is specified
		if ( empty( $value ) && isset( $config['default'] ) ) {
			$value = $config['default'];
		}

		// Apply type conversion and validation
		return $this->convertType( $value, $config );
	}

	/**
	 * Convert value to appropriate type
	 */
	protected function convertType( $value, array $config ) {
		if ( $value === null || $value === '' ) {
			return $value;
		}

		switch ( $config['type'] ) {
			case 'boolean_string':
				return StringHelper::boolString( $value );

			case 'integer':
				return (int) $value;

			case 'string':
				$value = (string) $value;
				if ( isset( $config['max_length'] ) ) {
					$value = StringHelper::truncate( $value, $config['max_length'] );
				}
				return $value;

			case 'array':
				return is_array( $value ) ? $value : array();

			default:
				return $value;
		}
	}

	/**
	 * Validate and clean row data using schema
	 */
	protected function validateAndCleanRow( array $row ): array {
		foreach ( $this->schema as $field => $config ) {
			if ( ! isset( $row[ $field ] ) ) {
				continue;
			}

			// Handle dependencies (e.g., checkout requires search)
			if ( isset( $config['depends_on'] ) ) {
				foreach ( $config['depends_on'] as $depField => $depValue ) {
					if ( ( $row[ $depField ] ?? null ) !== $depValue ) {
						if ( $config['type'] === 'boolean_string' ) {
							$row[ $field ] = 'false';
						} else {
							unset( $row[ $field ] );
						}
						break;
					}
				}
			}

			// Pattern validation
			if ( isset( $config['pattern'] ) && ! empty( $row[ $field ] ) ) {
				if ( ! preg_match( $config['pattern'], (string) $row[ $field ] ) ) {
					if ( $field === 'gtin' ) {
						$row[ $field ] = 'MISSING'; // Will be caught by validator
					}
				}
			}
		}

		// Remove null and empty values
		return array_filter(
			$row,
			function ( $value ) {
				return $value !== null && $value !== '';
			}
		);
	}

	/**
	 * Get meta value with fallback (with caching to prevent N+1 queries)
	 */
	protected function getMetaValue( \WC_Product $product, string $key ): ?string {
		$product_id = $product->get_id();
		
		// Load all meta for this product if not cached yet
		if ( ! isset( $this->product_meta_cache[ $product_id ] ) ) {
			$this->product_meta_cache[ $product_id ] = get_post_meta( $product_id );
		}
		
		$value = $this->product_meta_cache[ $product_id ][ $key ][0] ?? null;
		return ! empty( $value ) ? wp_strip_all_tags( $value ) : null;
	}

	// Abstract methods that implementing classes must define
	abstract protected function getId( \WC_Product $product, ?\WC_Product $parent ): string;
	abstract protected function getTitle( \WC_Product $product, ?\WC_Product $parent ): string;
	abstract protected function getDescription( \WC_Product $product, ?\WC_Product $parent ): string;
	abstract protected function getLink( \WC_Product $product, ?\WC_Product $parent ): string;
	abstract protected function getAvailability( \WC_Product $product, ?\WC_Product $parent ): string;
	abstract protected function getInventoryQuantity( \WC_Product $product, ?\WC_Product $parent ): int;
}
