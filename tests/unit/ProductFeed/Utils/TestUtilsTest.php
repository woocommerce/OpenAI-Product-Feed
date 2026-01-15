<?php
declare( strict_types = 1 );

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Utils;

use Automattic\WooCommerce\ProductFeedForOpenAI\Core\DependencyManagement\ContainerException;
use Automattic\WooCommerce\Internal\ProductFeed\Integrations\IntegrationInterface;
use Automattic\WooCommerce\Internal\ProductFeed\Utils\StringHelper;
use Automattic\WooCommerce\ProductFeedForOpenAI\Integrations\OpenAi\OpenAiIntegration;
use Automattic\WooCommerce\ProductFeedForOpenAI\ProductFeedTestCase;

/**
 * Test class for unit test utilities.
 */
class TestUtilsTest extends ProductFeedTestCase {
	public function test_container_replacement() {
		// Prepare a new class that will be used as a replacement.
		$replacement_helper = ( new class() extends StringHelper {
			public function dynamic_bool_string( $value ): string {
				return StringHelper::bool_string( $value );
			}
		} );

		// Load a normal class from the container.
		$string_helper = $this->test_container->get( StringHelper::class );
		$this->assertInstanceOf( StringHelper::class, $string_helper );

		// Replace a class in the container with a specific instance.
		$this->test_container->replace_with_concrete( StringHelper::class, $replacement_helper );
		$replaced = wpfoai_get_service( StringHelper::class );
		$this->assertEquals( $replacement_helper, $replaced );

		// Set the particular implementation of an interface.
		$integration = wpfoai_get_service( OpenAiIntegration::class );
		$this->test_container->replace_with_concrete( IntegrationInterface::class, $integration );
		$interface_instance = wpfoai_get_service( IntegrationInterface::class );
		$this->assertInstanceOf( OpenAiIntegration::class, $interface_instance );

		// For unit tests, test replacement with a mock.
		$mock_integration = $this->createMock( IntegrationInterface::class );
		$mock_integration->expects( $this->once() )
			->method( 'get_id' )
			->willReturn( 'openai' );
		$this->test_container->replace_with_concrete( IntegrationInterface::class, $mock_integration );
		$this->assertSame( $mock_integration, wpfoai_get_service( IntegrationInterface::class ) );
		$this->assertEquals( 'openai', wpfoai_get_service( IntegrationInterface::class )->get_id() );

		// Reset all replacements.
		$this->test_container->reset_all_replacements();

		// Expect an exception, as the interface does not have a specified implementation.
		$this->expectException( ContainerException::class );
		$integration = wpfoai_get_service( IntegrationInterface::class );
	}
}
