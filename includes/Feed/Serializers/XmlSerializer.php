<?php
/**
 *  Xml Serializer class.
 *
 * @package OAPFW
 */

declare(strict_types=1);

namespace OAPFW\Feed\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * XML serializer
 */
class XmlSerializer extends AbstractSerializer {

	/**
	 * Serialize data to XML format.
	 *
	 * @param array $data The data to serialize.
	 * @return string XML formatted string.
	 * @throws \Exception If XML generation fails.
	 */
	public function serialize( array $data ): string {
		try {
			$xml = new \SimpleXMLElement( '<products/>' );

			foreach ( $data as $row ) {
				$item = $xml->addChild( 'product' );
				foreach ( $row as $key => $value ) {
					if ( is_array( $value ) ) {
						$value = implode( ',', $value );
					}

					// Sanitize XML node name (remove invalid characters).
					$safe_key = preg_replace( '/[^a-zA-Z0-9_-]/', '_', $key );

					$item->addChild( $safe_key, htmlspecialchars( (string) $value ) );
				}
			}

			$xml_string = $xml->asXML();

			if ( false === $xml_string ) {
				throw new \Exception( 'Failed to generate XML output' );
			}

			return $xml_string;

		} catch ( \Exception $e ) {
			// Log error and return minimal valid XML.
			if ( function_exists( 'wc_get_logger' ) ) {
				$logger = wc_get_logger();
				$logger->error( 'XML serialization failed: ' . $e->getMessage(), array( 'source' => 'oapfw' ) );
			}

			// Return minimal valid XML structure.
			return '<?xml version="1.0" encoding="UTF-8"?><products><error>XML generation failed</error></products>';
		}
	}

	/**
	 * Get the content type for XML.
	 *
	 * @return string The content type.
	 */
	public function get_content_type(): string {
		return 'application/xml';
	}

	/**
	 * Get the file extension for XML.
	 *
	 * @return string The file extension.
	 */
	public function get_file_extension(): string {
		return 'xml';
	}
}
