<?php

declare(strict_types=1);

namespace OAPFW\Core\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serializer interface
 */
interface SerializerInterface {

	public function serialize( array $data ): string;
	public function getContentType(): string;
	public function getFileExtension(): string;
}
