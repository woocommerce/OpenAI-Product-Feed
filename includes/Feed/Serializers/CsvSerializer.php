<?php

declare(strict_types=1);

namespace OAPFW\Feed\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CSV serializer
 */
class CsvSerializer extends AbstractSerializer {

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

	public function getContentType(): string {
		return 'text/csv';
	}

	public function getFileExtension(): string {
		return 'csv';
	}
}
