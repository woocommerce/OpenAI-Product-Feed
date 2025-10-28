<?php
declare( strict_types = 1 );

use PHPUnit\Framework\MockObject\MockObject;
use Automattic\WooCommerce\ProductFeedForOpenAI\Core\DependencyManagement\Container;
use Automattic\WooCommerce\ProductFeedForOpenAI\Integrations\OpenAi\ScheduledActionManager;
use Automattic\WooCommerce\ProductFeedForOpenAI\Integrations\OpenAi\Settings;
use Automattic\WooCommerce\ProductFeedForOpenAI\Integrations\OpenAi\OpenAiIntegration;

/**
 * Admin controller test class.
 */
class ScheduledActionManagerTest extends WC_Unit_Test_Case {
	/**
	 * API controller instance.
	 *
	 * @var ScheduledActionManager
	 */
	private ScheduledActionManager $sut;

	/**
	 * Mock settings repository.
	 *
	 * @var Settings|MockObject
	 */
	private $mock_settings;

	/**
	 * Mock logger.
	 *
	 * @var WC_Logger_Interface|MockObject
	 */
	private $mock_logger;

	public function setUp(): void {
		parent::setUp();

		$this->mock_settings = $this->createMock( Settings::class );
		$this->mock_logger   = $this->createMock( WC_Logger_Interface::class );

		$integration = new OpenAiIntegration();
		$integration->init(
			wpfoai_get_service( Container::class ),
			$this->mock_settings
		);

		$this->sut = new ScheduledActionManager();
		$this->sut->init( $integration, $this->mock_logger );
	}

	public function tearDown(): void {
		parent::tearDown();
		remove_all_actions( 'wpfoai_push_file_pre_request' );
	}

	public function test_scheduled_push() {
		$endpoint_url = 'https://example.com/wc/v3/openai-feed';

		$this->mock_settings->expects( $this->atLeast( 1 ) )
			->method( 'get_endpoint_url' )
			->willReturn( $endpoint_url );

		// Add the minimum viable fields for a product to appear in the feed.
		$product = WC_Helper_Product::create_simple_product();
		$product->set_global_unique_id( 1234 );
		$product->update_meta_data( '_gtin', 1234 );
		$product->set_manage_stock( true );
		$product->set_stock_quantity( 10 );
		$product->set_description( 'This is a test' );
		$product->save();

		add_filter(
			'wpfoai_push_file_pre_request',
			function ( $pre, $file_path, $url ) use ( $endpoint_url, $product ) {
				unset( $pre ); // avoid PHPMD UnusedFormalParameter.

				$this->assertEquals( $endpoint_url, $url );

				// PHPCS thinks `file_get_contents` is a remote call.
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				$body = json_decode( file_get_contents( $file_path ), true );
				$this->assertIsArray( $body );
				$this->assertCount( 1, $body );
				$this->assertEquals( $product->get_id(), $body[0]['id'] );

				return [
					'response' => [
						'code' => 200,
					],
				];
			},
			10,
			3
		);

		$this->mock_logger->expects( $this->once() )
			->method( 'info' )
			->with( 'Feed push successful: HTTP 200', [ 'source' => 'wpfoai' ] );

		$this->sut->scheduled_push();
	}
}
