<?php

declare(strict_types=1);

namespace OAPFW\Feed\Serializers;

use OAPFW\Core\Interfaces\SerializerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract base serializer
 */
abstract class AbstractSerializer implements SerializerInterface {

	/**
	 * Stringify array values for serialization
	 */
	protected function stringifyValues( array $row ): array {
		foreach ( $row as $key => $value ) {
			if ( is_array( $value ) ) {
				$row[ $key ] = implode( ',', $value );
			}
		}
		return array_values( $row );
	}
}
