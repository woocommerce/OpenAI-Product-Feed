<?php
/**
 *  Container class.
 *
 * @package OAPFW
 */

declare(strict_types=1);

namespace OAPFW\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Simple dependency injection container
 */
class Container {

	/**
	 * Service definitions.
	 *
	 * @var array
	 */
	private array $services = array();

	/**
	 * Service instances.
	 *
	 * @var array
	 */
	private array $instances = array();

	/**
	 * Set a service definition.
	 *
	 * @param string $id The service ID.
	 * @param mixed  $definition The service definition.
	 */
	public function set( string $id, $definition ): void {
		$this->services[ $id ] = $definition;

		unset( $this->instances[ $id ] );
	}

	/**
	 * Get a service instance.
	 *
	 * @param string $id The service ID.
	 * @return mixed The service instance.
	 * @throws \InvalidArgumentException If service not found.
	 */
	public function get( string $id ) {
		if ( isset( $this->instances[ $id ] ) ) {
			return $this->instances[ $id ];
		}

		if ( ! isset( $this->services[ $id ] ) ) {
			throw new \InvalidArgumentException( "Service '{$id}' not found." );
		}

		$definition = $this->services[ $id ];

		if ( is_callable( $definition ) ) {
			$instance = $definition();
		} elseif ( is_string( $definition ) && class_exists( $definition ) ) {
			$instance = new $definition();
		} else {
			$instance = $definition;
		}

		$this->instances[ $id ] = $instance;

		return $instance;
	}

	/**
	 * Check if service exists.
	 *
	 * @param string $id The service ID.
	 * @return bool True if service exists.
	 */
	public function has( string $id ): bool {
		return isset( $this->services[ $id ] );
	}

	/**
	 * Remove a service.
	 *
	 * @param string $id The service ID.
	 */
	public function remove( string $id ): void {
		unset( $this->services[ $id ], $this->instances[ $id ] );
	}

	/**
	 * Get all service IDs.
	 *
	 * @return array Array of service IDs.
	 */
	public function get_service_ids(): array {
		return array_keys( $this->services );
	}
}
