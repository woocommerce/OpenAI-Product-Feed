<?php
declare( strict_types = 1 );

use OAPFW\API\Controllers\ApiController;
use OAPFW\Feed\FeedGenerator;
use PHPUnit\Framework\MockObject\MockObject;

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

	/**
	 * Mock feed generator instance.
	 *
	 * @var FeedGenerator|MockObject
	 */
	private $mock_feed_generator;

	public function setUp(): void {
		parent::setUp();

		$this->mock_feed_generator = $this->createMock( FeedGenerator::class );

		$this->sut = new ApiController();
		$this->sut->init( $this->mock_feed_generator );
	}

	public function test_handle_preview_feed() {
		$row     = [ 'foo' => 'bar' ];
		$request = new \WP_REST_Request( 'GET', '/wc/v3/openai-feed' );

		$product = WC_Helper_Product::create_simple_product();
		$request->set_param( 'product_id', $product->get_id() );

		$this->mock_feed_generator->expects( $this->once() )
			->method( 'build_for_product_id' )
			->with( $product->get_id() )
			->willReturn( $row );

		$response = $this->sut->handle_preview_feed( $request );
		$headers  = $response->get_headers();

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'Content-Type', $headers );
		$this->assertEquals( 'application/json; charset=utf-8', $headers['Content-Type'] );
		$this->assertEquals( $row, $response->get_data() );
	}
}
