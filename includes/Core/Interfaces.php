<?php

declare(strict_types=1);

namespace OAPFW\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings repository interface
 */
interface SettingsRepositoryInterface {

	public function get( string $key, $default = '' );
	public function set( string $key, $value ): void;
	public function all(): array;
	public function save( array $settings ): bool;
	public function getOptionName(): string;
}

/**
 * Feed generator interface
 */
interface FeedGeneratorInterface {

	public function buildFeed(): array;
	public function buildForProductId( int $product_id ): array;
	public function serialize( array $rows, string $format, ?string &$content_type = null ): string;
}

/**
 * Product mapper interface
 */
interface ProductMapperInterface {

	public function mapProduct( \WC_Product $product, ?\WC_Product $parent = null ): array;
}

/**
 * Feed validator interface
 */
interface ValidatorInterface {

	public function validateRow( array $row ): array;
	public function validateFeed( array $rows ): array;
}

/**
 * Serializer interface
 */
interface SerializerInterface {

	public function serialize( array $data ): string;
	public function getContentType(): string;
	public function getFileExtension(): string;
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
