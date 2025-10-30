<?php
declare( strict_types = 1 );

namespace Automattic\WooCommerce\ProductFeedForOpenAI;

use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionProperty;
use WC_Unit_Test_Case;

/**
 * ProductFeed test case class.
 */
class ProductFeedTestCase extends WC_Unit_Test_Case {
	/**
	 * Extended version of RuntimeContainer, used during tests.
	 *
	 * @var TestContainer
	 */
	protected TestContainer $test_container;

	public function setUp(): void {
		parent::setUp();

		// Initialize the test container if needed.
		$this->test_container = $this->get_replacement_container(
			isset( $this->test_container ) ? $this->test_container : null
		);
	}

	public function tearDown(): void {
		parent::tearDown();

		$this->test_container->reset_all_replacements();
	}

	/**
	 * Creates a mock object.
	 *
	 * This method does not work differently from `createMock`,
	 * but the DocBlock comment indicates a proper return type,
	 * combining `MockObject` and the provided class name.
	 *
	 * @template ID
	 * @param class-string<ID> $original_class_name Name of the class to mock.
	 * @return ID|MockObject
	 */
	// phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found,Squiz.Commenting.FunctionComment.IncorrectTypeHint
	public function createMock( string $original_class_name ): MockObject {
		return parent::createMock( $original_class_name );
	}

	/**
	 * Get the replacement container.
	 *
	 * @param TestContainer|null $current_container The current test container.
	 * @return TestContainer The replacement container.
	 */
	private function get_replacement_container( ?TestContainer $current_container = null ): TestContainer {
		// The same instance will be shared across all tests.
		static $test_container;

		// Just return the test container if it already set.
		if ( isset( $test_container ) && $test_container === $current_container ) {
			return $current_container;
		}

		$plugin_instance = wpfoai_plugin();
		$plugin_property = $this->make_property_accessible( $plugin_instance, 'container' );
		$main_container  = $plugin_property->getValue( $plugin_instance );

		// If the test container is already used in the plugin, don't change anything.
		if ( $main_container === $test_container ) {
			return $test_container;
		}

		// Fetch the `initial_resolved_cache` from the existing `RuntimeContainer`.
		$container_property = $this->make_property_accessible( $main_container, 'container' );
		$runtime_container  = $container_property->getValue( $main_container );
		$cache_property     = $this->make_property_accessible( $runtime_container, 'initial_resolved_cache' );
		$original_cache     = $cache_property->getValue( $runtime_container );

		// Create a test container with the same initial cache.
		$test_container = new TestContainer( $original_cache );
		$plugin_property->setValue( $plugin_instance, $test_container );

		return $test_container;
	}

	/**
	 * Makes a property accessible and returns it.
	 *
	 * @param object $obj           The object to make the property accessible.
	 * @param string $property_name The name of the property to make accessible.
	 * @return ReflectionProperty The property.
	 */
	private function make_property_accessible( object $obj, string $property_name ): ReflectionProperty {
		$reflection = new ReflectionClass( $obj );
		$property   = $reflection->getProperty( $property_name );
		$property->setAccessible( true );
		return $property;
	}
}
