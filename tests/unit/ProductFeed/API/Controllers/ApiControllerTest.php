<?php
declare( strict_types = 1 );

use Automattic\WooCommerce\ProductFeedForOpenAI\API\Controllers\ApiController;

/**
 * API Controller test class.
 */
class ApiControllerTest extends WC_Unit_Test_Case {
	/**
	 * API controller instance.
	 *
	 * @var ApiController
	 */
	private ApiController $sut;

	public function setUp(): void {
		parent::setUp();
		$this->sut = wpfoai_get_service( ApiController::class );
	}

	public function test_handle_preview_feed() {
		$request = new \WP_REST_Request( 'GET', '/wc/v3/openai-feed' );

		// Add the minimum viable fields for a product to appear in the feed.
		$product = WC_Helper_Product::create_simple_product();
		$product->set_global_unique_id( 1234 );
		$product->update_meta_data( '_gtin', 1234 );
		$product->set_manage_stock( true );
		$product->set_stock_quantity( 10 );
		$product->set_description( 'This is a test' );
		$product->save();

		$request->set_param( 'product_id', $product->get_id() );

		$response = $this->sut->handle_preview_feed( $request );
		$headers  = $response->get_headers();

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'Content-Type', $headers );
		$this->assertEquals( 'application/json; charset=utf-8', $headers['Content-Type'] );
		$this->assertCount( 1, $response->get_data() );

		// We could verify details about the response here, but those will probably change.
	}
}
