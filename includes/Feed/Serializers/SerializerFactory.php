<?php
namespace OAPFW\Feed\Serializers;

use OAPFW\Core\SerializerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Factory for creating serializers
 */
class SerializerFactory {

	/**
	 * Create serializer by format
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
	 * Get available formats
	 */
	public static function getAvailableFormats(): array {
		return array( 'json', 'csv', 'xml', 'tsv' );
	}

	/**
	 * Check if format is supported
	 */
	public static function isFormatSupported( string $format ): bool {
		return in_array( strtolower( $format ), self::getAvailableFormats(), true );
	}
}
