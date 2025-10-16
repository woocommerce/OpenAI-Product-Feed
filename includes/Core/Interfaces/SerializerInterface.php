<?php
/**
 *  Serializer Interface interface.
 *
 * @package OAPFW
 */

declare(strict_types=1);

namespace OAPFW\Core\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serializer interface
 */
interface SerializerInterface {

	/**
	 * Serialize data to string format.
	 *
	 * @param array $data The data to serialize.
	 * @return string Serialized data.
	 */
	public function serialize( array $data ): string;

	/**
	 * Get the content type for this serializer.
	 *
	 * @return string The content type.
	 */
	public function get_content_type(): string;

	/**
	 * Get the file extension for this serializer.
	 *
	 * @return string The file extension.
	 */
	public function get_file_extension(): string;
}
