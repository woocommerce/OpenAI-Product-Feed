<?php
declare( strict_types = 1 );

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Integrations\OpenAi;

use PHPUnit\Framework\MockObject\MockObject;

/**
 * Admin controller test class.
 */
class ScheduledActionManagerTest extends \WC_Unit_Test_Case {
	/**
	 * API controller instance.
	 *
	 * @var ScheduledActionManager
	 */
	private ScheduledActionManager $sut;

	/**
	 * Mock logger.
	 *
	 * @var \WC_Logger_Interface|MockObject
	 */
	private $mock_logger;

	public function setUp(): void {
		parent::setUp();

		$this->mock_logger = $this->createMock( \WC_Logger_Interface::class );

		$this->sut = new ScheduledActionManager();
		$this->sut->init( wpfoai_get_service( OpenAiIntegration::class ), $this->mock_logger );
	}

	public function tearDown(): void {
		parent::tearDown();
		remove_all_filters( 'wpfoai_push_file_pre_request' );
		remove_all_filters( 'woocommerce_terms_and_conditions_page_id' );
	}

	public function test_scheduled_push() {
		$endpoint_url = 'https://example.com/wc/v3/openai-feed';

		// Set the expected settings.
		$settings                            = get_option( 'woocommerce_agentic_agent_registry', [] );
		$settings['openai']['feed_url']      = $endpoint_url;
		$settings['openai']['return_window'] = 3;
		update_option( 'woocommerce_agentic_agent_registry', $settings );

		// Add an image that will be used for the product.
		$image_id = wp_insert_attachment(
			array(
				'post_title'     => 'Main Product Image',
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/jpeg',
			)
		);

		// Add the minimum viable fields for a product to appear in the feed.
		$product = \WC_Helper_Product::create_simple_product();
		$product->set_global_unique_id( 1234 );
		$product->update_meta_data( '_gtin', 1234 );
		$product->update_meta_data( '_wpfoai_disable_search', 'no' );
		$product->update_meta_data( '_wpfoai_disable_checkout', 'no' );
		$product->set_manage_stock( true );
		$product->set_stock_quantity( 10 );
		$product->set_description( 'This is a test' );
		$product->set_image_id( $image_id );
		$product->save();

		// Use the product itself as the T&C page ID.
		add_filter( 'woocommerce_terms_and_conditions_page_id', fn() => $product->get_id() );

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
