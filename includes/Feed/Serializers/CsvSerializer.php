<?php
/**
 *  Csv Serializer class.
 *
 * @package OAPFW
 */

declare(strict_types=1);

namespace OAPFW\Feed\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// This file works with the buffer stream, so we need to disable the file system operations checks.
// It should be fine given that we are not touching any files.
// phpcs:disable WordPress.WP.AlternativeFunctions

/**
 * CSV serializer
 */
class CsvSerializer extends AbstractSerializer {
	/**
	 * Serialize data to CSV format.
	 *
	 * @param array $data The data to serialize.
	 * @return string CSV formatted string.
	 */
	public function serialize( array $data ): string {
		if ( ! $data ) {
			return '';
		}

		$handle = fopen( 'php://temp', 'w+' );

		fputcsv( $handle, array_keys( $data[0] ) );

		foreach ( $data as $row ) {
			fputcsv( $handle, $this->stringifyValues( $row ) );
		}

		rewind( $handle );
		$content = stream_get_contents( $handle );
		fclose( $handle );

		return $content;
	}

	/**
	 * Get the content type for CSV.
	 *
	 * @return string The content type.
	 */
	public function get_content_type(): string {
		return 'text/csv';
	}

	/**
	 * Get the file extension for CSV.
	 *
	 * @return string The file extension.
	 */
	public function get_file_extension(): string {
		return 'csv';
	}
}
