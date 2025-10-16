<?php

declare(strict_types=1);

namespace OAPFW\Core\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cache interface
 */
interface CacheInterface {

	public function get( string $key, $default = null );
	public function set( string $key, $value, int $ttl = 0 ): bool;
	public function delete( string $key ): bool;
	public function clear(): bool;
	public function has( string $key ): bool;
}
