<?php

declare(strict_types=1);

namespace OAPFW\Core\Interfaces;

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
