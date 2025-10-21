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
		$request = new \WP_REST_Request( 'GET', '/wc/v3/openai-feed' );
		$request->set_param( 'product_id', 1 );

		$response = $this->sut->handle_preview_feed( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'application/json; charset=utf-8', $response->get_header( 'Content-Type' ) );
		$this->assertEquals( wp_json_encode( $this->mock_feed_generator->build_for_product_id( 1 ) ), $response->get_data() );
	}
}
