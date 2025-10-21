<?php
/**
 * Container class file.
 */

declare( strict_types=1 );

namespace OAPFW\Core\DependencyManagement;

use OAPFW\Core\Interfaces\ProductMapperInterface;
use OAPFW\Platforms\OpenAI\Mappers\ProductMapper;
use OAPFW\Platforms\OpenAI\Mappers\CatalogProductMapper;

/**
 * PSR11 compliant dependency injection container for the plugin.
 *
 * @see Automattic\WooCommerce\Container in Woo core.
 */
final class Container {
	/**
	 * The underlying container.
	 *
	 * @var RuntimeContainer
	 */
	private $container;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		// When the League container was in use we allowed to retrieve the container itself
		// by using 'Psr\Container\ContainerInterface' as the class identifier,
		// we continue allowing that for compatibility.

		// First, create a ProductMapper instance to bind to the interface.
		$temp_container = new RuntimeContainer(
			array(
				__CLASS__                          => $this,
				'Psr\Container\ContainerInterface' => $this,
			)
		);
		// TODO: revert it back to `ProductMapper` for OpenAI implementation.
		$product_mapper = $temp_container->get( CatalogProductMapper::class );

		// Now create the real container with interface binding.
		$this->container = new RuntimeContainer(
			array(
				__CLASS__                          => $this,
				'Psr\Container\ContainerInterface' => $this,
				ProductMapperInterface::class      => $product_mapper,
			)
		);
	}

	/**
	 * Returns an instance of the specified class.
	 * See the comment about ContainerException in RuntimeContainer::get.
	 *
	 * @template T
	 * @param string|class-string<T> $id Class name.
	 *
	 * @return T|object Object instance.
	 *
	 * @throws ContainerException Error when resolving the class to an object instance, or class not found.
	 * @throws \Exception Exception thrown in the constructor or in the 'init' method of one of the resolved classes.
	 */
	public function get( string $id ) {
		return $this->container->get( $id );
	}

	/**
	 * Returns true if the container can return an instance of the given class or false otherwise.
	 * See the comment in RuntimeContainer::has.
	 *
	 * @param class-string $id Class name.
	 *
	 * @return bool
	 */
	public function has( string $id ): bool {
		return $this->container->has( $id );
	}
}
