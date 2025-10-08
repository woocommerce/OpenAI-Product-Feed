<?php

declare(strict_types=1);

namespace OAPFW\Feed\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * XML serializer
 */
class XmlSerializer extends AbstractSerializer {

	public function serialize( array $data ): string {
		$xml = new \SimpleXMLElement( '<products/>' );

		foreach ( $data as $row ) {
			$item = $xml->addChild( 'product' );
			foreach ( $row as $key => $value ) {
				if ( is_array( $value ) ) {
					$value = implode( ',', $value );
				}
				$item->addChild( $key, htmlspecialchars( (string) $value ) );
			}
		}

		return $xml->asXML();
	}

	public function getContentType(): string {
		return 'application/xml';
	}

	public function getFileExtension(): string {
		return 'xml';
	}
}
