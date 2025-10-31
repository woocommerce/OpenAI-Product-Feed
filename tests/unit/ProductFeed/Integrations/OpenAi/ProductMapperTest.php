<?php
declare( strict_types = 1 );

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Integrations\OpenAi;

use PHPUnit\Framework\MockObject\MockObject;
use Automattic\WooCommerce\Enums\ProductType;
use WC_Helper_Product;

/**
 * ProductMapper test class.
 *
 * Tests mapping of WooCommerce products to OpenAI feed format.
 */
class ProductMapperTest extends \WC_Unit_Test_Case {
	/**
	 * System under test.
	 *
	 * @var ProductMapper
	 */
	private ProductMapper $sut;

	/**
	 * Mock settings repository.
	 *
	 * @var Settings|MockObject
	 */
	private $mock_settings;

	public function setUp(): void {
		parent::setUp();

		$this->mock_settings = $this->createMock( Settings::class );
		$this->sut           = new ProductMapper();
		$this->sut->init( $this->mock_settings );

		// Set up default settings mock behavior.
		$this->mock_settings->method( 'get' )
			->willReturnCallback(
				function ( $key, $default_value = '' ) {
					$defaults = [
						'enable_products_default' => 'true',
						'seller_name'             => 'Test Store',
						'seller_url'              => 'https://teststore.com',
					];
					return $defaults[ $key ] ?? $default_value;
				}
			);
	}

	/**
	 * Test mapping a simple product
	 */
	public function test_map_product_simple_product(): void {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_name( 'Test Product' );
		$product->set_description( 'Test Description' );
		$product->set_regular_price( '99.99' );
		$product->set_stock_status( 'instock' );
		$product->set_stock_quantity( 10 );
		$product->save();

		$result = $this->sut->map_product( $product );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'id', $result );
		$this->assertArrayHasKey( 'title', $result );
		$this->assertArrayHasKey( 'description', $result );
		$this->assertArrayHasKey( 'link', $result );
		$this->assertArrayHasKey( 'enable_search', $result );
		$this->assertArrayHasKey( 'enable_checkout', $result );

		$this->assertEquals( (string) $product->get_id(), $result['id'] );
		$this->assertEquals( 'Test Product', $result['title'] );
		$this->assertEquals( 'Test Description', $result['description'] );
		$this->assertEquals( 'true', $result['enable_search'] );

