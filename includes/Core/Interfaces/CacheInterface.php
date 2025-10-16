<?php
/**
 *  Cache Interface interface.
 *
 * @package OAPFW
 */

declare(strict_types=1);

namespace OAPFW\Core\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cache interface
 */
interface CacheInterface {

	/**
	 * Get a value from cache.
	 *
	 * @param string $key The cache key.
	 * @param mixed  $default_value Default value if key not found.
	 * @return mixed The cached value or default.
	 */
	public function get( string $key, $default_value = null );

	/**
	 * Set a value in cache.
	 *
	 * @param string $key The cache key.
	 * @param mixed  $value The value to cache.
	 * @param int    $ttl Time to live in seconds.
	 * @return bool True on success.
	 */
	public function set( string $key, $value, int $ttl = 0 ): bool;

	/**
	 * Delete a value from cache.
	 *
	 * @param string $key The cache key.
	 * @return bool True on success.
	 */
	public function delete( string $key ): bool;

	/**
	 * Clear all cache.
	 *
	 * @return bool True on success.
	 */
	public function clear(): bool;

	/**
	 * Check if key exists in cache.
	 *
	 * @param string $key The cache key.
	 * @return bool True if key exists.
	 */
	public function has( string $key ): bool;
}
