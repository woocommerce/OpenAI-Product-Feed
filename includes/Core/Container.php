<?php
namespace OAPFW\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Simple dependency injection container
 */
class Container {

	private array $services  = array();
	private array $instances = array();

	/**
	 * Set a service definition
	 */
	public function set( string $id, $definition ): void {
		$this->services[ $id ] = $definition;

		// Clear cached instance if it exists
		unset( $this->instances[ $id ] );
	}

	/**
	 * Get a service instance
	 */
	public function get( string $id ) {
		// Return cached instance if available
		if ( isset( $this->instances[ $id ] ) ) {
			return $this->instances[ $id ];
		}

		// Check if service is defined
		if ( ! isset( $this->services[ $id ] ) ) {
			throw new \InvalidArgumentException( "Service '{$id}' not found." );
		}

		$definition = $this->services[ $id ];

		// Create instance
		if ( is_callable( $definition ) ) {
			$instance = $definition();
		} elseif ( is_string( $definition ) && class_exists( $definition ) ) {
			$instance = new $definition();
		} else {
			$instance = $definition;
		}

		// Cache the instance
		$this->instances[ $id ] = $instance;

		return $instance;
	}

	/**
	 * Check if service exists
	 */
	public function has( string $id ): bool {
		return isset( $this->services[ $id ] );
	}

	/**
	 * Remove a service
	 */
	public function remove( string $id ): void {
		unset( $this->services[ $id ], $this->instances[ $id ] );
	}

	/**
	 * Get all service IDs
	 */
	public function getServiceIds(): array {
		return array_keys( $this->services );
	}
}
