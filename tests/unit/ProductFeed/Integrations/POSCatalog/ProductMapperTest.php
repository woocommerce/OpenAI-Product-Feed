<?php
declare( strict_types = 1 );

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Integrations\POSCatalog;

use Automattic\WooCommerce\ProductFeedForOpenAI\ProductFeedTestCase;
use WC_Helper_Product;

/**
 * Product mapper test class.
 */
class ProductMapperTest2 extends ProductFeedTestCase {
	/**
	 * System under test.
	 *
	 * @var ProductMapper
	 */
	private ProductMapper $sut;

	public function setUp(): void {
		parent::setUp();

		$this->sut = new ProductMapper();
	}

	public function tearDown(): void {
		parent::tearDown();

		$this->sut->set_fields( null );
	}

	/**
	 * Test mapping a simple product.
	 */
	public function test_map_product_simple_product(): void {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_name( 'Test Product' );
		$product->set_description( 'Test Description' );
		$product->set_regular_price( '99.99' );

		$result = $this->sut->map_product( $product );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'id', $result );
		$this->assertArrayHasKey( 'name', $result );
		$this->assertArrayHasKey( 'description', $result );
		$this->assertArrayHasKey( 'price', $result );
		$this->assertArrayHasKey( 'downloadable', $result );
		$this->assertArrayHasKey( 'parent_id', $result );
		$this->assertArrayHasKey( 'images', $result );
	}

	/**
	 * Test mapping a product with specific fields.
	 */
	public function test_map_product_with_fields(): void {
		$this->sut->set_fields( 'id,name,description' );

		$product = WC_Helper_Product::create_simple_product();
		$product->set_name( 'Test Product' );
		$product->set_description( 'Test Description' );
		$product->set_regular_price( '99.99' );

		$result = $this->sut->map_product( $product );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'id', $result );
		$this->assertArrayHasKey( 'name', $result );
		$this->assertArrayHasKey( 'description', $result );
		$this->assertArrayNotHasKey( 'price', $result );
		$this->assertArrayNotHasKey( 'downloadable', $result );
		$this->assertArrayNotHasKey( 'parent_id', $result );
	}
}
