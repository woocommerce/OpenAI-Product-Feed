<?php
declare( strict_types = 1 );

use Automattic\WooCommerce\ProductFeedForOpenAI\DeliveryMethods\PushFile;
use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\FeedInterface;

/**
 * PushFile delivery method test class.
 */
class PushFileTest extends WC_Unit_Test_Case {
	/**
	 * The system under test.
	 *
	 * @var PushFile
	 */
	private PushFile $sut;

	public function setUp(): void {
		parent::setUp();

		$this->sut = new PushFile( 'https://example.com/wc/v3/openai-feed' );
	}

	public function provider_check_setup() {
		return [
			'valid_url'    => [
				'endpoint_url' => 'https://example.com/wc/v3/openai-feed',
				'expectation'  => true,
			],
			'local_url'    => [
				'endpoint_url' => 'http ://192.168.1.3/wc/v3/openai-feed',
				'expectation'  => false,
			],
			'invalid_port' => [
				'endpoint_url' => 'https://example.com: 333/wc/v3/openai-feed',
				'expectation'  => false,
			],
			'invalid_url'  => [
				'endpoint_url' => 'invalid-url',
				'expectation'  => false,
			],
			'empty_url'    => [
				'endpoint_url' => '',
				'expectation'  => false,
			],
		];
	}

	/**
	 * Test the check_setup method.
	 *
	 * @param string $endpoint_url The endpoint URL to test.
	 * @param bool   $expectation The expected result.
	 * @dataProvider provider_check_setup
	 */
	public function test_check_setup( $endpoint_url, $expectation ) {
		$delivery_method = new PushFile( $endpoint_url );
		$this->assertEquals( $expectation, $delivery_method->check_setup() );
	}

	public function test_deliver_defers_to_pre_request_filter() {
		$callback = function ( $pre, $path, $endpoint ) {
			$this->assertNull( $pre );
			$this->assertEquals( 'https://example.com/wc/v3/openai-feed', $endpoint );
			$this->assertEquals( '/random/missing/file.json', $path );

			return [
				'response' => [
					'code' => 207,
				],
			];
		};

		$mock_feed = $this->createMock( FeedInterface::class );
		$mock_feed->expects( $this->once() )
			->method( 'get_file_path' )
			->willReturn( '/random/missing/file.json' ); // PushFile accepts non-JSON.

		add_filter( 'wpfoai_push_file_pre_request', $callback, 10, 3 );
		$response = $this->sut->deliver( $mock_feed );
		remove_filter( 'wpfoai_push_file_pre_request', $callback, 10 );

		$this->assertEquals( 207, $response['response']['code'] );
	}

	public function test_deliver_throws_exception_if_file_does_not_exist() {
		$mock_feed = $this->createMock( FeedInterface::class );
		$mock_feed->expects( $this->once() )
			->method( 'get_file_path' )
			->willReturn( '/random/missing/file.json' ); // PushFile accepts non-JSON.

		$this->expectException( RuntimeException::class );
		$this->sut->deliver( $mock_feed );
	}

	public function test_deliver_throws_exception_if_file_cannot_be_opened() {
		$mock_feed = $this->createMock( FeedInterface::class );
		$mock_feed->expects( $this->once() )
			->method( 'get_file_path' )
			->willReturn( __DIR__ . '/no-access.json' );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessageMatches( '/unable to open feed file for reading/' );
		$this->sut->deliver( $mock_feed );
	}

	public function test_deliver_throws_exception_if_file_cannot_be_determined() {
		$mock_feed = $this->createMock( FeedInterface::class );
		$mock_feed->expects( $this->once() )
			->method( 'get_file_path' )
			->willReturn( '/random/missing/file.json' ); // PushFile accepts non-JSON.

		$this->expectException( RuntimeException::class );
		$this->sut->deliver( $mock_feed );
	}
}
