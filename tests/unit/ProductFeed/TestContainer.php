<?php

declare( strict_types=1 );

namespace Automattic\WooCommerce\ProductFeedForOpenAI;

use Automattic\WooCommerce\ProductFeedForOpenAI\Core\DependencyManagement\RuntimeContainer;

/**
 * Dependency injection container used during unit tests.
 */
class TestContainer extends RuntimeContainer {
	/**
	 * Replace a class/interface with a concrete (mock) implementation.
	 *
	 * @param string $class_name The class/interface name to replace.
	 * @param object $concrete The concrete (mock) implementation.
	 */
	public function replace_with_concrete( string $class_name, object $concrete ): void {
		$this->resolved_cache[ $class_name ] = $concrete;
	}

	/**
	 * Reset the resolved cache, together with all replacements.
	 */
	public function reset_all_replacements(): void {
		$this->resolved_cache = $this->initial_resolved_cache;
	}
}
