<?php
/**
 *  Json Serializer class.
 *
 * @package OAPFW
 */

declare(strict_types=1);

namespace OAPFW\Feed\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * JSON serializer
 */
class JsonSerializer extends AbstractSerializer {

	/**
	 * Serialize data to JSON format.
	 *
	 * @param array $data The data to serialize.
	 * @return string JSON encoded string.
	 */
	public function serialize( array $data ): string {
		return wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	/**
	 * Get the content type for JSON.
	 *
	 * @return string The content type.
	 */
	public function get_content_type(): string {
		return 'application/json';
	}

	/**
	 * Get the file extension for JSON.
	 *
	 * @return string The file extension.
	 */
	public function get_file_extension(): string {
		return 'json';
	}
}