		$product->delete( true );
	}

	/**
	 * Test mapping a variable product (parent)
	 */
	public function test_map_product_variable_product(): void {
		$product = WC_Helper_Product::create_variation_product();
		$product->set_name( 'Variable Product' );
		$product->set_description( 'Variable Description' );
		$product->save();

		$result = $this->sut->map_product( $product );

		$this->assertIsArray( $result );
		$this->assertEquals( (string) $product->get_id(), $result['id'] );
		$this->assertEquals( 'Variable Product', $result['title'] );

		$product->delete( true );
	}

	/**
	 * Test mapping a product variation with parent
	 */
	public function test_map_product_variation_with_parent(): void {
		$variable_product = WC_Helper_Product::create_variation_product();
		$variable_product->set_name( 'Parent Product' );
		$variable_product->save();

		$variations = $variable_product->get_children();
		$this->assertNotEmpty( $variations, 'Variable product should have variations' );

		$variation = wc_get_product( $variations[0] );
		$this->assertNotNull( $variation, 'Variation should exist' );
		$this->assertEquals( ProductType::VARIATION, $variation->get_type() );

		$result = $this->sut->map_product( $variation );

		$this->assertIsArray( $result );
		$this->assertEquals( (string) $variation->get_id(), $result['id'] );

		// For variations, item_group_id should be the parent ID.
		if ( isset( $result['item_group_id'] ) ) {
			$this->assertEquals( (string) $variable_product->get_id(), $result['item_group_id'] );
		}

		$variable_product->delete( true );
	}

	/**
	 * Test map_product throws exception when parent product not found for variation
	 */
	public function test_map_product_variation_missing_parent_throws_exception(): void {
		$mock_variation = $this->createMock( \WC_Product_Variation::class );
		$mock_variation->method( 'get_type' )->willReturn( ProductType::VARIATION );
		$mock_variation->method( 'get_parent_id' )->willReturn( 999999 ); // Non-existent parent.
		$mock_variation->method( 'get_id' )->willReturn( 123 );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Parent product not found for variation' );

		$this->sut->map_product( $mock_variation );
	}

	/**
	 * Test mapped product contains basic required fields
	 */
	public function test_map_product_contains_required_fields(): void {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_name( 'Required Fields Test' );
		$product->set_description( 'Description for required fields test' );
		$product->set_stock_status( 'instock' );
		$product->save();

		$result = $this->sut->map_product( $product );

		// Check for required fields according to OpenAI spec.
		$required_fields = [ 'id', 'title', 'description', 'link', 'enable_search', 'enable_checkout' ];

		foreach ( $required_fields as $field ) {
			$this->assertArrayHasKey( $field, $result, "Result should contain required field: {$field}" );
			$this->assertNotEmpty( $result[ $field ], "Required field '{$field}' should not be empty" );
		}

		$product->delete( true );
	}

	/**
	 * Test field mapping with defaults from schema
	 */
	public function test_map_product_uses_default_values(): void {
		$product = WC_Helper_Product::create_simple_product();
		$product->save();

		$result = $this->sut->map_product( $product );

		// Check default values are applied.
		$this->assertArrayHasKey( 'enable_search', $result );
		$this->assertContains( $result['enable_search'], [ 'true', 'false' ] );

		$this->assertArrayHasKey( 'enable_checkout', $result );
		$this->assertContains( $result['enable_checkout'], [ 'true', 'false' ] );

		$product->delete( true );
	}

	/**
	 * Test type conversion for integer fields
	 */
	public function test_map_product_converts_integer_types(): void {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_stock_quantity( 42 );
		$product->set_manage_stock( true );
		$product->save();

		$result = $this->sut->map_product( $product );

		if ( isset( $result['inventory_quantity'] ) ) {
			$this->assertIsInt( $result['inventory_quantity'], 'inventory_quantity should be an integer' );
			$this->assertEquals( 42, $result['inventory_quantity'] );
		}

		$product->delete( true );
	}

	/**
	 * Test type conversion for string fields with max length
	 */
	public function test_map_product_truncates_long_strings(): void {
		$long_title = str_repeat( 'A', 200 ); // Exceeds max_length of 150.

		$product = WC_Helper_Product::create_simple_product();
		$product->set_name( $long_title );
		$product->save();

		$result = $this->sut->map_product( $product );

		$this->assertArrayHasKey( 'title', $result );
		$this->assertLessThanOrEqual( 150, strlen( $result['title'] ), 'Title should be truncated to max_length' );

		$product->delete( true );
	}

	/**
	 * Test row cleaning removes null and empty values
	 */
	public function test_map_product_cleans_null_and_empty_values(): void {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_name( 'Clean Test' );
		$product->set_description( 'Clean Description' );
		// Don't set optional fields like gtin, mpn, etc...
		$product->save();

		$result = $this->sut->map_product( $product );

		// Check that null/empty optional fields are removed.
		foreach ( $result as $key => $value ) {
			$this->assertNotNull( $value, "Field '{$key}' should not be null in cleaned result" );
			$this->assertNotEquals( '', $value, "Field '{$key}' should not be empty string in cleaned result" );
		}

		$product->delete( true );
	}

	/**
	 * Test wpfoai_map_product filter is applied
	 */
	public function test_map_product_applies_filter(): void {
		$product = WC_Helper_Product::create_simple_product();
		$product->save();

		$filter_applied  = false;
		$filter_callback = function ( $row ) use ( &$filter_applied ) {
			$filter_applied      = true;
			$row['custom_field'] = 'custom_value';
			return $row;
		};

		add_filter( 'wpfoai_map_product', $filter_callback, 10, 3 );

		$result = $this->sut->map_product( $product );

		$this->assertTrue( $filter_applied, 'wpfoai_map_product filter should be applied' );
		$this->assertArrayHasKey( 'custom_field', $result );
		$this->assertEquals( 'custom_value', $result['custom_field'] );

		remove_filter( 'wpfoai_map_product', $filter_callback, 10 );

		$product->delete( true );
	}

	/**
	 * Test enable_search mapping with product meta override
	 */
	public function test_map_product_enable_search_with_override(): void {
		$product = WC_Helper_Product::create_simple_product();
		$product->update_meta_data( ProductFieldsController::KEY_DISABLE_SEARCH, 'yes' );
		$product->save();

		$result = $this->sut->map_product( $product );

		$this->assertArrayHasKey( 'enable_search', $result );
		$this->assertEquals( 'false', $result['enable_search'], 'enable_search should be false when disabled via meta' );

		$product->delete( true );
	}

	/**
	 * Test enable_checkout mapping with product meta override
	 */
	public function test_map_product_enable_checkout_with_override(): void {
		$product = WC_Helper_Product::create_simple_product();
		$product->update_meta_data( ProductFieldsController::KEY_DISABLE_CHECKOUT, 'yes' );
		$product->save();

		$result = $this->sut->map_product( $product );

		$this->assertArrayHasKey( 'enable_checkout', $result );
		$this->assertEquals( 'false', $result['enable_checkout'], 'enable_checkout should be false when disabled via meta' );

		$product->delete( true );
	}

	/**
	 * Test enable_search for variation uses parent product meta
	 */
	public function test_map_product_enable_search_variation_uses_parent_meta(): void {
		$variable_product = WC_Helper_Product::create_variation_product();
		$variable_product->update_meta_data( ProductFieldsController::KEY_DISABLE_SEARCH, 'yes' );
		$variable_product->save();

		$variations = $variable_product->get_children();
		$this->assertNotEmpty( $variations );

		$variation = wc_get_product( $variations[0] );
		$this->assertNotNull( $variation );

		$result = $this->sut->map_product( $variation );

		$this->assertArrayHasKey( 'enable_search', $result );
		// Should use parent's disable setting.
		$this->assertEquals( 'false', $result['enable_search'] );

		$variable_product->delete( true );
	}

	/**
	 * Test enable_checkout for variation uses parent product meta
	 */
	public function test_map_product_enable_checkout_variation_uses_parent_meta(): void {
		$variable_product = WC_Helper_Product::create_variation_product();
		$variable_product->update_meta_data( ProductFieldsController::KEY_DISABLE_CHECKOUT, 'yes' );
		$variable_product->save();

		$variations = $variable_product->get_children();
		$this->assertNotEmpty( $variations );

		$variation = wc_get_product( $variations[0] );
		$this->assertNotNull( $variation );

		$result = $this->sut->map_product( $variation );

		$this->assertArrayHasKey( 'enable_checkout', $result );
		// Should use parent's disable setting.
		$this->assertEquals( 'false', $result['enable_checkout'] );

		$variable_product->delete( true );
	}

	/**
	 * Test price formatting with currency
	 */
	public function test_map_product_price_includes_currency(): void {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_regular_price( '49.99' );
		$product->save();

		$result = $this->sut->map_product( $product );

		if ( isset( $result['price'] ) ) {
			$this->assertStringContainsString( '49.99', $result['price'], 'Price should contain the amount' );
			// Price should include currency code (e.g., "49.99 USD").
			$this->assertMatchesRegularExpression( '/\d+(\.\d+)?\s+[A-Z]{3}/', $result['price'], 'Price should include currency code' );
		}

		$product->delete( true );
	}

	/**
	 * Test sale price mapping
	 */
	public function test_map_product_sale_price(): void {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_regular_price( '99.99' );
		$product->set_sale_price( '79.99' );
		$product->save();

		$result = $this->sut->map_product( $product );

		if ( isset( $result['sale_price'] ) ) {
			$this->assertStringContainsString( '79.99', $result['sale_price'], 'Sale price should contain the amount' );
		}

		$product->delete( true );
	}

	/**
	 * Test availability mapping for in stock product
	 */
	public function test_map_product_availability_in_stock(): void {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_stock_status( 'instock' );
		$product->save();

		$result = $this->sut->map_product( $product );

		$this->assertArrayHasKey( 'availability', $result );
		$this->assertEquals( 'in_stock', $result['availability'] );

		$product->delete( true );
	}

	/**
	 * Test availability mapping for out of stock product
	 */
	public function test_map_product_availability_out_of_stock(): void {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_stock_status( 'outofstock' );
		$product->save();

		$result = $this->sut->map_product( $product );

		$this->assertArrayHasKey( 'availability', $result );
		$this->assertEquals( 'out_of_stock', $result['availability'] );

		$product->delete( true );
	}

	/**
	 * Test availability mapping for backorder product
	 */
	public function test_map_product_availability_backorder(): void {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_stock_status( 'onbackorder' );
		$product->save();

		$result = $this->sut->map_product( $product );

		$this->assertArrayHasKey( 'availability', $result );
		$this->assertEquals( 'preorder', $result['availability'] );

		$product->delete( true );
	}

	/**
	 * Test inventory quantity for product with stock management
	 */
	public function test_map_product_inventory_quantity_with_stock_management(): void {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_manage_stock( true );
		$product->set_stock_quantity( 25 );
		$product->save();

		$result = $this->sut->map_product( $product );

		$this->assertArrayHasKey( 'inventory_quantity', $result );
		$this->assertEquals( 25, $result['inventory_quantity'] );

		$product->delete( true );
	}

	/**
	 * Test inventory quantity for product without stock management (in stock)
	 */
	public function test_map_product_inventory_quantity_without_stock_management_in_stock(): void {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_manage_stock( false );
		$product->set_stock_status( 'instock' );
		$product->save();

		$result = $this->sut->map_product( $product );

		$this->assertArrayHasKey( 'inventory_quantity', $result );
		$this->assertGreaterThanOrEqual( 1, $result['inventory_quantity'] );

		$product->delete( true );
	}

	/**
	 * Test inventory quantity for product without stock management (out of stock)
	 */
	public function test_map_product_inventory_quantity_without_stock_management_out_of_stock(): void {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_manage_stock( false );
		$product->set_stock_status( 'outofstock' );
		$product->save();

		$result = $this->sut->map_product( $product );

		$this->assertArrayHasKey( 'inventory_quantity', $result );
		$this->assertEquals( 0, $result['inventory_quantity'] );

		$product->delete( true );
	}

	/**
	 * Test brand mapping with attribute
	 */
	public function test_map_product_brand_from_attribute(): void {
		$product   = WC_Helper_Product::create_simple_product();
		$attribute = WC_Helper_Product::create_product_attribute_object( 'brand', [ 'TestBrand' ] );
		$product->set_attributes( [ $attribute ] );
		$product->save();

		$result = $this->sut->map_product( $product );

		// Brand should either be set from attribute or default to 'Generic'.
		$this->assertArrayHasKey( 'brand', $result );
		$this->assertIsString( $result['brand'] );
		$this->assertEquals( 'TestBrand', $result['brand'] );

		$product->delete( true );
	}

	/**
	 * Test brand defaults to 'Generic' when not set
	 */
	public function test_map_product_brand_defaults_to_generic(): void {
		$product = WC_Helper_Product::create_simple_product();
		$product->save();

		$result = $this->sut->map_product( $product );

		$this->assertArrayHasKey( 'brand', $result );
		$this->assertEquals( 'Generic', $result['brand'] );

		$product->delete( true );
	}

	/**
	 * Test condition mapping with meta
	 */
	public function test_map_product_condition_from_meta(): void {
		$product = WC_Helper_Product::create_simple_product();
		$product->update_meta_data( ProductFieldsController::KEY_CONDITION, 'refurbished' );
		$product->save();

		$result = $this->sut->map_product( $product );

		$this->assertArrayHasKey( 'condition', $result );
		$this->assertEquals( 'refurbished', $result['condition'] );

		$product->delete( true );
	}

	/**
	 * Test condition defaults to 'new' when not set
	 */
	public function test_map_product_condition_defaults_to_new(): void {
		$product = WC_Helper_Product::create_simple_product();
		// Don't set condition meta.
		$product->save();

		$result = $this->sut->map_product( $product );

		$this->assertArrayHasKey( 'condition', $result );
		$this->assertEquals( 'new', $result['condition'] );

		$product->delete( true );
	}

	/**
	 * Test MPN generation when GTIN is not present
	 */
	public function test_map_product_mpn_generated_without_gtin(): void {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_name( 'MPN Test Product' );
		// Don't set GTIN.
		$product->save();

		$result = $this->sut->map_product( $product );

		// When GTIN is not present, MPN should be generated.
		if ( isset( $result['mpn'] ) ) {
			$this->assertStringStartsWith( 'MPN-', $result['mpn'], 'Generated MPN should start with MPN-' );
		}

		$product->delete( true );
	}

	/**
	 * Test product link is a valid URL
	 */
	public function test_map_product_link_is_valid_url(): void {
		$product = WC_Helper_Product::create_simple_product();
		$product->save();

		$result = $this->sut->map_product( $product );

		$this->assertArrayHasKey( 'link', $result );
		$this->assertNotEmpty( $result['link'] );
		$this->assertMatchesRegularExpression( '/^https?:\/\//', $result['link'], 'Link should be a valid URL' );

		$product->delete( true );
	}

	/**
	 * Test title has HTML tags stripped
	 */
	public function test_map_product_title_strips_html_tags(): void {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_name( '<strong>HTML</strong> <em>Title</em>' );
		$product->save();

		$result = $this->sut->map_product( $product );

		$this->assertArrayHasKey( 'title', $result );
		$this->assertEquals( 'HTML Title', $result['title'] );
		$this->assertStringNotContainsString( '<strong>', $result['title'] );
		$this->assertStringNotContainsString( '</strong>', $result['title'] );

		$product->delete( true );
	}

	/**
	 * Test description has HTML tags stripped
	 */
	public function test_map_product_description_strips_html_tags(): void {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_description( '<p>HTML <strong>Description</strong></p>' );
		$product->save();

		$result = $this->sut->map_product( $product );

		$this->assertArrayHasKey( 'description', $result );
		$this->assertStringNotContainsString( '<p>', $result['description'] );
		$this->assertStringNotContainsString( '<strong>', $result['description'] );

		$product->delete( true );
	}

	/**
	 * Test description falls back to short description
	 */
	public function test_map_product_description_fallback_to_short_description(): void {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_description( '' ); // Empty long description.
		$product->set_short_description( 'This is a short description' );
		$product->save();

		$result = $this->sut->map_product( $product );

		$this->assertArrayHasKey( 'description', $result );
		$this->assertEquals( 'This is a short description', $result['description'] );

		$product->delete( true );
	}

	/**
	 * Test seller information is included from settings
	 */
	public function test_map_product_includes_seller_information(): void {
		$mock_settings = $this->createMock( Settings::class );
		$mock_settings->method( 'get' )
			->willReturnCallback(
				function ( $key, $default_value = '' ) {
					$values = [
						'seller_name'             => 'My Test Store',
						'seller_url'              => 'https://myteststore.com',
						'enable_products_default' => 'true',
					];
					return $values[ $key ] ?? $default_value;
				}
			);

		$mapper = new ProductMapper();
		$mapper->init( $mock_settings );

		$product = WC_Helper_Product::create_simple_product();
		$product->save();

		$result = $mapper->map_product( $product );

		// Check if seller information is included (if mapped).
		if ( isset( $result['seller_name'] ) ) {
			$this->assertEquals( 'My Test Store', $result['seller_name'] );
		}

		if ( isset( $result['seller_url'] ) ) {
			$this->assertEquals( 'https://myteststore.com', $result['seller_url'] );
		}

		$product->delete( true );
	}

	/**
	 * Test sale_price_effective_date with both dates set
	 */
	public function test_map_product_sale_price_effective_date_with_both_dates(): void {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_regular_price( '99.99' );
		$product->set_sale_price( '79.99' );

		$sale_from = new \WC_DateTime( '2025-11-01' );
		$sale_to   = new \WC_DateTime( '2025-11-30' );

		$product->set_date_on_sale_from( $sale_from );
		$product->set_date_on_sale_to( $sale_to );
		$product->save();

		$result = $this->sut->map_product( $product );

		$this->assertArrayHasKey( 'sale_price_effective_date', $result );
		$this->assertEquals( '2025-11-01 / 2025-11-30', $result['sale_price_effective_date'] );

		$product->delete( true );
	}

	/**
	 * Test sale_price_effective_date with only sale_from date (verifies format)
	 */
	public function test_map_product_sale_price_effective_date_with_only_start_date(): void {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_regular_price( '99.99' );
		$product->set_sale_price( '79.99' );

		$sale_from = new \WC_DateTime( '2025-11-01' );
		$product->set_date_on_sale_from( $sale_from );
		$product->save();

		$result = $this->sut->map_product( $product );

		$this->assertArrayHasKey( 'sale_price_effective_date', $result );
		$this->assertStringStartsWith( '2025-11-01 / ', $result['sale_price_effective_date'] );

		// Verify format is correct (YYYY-MM-DD / YYYY-MM-DD).
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2} \/ \d{4}-\d{2}-\d{2}$/', $result['sale_price_effective_date'] );

		$product->delete( true );
	}

	/**
	 * Test sale_price_effective_date with future sale_from (end date should be sale_from + 30 days)
	 */
	public function test_map_product_sale_price_effective_date_with_only_start_date_in_far_future(): void {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_regular_price( '99.99' );
		$product->set_sale_price( '79.99' );

		// Set sale to start 2 months in the future.
		$sale_from = new \WC_DateTime( '+2 months' );
		$product->set_date_on_sale_from( $sale_from );
		$product->save();

		$result = $this->sut->map_product( $product );

		$this->assertArrayHasKey( 'sale_price_effective_date', $result );

		// Parse the dates.
		$dates = explode( ' / ', $result['sale_price_effective_date'] );
		$this->assertCount( 2, $dates );

		$start_date = new \DateTime( $dates[0] );
		$end_date   = new \DateTime( $dates[1] );

		// Verify end date is exactly 30 days after start date.
		$expected_end = clone $start_date;
		$expected_end->modify( '+30 days' );
		$this->assertEquals( $expected_end->format( 'Y-m-d' ), $end_date->format( 'Y-m-d' ), 'End date should be exactly 30 days after start date' );

		$product->delete( true );
	}

	/**
	 * Test sale_price_effective_date with past sale_from (end date should be today + 30 days)
	 */
	public function test_map_product_sale_price_effective_date_with_past_start_date(): void {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_regular_price( '99.99' );
		$product->set_sale_price( '79.99' );

		// Set sale to have started 3 months ago.
		$sale_from = new \WC_DateTime( '-3 months' );
		$product->set_date_on_sale_from( $sale_from );
		$product->save();

		$result = $this->sut->map_product( $product );

		$this->assertArrayHasKey( 'sale_price_effective_date', $result );

		// Parse the dates.
		$dates = explode( ' / ', $result['sale_price_effective_date'] );
		$this->assertCount( 2, $dates );

		$end_date = new \DateTime( $dates[1] );
		$now      = new \DateTime();

		// Verify end date is approximately 30 days from now (allow 1 day margin for execution time).
		$expected_end = clone $now;
		$expected_end->modify( '+30 days' );
		$diff = abs( $end_date->getTimestamp() - $expected_end->getTimestamp() );
		$this->assertLessThanOrEqual( DAY_IN_SECONDS, $diff, 'End date should be approximately 30 days from today' );

		$product->delete( true );
	}

	/**
	 * Test sale_price_effective_date with only sale_to date (start date should default to today)
	 */
	public function test_map_product_sale_price_effective_date_with_only_end_date(): void {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_regular_price( '99.99' );
		$product->set_sale_price( '79.99' );

		$sale_to = new \WC_DateTime( '2025-12-31' );
		$product->set_date_on_sale_to( $sale_to );
		$product->save();

		$result = $this->sut->map_product( $product );

		$this->assertArrayHasKey( 'sale_price_effective_date', $result );
		$this->assertStringEndsWith( ' / 2025-12-31', $result['sale_price_effective_date'] );

		// Verify format is correct (YYYY-MM-DD / YYYY-MM-DD).
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2} \/ \d{4}-\d{2}-\d{2}$/', $result['sale_price_effective_date'] );

		$product->delete( true );
	}

	/**
	 * Test sale_price_effective_date with no dates set (should use today and today + 30 days)
	 */
	public function test_map_product_sale_price_effective_date_with_no_dates(): void {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_regular_price( '99.99' );
		$product->set_sale_price( '79.99' );
		$product->save();

		$result = $this->sut->map_product( $product );

		$this->assertArrayHasKey( 'sale_price_effective_date', $result );

		// Verify format is correct (YYYY-MM-DD / YYYY-MM-DD).
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2} \/ \d{4}-\d{2}-\d{2}$/', $result['sale_price_effective_date'] );

		// Parse dates and verify end is after start.
		$dates = explode( ' / ', $result['sale_price_effective_date'] );
		$this->assertCount( 2, $dates );
		$this->assertLessThan( $dates[1], $dates[0], 'End date should be after start date' );

		$product->delete( true );
	}

	/**
	 * Test sale_price_effective_date is null when no sale_price
	 */
	public function test_map_product_sale_price_effective_date_null_without_sale_price(): void {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_regular_price( '99.99' );
		// Don't set sale_price.
		$product->save();

		$result = $this->sut->map_product( $product );

		// sale_price_effective_date should not be in result when there's no sale_price.
		$this->assertArrayNotHasKey( 'sale_price_effective_date', $result );

		$product->delete( true );
	}

	/**
	 * Test product_category returns hierarchical path with separator
	 */
	public function test_map_product_category_hierarchical_path(): void {
		// Create category hierarchy: Root > Child > Grandchild.
		$root_cat = wp_insert_term( 'Root Category', 'product_cat' );
		$this->assertIsArray( $root_cat );

		$child_cat = wp_insert_term(
			'Child Category',
			'product_cat',
			[ 'parent' => $root_cat['term_id'] ]
		);
		$this->assertIsArray( $child_cat );

		$grandchild_cat = wp_insert_term(
			'Grandchild Category',
			'product_cat',
			[ 'parent' => $child_cat['term_id'] ]
		);
		$this->assertIsArray( $grandchild_cat );

		$product = WC_Helper_Product::create_simple_product();
		$product->set_category_ids( [ $grandchild_cat['term_id'] ] );
		$product->save();

		$result = $this->sut->map_product( $product );

		$this->assertArrayHasKey( 'product_category', $result );
		$this->assertEquals( 'Root Category > Child Category > Grandchild Category', $result['product_category'] );

		$product->delete( true );
		wp_delete_term( $grandchild_cat['term_id'], 'product_cat' );
		wp_delete_term( $child_cat['term_id'], 'product_cat' );
		wp_delete_term( $root_cat['term_id'], 'product_cat' );
	}

	/**
	 * Test product_category selects deepest category when product has multiple categories
	 */
	public function test_map_product_category_selects_deepest(): void {
		// Create two category hierarchies with different depths.
		$shallow_cat = wp_insert_term( 'Shallow Category', 'product_cat' );
		$this->assertIsArray( $shallow_cat );

		$deep_root = wp_insert_term( 'Deep Root', 'product_cat' );
		$this->assertIsArray( $deep_root );

		$deep_child = wp_insert_term(
			'Deep Child',
			'product_cat',
			[ 'parent' => $deep_root['term_id'] ]
		);
		$this->assertIsArray( $deep_child );

		$deep_grandchild = wp_insert_term(
			'Deep Grandchild',
			'product_cat',
			[ 'parent' => $deep_child['term_id'] ]
		);
		$this->assertIsArray( $deep_grandchild );

		$product = WC_Helper_Product::create_simple_product();
		$product->set_category_ids( [ $shallow_cat['term_id'], $deep_grandchild['term_id'] ] );
		$product->save();

		$result = $this->sut->map_product( $product );

		$this->assertArrayHasKey( 'product_category', $result );
		// Should select the deepest hierarchy.
		$this->assertEquals( 'Deep Root > Deep Child > Deep Grandchild', $result['product_category'] );

		$product->delete( true );
		wp_delete_term( $deep_grandchild['term_id'], 'product_cat' );
		wp_delete_term( $deep_child['term_id'], 'product_cat' );
		wp_delete_term( $deep_root['term_id'], 'product_cat' );
		wp_delete_term( $shallow_cat['term_id'], 'product_cat' );
	}

	/**
	 * Test product_category returns null when product has no categories
	 */
	public function test_map_product_category_null_when_no_categories(): void {
		$product = WC_Helper_Product::create_simple_product();

		// Remove all categories including default 'Uncategorized'.
		$category_ids = $product->get_category_ids();
		if ( ! empty( $category_ids ) ) {
			wp_remove_object_terms( $product->get_id(), $category_ids, 'product_cat' );
		}

		// Reload the product to ensure categories are cleared.
		$product = wc_get_product( $product->get_id() );
		$this->assertEmpty( $product->get_category_ids(), 'Product should have no categories' );

		$result = $this->sut->map_product( $product );

		// When no categories, product_category should not be in result.
		$this->assertArrayNotHasKey( 'product_category', $result );

		$product->delete( true );
	}

	/**
	 * Test product_category for variation uses parent product categories
	 */
	public function test_map_product_category_variation_uses_parent_categories(): void {
		// Create category for parent product.
		$parent_cat = wp_insert_term( 'Parent Category', 'product_cat' );
		$this->assertIsArray( $parent_cat );

		$child_cat = wp_insert_term(
			'Child Category',
			'product_cat',
			[ 'parent' => $parent_cat['term_id'] ]
		);
		$this->assertIsArray( $child_cat );

		// Create a different category for variation (should not be used).
		$variation_cat = wp_insert_term( 'Variation Category', 'product_cat' );
		$this->assertIsArray( $variation_cat );

		// Create variable product with categories.
		$variable_product = WC_Helper_Product::create_variation_product();
		$variable_product->set_category_ids( [ $child_cat['term_id'] ] );
		$variable_product->save();

		$variations = $variable_product->get_children();
		$this->assertNotEmpty( $variations );

		$variation = wc_get_product( $variations[0] );
		$this->assertNotNull( $variation );

		// Set different category on variation (should be ignored).
		$variation->set_category_ids( [ $variation_cat['term_id'] ] );
		$variation->save();

		$result = $this->sut->map_product( $variation );

		$this->assertArrayHasKey( 'product_category', $result );
		// Should use parent's categories, not variation's own categories.
		$this->assertEquals( 'Parent Category > Child Category', $result['product_category'] );

		$variable_product->delete( true );
		wp_delete_term( $child_cat['term_id'], 'product_cat' );
		wp_delete_term( $parent_cat['term_id'], 'product_cat' );
		wp_delete_term( $variation_cat['term_id'], 'product_cat' );
	}

	/**
	 * Test product_category for simple product uses its own categories
	 */
	public function test_map_product_category_simple_product_uses_own_categories(): void {
		// Create category hierarchy.
		$root_cat = wp_insert_term( 'Electronics', 'product_cat' );
		$this->assertIsArray( $root_cat );

		$child_cat = wp_insert_term(
			'Laptops',
			'product_cat',
			[ 'parent' => $root_cat['term_id'] ]
		);
		$this->assertIsArray( $child_cat );

		$product = WC_Helper_Product::create_simple_product();
		$product->set_category_ids( [ $child_cat['term_id'] ] );
		$product->save();

		$result = $this->sut->map_product( $product );

		$this->assertArrayHasKey( 'product_category', $result );
		$this->assertEquals( 'Electronics > Laptops', $result['product_category'] );

		$product->delete( true );
		wp_delete_term( $child_cat['term_id'], 'product_cat' );
		wp_delete_term( $root_cat['term_id'], 'product_cat' );
	}

	/**
	 * Test product_category with single level category (no parent)
	 */
	public function test_map_product_category_single_level(): void {
		$category = wp_insert_term( 'Books', 'product_cat' );
		$this->assertIsArray( $category );

		$product = WC_Helper_Product::create_simple_product();
		$product->set_category_ids( [ $category['term_id'] ] );
		$product->save();

		$result = $this->sut->map_product( $product );

		$this->assertArrayHasKey( 'product_category', $result );
		$this->assertEquals( 'Books', $result['product_category'] );

		$product->delete( true );
		wp_delete_term( $category['term_id'], 'product_cat' );
	}
}
