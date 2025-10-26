<?php
declare( strict_types = 1 );

use PHPUnit\Framework\MockObject\MockObject;
use Automattic\WooCommerce\ProductFeedForOpenAI\Core\DependencyManagement\Container;
use Automattic\WooCommerce\ProductFeedForOpenAI\Integrations\OpenAI\ScheduledActionManager;
use Automattic\WooCommerce\ProductFeedForOpenAI\Integrations\OpenAI\Settings;
use Automattic\WooCommerce\ProductFeedForOpenAI\Integrations\OpenAI\OpenAIIntegration;

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
	 * @var WC_Logger|MockObject
	 */
	private $mock_logger;

	public function setUp(): void {
		parent::setUp();

		$this->mock_settings = $this->createMock( Settings::class );
		$this->mock_logger   = $this->createMock( WC_Logger::class );
		add_filter( 'woocommerce_logging_class', fn() => $this->mock_logger );

		$integration = new OpenAIIntegration();
		$integration->init(
			wpfoai_get_service( Container::class ),
			$this->mock_settings
		);

		$this->sut = new ScheduledActionManager();
		$this->sut->init( $integration );
	}

	public function tearDown(): void {
		parent::tearDown();
		remove_all_actions( 'pre_http_request' );
		remove_all_actions( 'woocommerce_logging_class' );
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
			'pre_http_request',
			function ( $pre, $args, $url ) use ( $endpoint_url, $product ) {
				$this->assertEquals( $endpoint_url, $url );

				$body = json_decode( $args['body'], true );
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
