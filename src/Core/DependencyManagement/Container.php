<?php
/**
 * Container class file.
 */

declare( strict_types=1 );

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Core\DependencyManagement;

use WC_Logger_Interface;

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
		$this->container = new RuntimeContainer(
			[
				__CLASS__                          => $this,
				'Psr\Container\ContainerInterface' => $this,
				WC_Logger_Interface::class         => wc_get_logger(),
			]
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
