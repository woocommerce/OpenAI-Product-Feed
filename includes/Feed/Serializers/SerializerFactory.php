<?php
/**
 *  Serializer Factory class.
 *
 * @package OAPFW
 */

declare(strict_types=1);

namespace OAPFW\Feed\Serializers;

use OAPFW\Core\Interfaces\SerializerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Factory for creating serializers
 */
class SerializerFactory {

	/**
	 * Create serializer by format.
	 *
	 * @param string $format The format to create serializer for.
	 * @return SerializerInterface The serializer instance.
	 */
	public static function create( string $format ): SerializerInterface {
		switch ( strtolower( $format ) ) {
			case 'csv':
				return new CsvSerializer();
			case 'xml':
				return new XmlSerializer();
			case 'tsv':
				return new TsvSerializer();
			case 'json':
			default:
				return new JsonSerializer();
		}
	}

	/**
	 * Get available formats.
	 *
	 * @return array Array of available formats.
	 */
	public static function get_available_formats(): array {
		return [ 'json', 'csv', 'xml', 'tsv' ];
	}

	/**
	 * Check if format is supported.
	 *
	 * @param string $format The format to check.
	 * @return bool True if format is supported.
	 */
	public static function is_format_supported( string $format ): bool {
		return in_array( strtolower( $format ), self::get_available_formats(), true );
	}
}
