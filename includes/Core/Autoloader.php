<?php

declare(strict_types=1);

namespace OAPFW\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PSR-4 Autoloader for OpenAI Product Feed for Woo
 */
class Autoloader {

	private array $prefixes = array();

	/**
	 * Register autoloader
	 */
	public function register(): void {
		spl_autoload_register( array( $this, 'loadClass' ) );
	}

	/**
	 * Add namespace prefix
	 */
	public function addNamespace( string $prefix, string $base_dir ): void {
		$prefix   = trim( $prefix, '\\' ) . '\\';
		$base_dir = rtrim( $base_dir, DIRECTORY_SEPARATOR ) . '/';

		if ( ! isset( $this->prefixes[ $prefix ] ) ) {
			$this->prefixes[ $prefix ] = array();
		}

		array_push( $this->prefixes[ $prefix ], $base_dir );
	}

	/**
	 * Load class file
	 */
	public function loadClass( string $class ): bool {
		$prefix = $class;

		while ( false !== $pos = strrpos( $prefix, '\\' ) ) {
			$prefix         = substr( $class, 0, $pos + 1 );
			$relative_class = substr( $class, $pos + 1 );

			$mapped_file = $this->loadMappedFile( $prefix, $relative_class );
			if ( $mapped_file ) {
				return $mapped_file;
			}

			$prefix = rtrim( $prefix, '\\' );
		}

		return false;
	}

	/**
	 * Load mapped file for namespace prefix and relative class
	 */
	protected function loadMappedFile( string $prefix, string $relative_class ): bool {
		if ( ! isset( $this->prefixes[ $prefix ] ) ) {
			return false;
		}

		foreach ( $this->prefixes[ $prefix ] as $base_dir ) {
			$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

			if ( $this->requireFile( $file ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Require file if it exists
	 */
	protected function requireFile( string $file ): bool {
		if ( file_exists( $file ) ) {
			require $file;
			return true;
		}
		return false;
	}
}
