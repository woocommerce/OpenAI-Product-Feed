<?php
/**
 * Abstract  Schema Based Mapper class.
 *
 * @package OAPFW
 */

declare(strict_types=1);

namespace OAPFW\Platforms\OpenAI\Mappers;

use OAPFW\Platforms\OpenAI\Schema\OpenAIFeedSchema;
use OAPFW\Core\Interfaces\SettingsRepositoryInterface;
use OAPFW\Utils\StringHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schema-driven base mapper for OpenAI feed
 */
abstract class SchemaBasedMapper {

	/**
	 * Settings repository instance.
	 *
	 * @var SettingsRepositoryInterface
	 */
	protected SettingsRepositoryInterface $settings;

	/**
	 * OpenAI feed schema definition.
	 *
	 * @var array
	 */
	protected array $schema;

	/**
	 * Product meta cache to prevent N+1 queries.
	 *
	 * @var array
	 */
	protected array $product_meta_cache = array();

	/**
	 * Constructor.
	 *
	 * @param SettingsRepositoryInterface $settings Settings repository.
	 */
	public function __construct( SettingsRepositoryInterface $settings ) {
		$this->settings = $settings;
		$this->schema   = OpenAIFeedSchema::get_schema();
	}

	/**
	 * Map product using schema definition
	 *
	 * @param \WC_Product      $product Product object.
	 * @param \WC_Product|null $parent_product  Parent product for variations.
	 * @return array Mapped product data.
	 */
	public function map_product_by_schema( \WC_Product $product, ?\WC_Product $parent_product = null ): array {
		$row = array();

		foreach ( $this->schema as $field => $config ) {
			$row[ $field ] = $this->map_field( $product, $parent_product, $field, $config );
		}

		$row = $this->validate_and_clean_row( $row );

		/**
		 * Filter mapped product data before validation.
		 *
		 * @since 1.0.0
		 * @param array            $row     Mapped product data.
		 * @param \WC_Product      $product Product object.
		 * @param \WC_Product|null $parent_product  Parent product for variations.
		 */
		return apply_filters( 'oapfw_map_product', $row, $product, $parent_product );
	}

	/**
	 * Map individual field based on configuration
	 *
	 * @param \WC_Product      $product Product object.
	 * @param \WC_Product|null $parent_product  Parent product for variations.
	 * @param string           $field   Field name to map.
	 * @param array            $config  Field configuration from schema.
	 * @return mixed Mapped field value.
	 */
	protected function map_field( \WC_Product $product, ?\WC_Product $parent_product, string $field, array $config ) {
		$field_mappings = $this->get_field_mappings();
		$mapper_method  = $field_mappings[ $field ] ?? null;

		if ( $mapper_method && method_exists( $this, $mapper_method ) ) {
			$value = $this->$mapper_method( $product, $parent_product );
		} else {
			$value = $this->get_meta_value( $product, "_oapfw_{$field}" );
		}

		if ( empty( $value ) && isset( $config['default'] ) ) {
			$value = $config['default'];
		}

		return $this->convert_type( $value, $config );
	}

	/**
	 * Convert value to appropriate type
	 *
	 * @param mixed $value  Value to convert.
	 * @param array $config Field configuration.
	 * @return mixed Converted value.
	 */
	protected function convert_type( $value, array $config ) {
		if ( null === $value || '' === $value ) {
			return $value;
		}

		switch ( $config['type'] ) {
			case 'boolean_string':
				return StringHelper::bool_string( $value );

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
	 *
	 * @param array $row Product data row.
	 * @return array Cleaned product data row.
	 */
	protected function validate_and_clean_row( array $row ): array {
		foreach ( $this->schema as $field => $config ) {
			if ( ! isset( $row[ $field ] ) ) {
				continue;
			}

			if ( isset( $config['depends_on'] ) ) {
				foreach ( $config['depends_on'] as $dep_field => $dep_value ) {
					$current_value = $row[ $dep_field ] ?? null;
					if ( $dep_value !== $current_value ) {
						if ( 'boolean_string' === $config['type'] ) {
							$row[ $field ] = 'false';
						} else {
							unset( $row[ $field ] );
						}
						break;
					}
				}
			}

			if ( isset( $config['pattern'] ) && ! empty( $row[ $field ] ) ) {
				if ( ! preg_match( $config['pattern'], (string) $row[ $field ] ) ) {
					if ( 'gtin' === $field ) {
						$row[ $field ] = 'MISSING'; // Will be caught by validator.
					}
				}
			}
		}

		return array_filter(
			$row,
			function ( $value ) {
				return null !== $value && '' !== $value;
			}
		);
	}

	/**
	 * Get meta value with fallback (with caching to prevent N+1 queries)
	 *
	 * @param \WC_Product $product Product object.
	 * @param string      $key     Meta key to retrieve.
	 * @return string|null Meta value or null if not found.
	 */
	protected function get_meta_value( \WC_Product $product, string $key ): ?string {
		$product_id = $product->get_id();

		if ( ! isset( $this->product_meta_cache[ $product_id ] ) ) {
			$this->product_meta_cache[ $product_id ] = get_post_meta( $product_id );
		}

		$value = isset( $this->product_meta_cache[ $product_id ][ $key ][0] )
			? $this->product_meta_cache[ $product_id ][ $key ][0]
			: null;
		return ! empty( $value ) ? wp_strip_all_tags( $value ) : null;
	}

	/**
	 * Get field mappings for this mapper
	 *
	 * Maps schema field names to mapper method names.
	 * This allows different AI platforms to use different field mappings.
	 *
	 * @return array Field name to method name mappings
	 */
	abstract protected function get_field_mappings(): array;

	// Required field mappers - implementing classes must provide these.

	/**
	 * Get product ID.
	 *
	 * @param \WC_Product      $product Product object.
	 * @param \WC_Product|null $parent_product  Parent product for variations.
	 * @return string Product ID.
	 */
	abstract protected function get_id( \WC_Product $product, ?\WC_Product $parent_product ): string;

	/**
	 * Get product title.
	 *
	 * @param \WC_Product      $product Product object.
	 * @param \WC_Product|null $parent_product  Parent product for variations.
	 * @return string Product title.
	 */
	abstract protected function get_title( \WC_Product $product, ?\WC_Product $parent_product ): string;

	/**
	 * Get product description.
	 *
	 * @param \WC_Product      $product Product object.
	 * @param \WC_Product|null $parent_product  Parent product for variations.
	 * @return string Product description.
	 */
	abstract protected function get_description( \WC_Product $product, ?\WC_Product $parent_product ): string;

	/**
	 * Get product link.
	 *
	 * @param \WC_Product      $product Product object.
	 * @param \WC_Product|null $parent_product  Parent product for variations.
	 * @return string Product link.
	 */
	abstract protected function get_link( \WC_Product $product, ?\WC_Product $parent_product ): string;

	/**
	 * Get product availability.
	 *
	 * @param \WC_Product      $product Product object.
	 * @param \WC_Product|null $parent_product  Parent product for variations.
	 * @return string Product availability.
	 */
	abstract protected function get_availability( \WC_Product $product, ?\WC_Product $parent_product ): string;

	/**
	 * Get product inventory quantity.
	 *
	 * @param \WC_Product      $product Product object.
	 * @param \WC_Product|null $parent_product  Parent product for variations.
	 * @return int Inventory quantity.
	 */
	abstract protected function get_inventory_quantity( \WC_Product $product, ?\WC_Product $parent_product ): int;
}
