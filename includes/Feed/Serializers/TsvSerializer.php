<?php
/**
 *  Tsv Serializer class.
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
 * TSV (Tab-Separated Values) serializer
 */
class TsvSerializer extends AbstractSerializer {

	/**
	 * Serialize data to TSV format.
	 *
	 * @param array $data The data to serialize.
	 * @return string TSV formatted string.
	 */
	public function serialize( array $data ): string {
		if ( ! $data ) {
			return '';
		}

		$handle = fopen( 'php://temp', 'w+' );

		fwrite( $handle, implode( "\t", array_keys( $data[0] ) ) . "\n" );

		foreach ( $data as $row ) {
			fwrite( $handle, implode( "\t", $this->stringifyValues( $row ) ) . "\n" );
		}

		rewind( $handle );
		$content = stream_get_contents( $handle );
		fclose( $handle );

		return $content;
	}

	/**
	 * Get the content type for TSV.
	 *
	 * @return string The content type.
	 */
	public function get_content_type(): string {
		return 'text/tab-separated-values';
	}

	/**
	 * Get the file extension for TSV.
	 *
	 * @return string The file extension.
	 */
	public function get_file_extension(): string {
		return 'tsv';
	}
}
