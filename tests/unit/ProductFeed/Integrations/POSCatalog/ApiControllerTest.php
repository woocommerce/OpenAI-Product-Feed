<?php
declare( strict_types = 1 );

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Integrations\POSCatalog;

use Automattic\WooCommerce\ProductFeedForOpenAI\Core\DependencyManagement\Container;
use Automattic\WooCommerce\ProductFeedForOpenAI\ProductFeedTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use WP_REST_Request;

/**
 * API controller test class.
 */
class ApiControllerTest extends ProductFeedTestCase {
	/**
	 * System under test.
	 *
	 * @var ApiController
	 */
	private ApiController $sut;

	/**
	 * Mock async generator.
	 *
	 * @var MockObject|AsyncGenerator
	 */
	private $mock_async_generator;

	public function setUp(): void {
		parent::setUp();

		$this->mock_async_generator = $this->createMock( AsyncGenerator::class );
		$this->test_container->replace_with_concrete( AsyncGenerator::class, $this->mock_async_generator );

		$this->sut = $this->test_container->get( ApiController::class );
	}

	public function provider_generate_feed(): array {
		return [
			'No force-generation check, no fields'   => [ false, null ],
			'No force-generation check, with fields' => [ false, 'id,name' ],
			'Force generation, with fields'          => [ true, 'id,name' ],
		];
	}

	/**
	 * Test the generate_feed endpoint method.
	 *
	 * @dataProvider provider_generate_feed
	 * @param bool        $force_regeneration Whether to force regeneration of the feed.
	 * @param string|null $fields The fields to include in the feed.
	 */
	public function test_generate_feed( bool $force_regeneration, ?string $fields = null ) {
		$request = new WP_REST_Request( 'POST', '/wc/product-catalog/v1/create' );

		if ( $force_regeneration ) {
			$request->set_param( 'force', true );
		}
		if ( $fields ) {
			$request->set_param( '_product_fields', $fields );
		}

		$this->mock_async_generator->expects( $this->once() )
			->method( $force_regeneration ? 'force_regeneration' : 'get_status' )
			->with( $fields ? [ '_product_fields' => $fields ] : [] )
			->willReturn(
				[
					'action_id' => 6789,
					'path'      => '/tmp/random_path.json',
					'url'       => 'https://example.com/feed.json',
				]
			);

		$response      = $this->sut->generate_feed( $request );
		$response_data = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayNotHasKey( 'action_id', $response_data );
		$this->assertArrayNotHasKey( 'path', $response_data );
		$this->assertArrayHasKey( 'url', $response_data );
		$this->assertEquals( 'https://example.com/feed.json', $response_data['url'] );
	}
}
