<?php
declare( strict_types = 1 );

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Integrations\POSCatalog;

use PHPUnit\Framework\MockObject\MockObject;
use Automattic\WooCommerce\ProductFeedForOpenAI\ProductFeedTestCase;
use WC_Helper_Product;

/**
 * Async generator test class.
 */
class AsyncGeneratorTest extends ProductFeedTestCase {
	/**
	 * System under test.
	 *
	 * @var AsyncGenerator
	 */
	private AsyncGenerator $sut;

	/**
	 * Mock integration.
	 *
	 * @var MockObject|POSIntegration
	 */
	private $mock_integration;

	// Option key for tests.
	private const OPTION_KEY = 'product_feed_async_test';

	public function setUp(): void {
		parent::setUp();

		$this->mock_integration = $this->createMock( POSIntegration::class );
		$this->test_container->replace_with_concrete( POSIntegration::class, $this->mock_integration );

		$this->sut = $this->test_container->get( AsyncGenerator::class );
	}

	public function tearDown(): void {
		parent::tearDown();

		delete_option( self::OPTION_KEY );
	}

	public function test_feed_generation_action_forwards_args() {
		// Make sure at least one product is present. We will not check it here.
		WC_Helper_Product::create_simple_product();

		// Set the initial option to indicate scheduled state.
		$status = [
			'state' => AsyncGenerator::STATE_SCHEDULED,
			'args'  => [
				'_fields' => 'id,name',
			]
		];
		update_option( self::OPTION_KEY, $status );

		// Expect the mapper to be called with the fields.
		$mock_mapper = $this->createMock( ProductMapper::class );
		$mock_mapper->expects( $this->once() )
			->method( 'set_fields' )
			->with( 'id,name' );
		$mock_mapper->expects( $this->atLeast( 1 ) )
			->method( 'map_product' )
			->willReturn( [] );

		// Replace the mapper with the integration.
		$this->mock_integration->expects( $this->exactly( 2 ) )
			->method( 'get_product_mapper' )
			->willReturn( $mock_mapper );

		// Trigger the action.
		$this->sut->feed_generation_action( self::OPTION_KEY );

		// Check the final status.
		$updated_status = get_option( self::OPTION_KEY );
		$this->assertEquals( AsyncGenerator::STATE_COMPLETED, $updated_status['state'] );
	}
}
