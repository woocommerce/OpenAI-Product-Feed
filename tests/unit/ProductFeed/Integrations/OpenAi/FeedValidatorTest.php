<?php
declare( strict_types = 1 );

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Integrations\OpenAi;

use Automattic\WooCommerce\Enums\ProductStatus;
use PHPUnit\Framework\MockObject\MockObject;


/**
 * FeedValidator test class.
 *
 * Tests schema-driven validation of product feed data against OpenAI specifications.
 */
class FeedValidatorTest extends \WC_Unit_Test_Case {
	/**
	 * Feed validator instance.
	 *
	 * @var FeedValidator
	 */
	private FeedValidator $validator;

	/**
	 * Mock product.
	 *
	 * @var \WC_Product|MockObject
	 */
	private $mock_product;

	public function setUp(): void {
		parent::setUp();

		$this->validator = new FeedValidator();
		$this->validator->init();
		$this->mock_product = $this->createMock( \WC_Product::class );
	}

	/**
	 * Test validation passes with valid entry
	 */
	public function test_validate_entry_with_valid_data(): void {
		$entry = [
			'id'                 => '123',
			'title'              => 'Test Product',
			'description'        => 'Test Description',
			'link'               => 'https://example.com/product',
			'enable_search'      => 'true',
			'enable_checkout'    => 'false',
			'product_category'   => 'Electronics',
			'brand'              => 'TestBrand',
			'material'           => 'Metal',
			'weight'             => '1 kg',
			'image_link'         => 'https://example.com/image.jpg',
			'price'              => '99.99 USD',
			'availability'       => 'in_stock',
			'inventory_quantity' => 10,
			'condition'          => 'new',
			'seller_name'        => 'Test Store',
			'seller_url'         => 'https://teststore.com',
			'return_policy'      => 'https://teststore.com/returns',
			'return_window'      => 30,
		];

		$issues = $this->validator->validate_entry( $entry, $this->mock_product );

		$this->assertEmpty( $issues, 'Valid entry should not have validation issues' );
	}

	public function test_validate_entry_with_private_product(): void {
		$this->mock_product->method( 'get_status' )->willReturn( ProductStatus::PRIVATE );
		$entry = [
			'id'          => '123',
			'title'       => 'Test Product',
			'description' => 'Test Description',
		];

		$issues = $this->validator->validate_entry( $entry, $this->mock_product );

		$this->assertNotEmpty( $issues );
		$this->assertContains( 'Private products will not appear in search results', $issues );
	}

	/**
	 * Test required field validation
	 */
	public function test_validate_entry_missing_required_field(): void {
		$entry = [
			'id'          => '123',
			'description' => 'Test Description',
			'link'        => 'https://example.com/product',
			// 'title' is missing - required field
		];

		$issues = $this->validator->validate_entry( $entry, $this->mock_product );

		$this->assertNotEmpty( $issues, 'Should have validation issues for missing required field' );
		$this->assertCount(
			1,
			array_filter(
				$issues,
				function ( $issue ) {
					return strpos( $issue, 'title' ) !== false || strpos( $issue, 'Product title' ) !== false;
				}
			),
			'Should have issue about missing title'
		);
	}

	/**
	 * Test multiple required fields missing
	 */
	public function test_validate_entry_multiple_missing_required_fields(): void {
		$entry = [
			'id' => '123',
			// Missing: title, description, link, enable_search, enable_checkout, product_category.
		];

		$issues = $this->validator->validate_entry( $entry, $this->mock_product );

		$this->assertGreaterThanOrEqual( 3, count( $issues ), 'Should have multiple validation issues' );
	}

	/**
	 * Test integer field type validation
	 */
	public function test_validate_field_type_integer_invalid(): void {
		$entry = [
			'id'                 => '123',
			'title'              => 'Test Product',
			'description'        => 'Test Description',
			'link'               => 'https://example.com/product',
			'enable_search'      => 'true',
			'enable_checkout'    => 'false',
			'product_category'   => 'Electronics',
			'inventory_quantity' => 'not-a-number', // Invalid integer.
		];

		$issues = $this->validator->validate_entry( $entry, $this->mock_product );

		$this->assertNotEmpty( $issues );
		$this->assertCount(
			1,
			array_filter(
				$issues,
				function ( $issue ) {
					return strpos( $issue, 'inventory_quantity' ) !== false && strpos( $issue, 'number' ) !== false;
				}
			),
			'Should have issue about inventory_quantity not being a number'
		);
	}

	/**
	 * Test integer field type validation passes with valid integer
	 */
	public function test_validate_field_type_integer_valid(): void {
		$entry = [
			'id'                 => '123',
			'title'              => 'Test Product',
			'description'        => 'Test Description',
			'link'               => 'https://example.com/product',
			'enable_search'      => 'true',
			'enable_checkout'    => 'false',
			'product_category'   => 'Electronics',
			'brand'              => 'TestBrand',
			'inventory_quantity' => 100,
		];

		$issues = $this->validator->validate_entry( $entry, $this->mock_product );

		$filtered_issues = array_filter(
			$issues,
			function ( $issue ) {
				return strpos( $issue, 'inventory_quantity' ) !== false;
			}
		);
		$this->assertEmpty( $filtered_issues, 'Valid integer should not generate validation issues' );
	}

	/**
	 * Test URL field type validation
	 */
	public function test_validate_field_type_url_invalid(): void {
		$entry = [
			'id'               => '123',
			'title'            => 'Test Product',
			'description'      => 'Test Description',
			'link'             => 'not-a-valid-url', // Invalid URL.
			'enable_search'    => 'true',
			'enable_checkout'  => 'false',
			'product_category' => 'Electronics',
		];

		$issues = $this->validator->validate_entry( $entry, $this->mock_product );

		$this->assertNotEmpty( $issues );
		$this->assertCount(
			1,
			array_filter(
				$issues,
				function ( $issue ) {
					return strpos( $issue, 'link' ) !== false && strpos( $issue, 'URL' ) !== false;
				}
			),
			'Should have issue about link not being a valid URL'
		);
	}

	/**
	 * Test URL field type validation passes with valid URL
	 */
	public function test_validate_field_type_url_valid(): void {
		$entry = [
			'id'               => '123',
			'title'            => 'Test Product',
			'description'      => 'Test Description',
			'link'             => 'https://example.com/product/123',
			'enable_search'    => 'true',
			'enable_checkout'  => 'false',
			'product_category' => 'Electronics',
			'brand'            => 'TestBrand',
		];

		$issues = $this->validator->validate_entry( $entry, $this->mock_product );

		$filtered_issues = array_filter(
			$issues,
			function ( $issue ) {
				return strpos( $issue, 'link' ) !== false && strpos( $issue, 'URL' ) !== false;
			}
		);
		$this->assertEmpty( $filtered_issues, 'Valid URL should not generate validation issues' );
	}

	/**
	 * Test enum field validation with invalid value
	 */
	public function test_validate_field_enum_invalid(): void {
		$entry = [
			'id'               => '123',
			'title'            => 'Test Product',
			'description'      => 'Test Description',
			'link'             => 'https://example.com/product',
			'enable_search'    => 'true',
			'enable_checkout'  => 'false',
			'product_category' => 'Electronics',
			'brand'            => 'TestBrand',
			'condition'        => 'broken', // Invalid enum value.
		];

		$issues = $this->validator->validate_entry( $entry, $this->mock_product );

		$this->assertNotEmpty( $issues );
		$this->assertCount(
			1,
			array_filter(
				$issues,
				function ( $issue ) {
					return strpos( $issue, 'condition' ) !== false
					&& ( strpos( $issue, 'new' ) !== false || strpos( $issue, 'refurbished' ) !== false );
				}
			),
			'Should have issue about invalid condition enum value'
		);
	}

	/**
	 * Test enum field validation with valid values
	 */
	public function test_validate_field_enum_valid(): void {
		$valid_conditions = [ 'new', 'refurbished', 'used' ];

		foreach ( $valid_conditions as $condition ) {
			$entry = [
				'id'               => '123',
				'title'            => 'Test Product',
				'description'      => 'Test Description',
				'link'             => 'https://example.com/product',
				'enable_search'    => 'true',
				'enable_checkout'  => 'false',
				'product_category' => 'Electronics',
				'brand'            => 'TestBrand',
				'condition'        => $condition,
			];

			$issues = $this->validator->validate_entry( $entry, $this->mock_product );

			$filtered_issues = array_filter(
				$issues,
				function ( $issue ) {
					return strpos( $issue, 'condition' ) !== false;
				}
			);
			$this->assertEmpty( $filtered_issues, "Valid condition '{$condition}' should not generate validation issues" );
		}
	}

	/**
	 * Test enable_checkout requires enable_search validation
	 */
	public function test_enable_checkout_requires_enable_search(): void {
		$entry = [
			'id'               => '123',
			'title'            => 'Test Product',
			'description'      => 'Test Description',
			'link'             => 'https://example.com/product',
			'enable_search'    => 'false',
			'enable_checkout'  => 'true', // Invalid: checkout enabled but search disabled.
			'product_category' => 'Electronics',
			'brand'            => 'TestBrand',
		];

		$issues = $this->validator->validate_entry( $entry, $this->mock_product );

		$this->assertNotEmpty( $issues );
		$this->assertCount(
			1,
			array_filter(
				$issues,
				function ( $issue ) {
					return strpos( $issue, 'enable_checkout' ) !== false && strpos( $issue, 'enable_search' ) !== false;
				}
			),
			'Should have issue about enable_checkout requiring enable_search=true'
		);
	}

	/**
	 * Test enable_checkout works when enable_search is true
	 */
	public function test_enable_checkout_valid_when_search_enabled(): void {
		$entry = [
			'id'               => '123',
			'title'            => 'Test Product',
			'description'      => 'Test Description',
			'link'             => 'https://example.com/product',
			'enable_search'    => 'true',
			'enable_checkout'  => 'true',
			'product_category' => 'Electronics',
			'brand'            => 'TestBrand',
		];

		$issues = $this->validator->validate_entry( $entry, $this->mock_product );

		$filtered_issues = array_filter(
			$issues,
			function ( $issue ) {
				return strpos( $issue, 'enable_checkout' ) !== false;
			}
		);
		$this->assertEmpty( $filtered_issues, 'enable_checkout=true should be valid when enable_search=true' );
	}

	/**
	 * Test brand requirement validation
	 */
	public function test_brand_required_for_non_exempt_categories(): void {
		$entry = [
			'id'                 => '123',
			'title'              => 'Test Product',
			'description'        => 'Test Description',
			'link'               => 'https://example.com/product',
			'enable_search'      => 'true',
			'enable_checkout'    => 'false',
			'product_category'   => 'Electronics',
			'material'           => 'Metal',
			'weight'             => '1 kg',
			'image_link'         => 'https://example.com/image.jpg',
			'price'              => '99.99 USD',
			'availability'       => 'in_stock',
			'inventory_quantity' => 10,
			// 'brand' is missing for non-exempt category.
		];

		$issues = $this->validator->validate_entry( $entry, $this->mock_product );

		$this->assertNotEmpty( $issues );
		$this->assertGreaterThanOrEqual(
			1,
			count(
				array_filter(
					$issues,
					function ( $issue ) {
						return strpos( $issue, 'Brand' ) !== false || strpos( $issue, 'brand' ) !== false;
					}
				)
			),
			'Should have issue about missing brand'
		);
	}

	/**
	 * Test brand custom validation message not shown for exempt categories
	 *
	 * Note: brand is still required in the schema, but the custom validation
	 * message about exemptions should not appear for these categories.
	 */
	public function test_brand_custom_message_not_shown_for_exempt_categories(): void {
		$exempt_categories = [ 'Movies', 'Books', 'Music' ];

		foreach ( $exempt_categories as $category ) {
			$entry = [
				'id'                 => '123',
				'title'              => 'Test Product',
				'description'        => 'Test Description',
				'link'               => 'https://example.com/product',
				'enable_search'      => 'true',
				'enable_checkout'    => 'false',
				'product_category'   => $category,
				'material'           => 'Paper',
				'weight'             => '0.5 kg',
				'image_link'         => 'https://example.com/image.jpg',
				'price'              => '29.99 USD',
				'availability'       => 'in_stock',
				'inventory_quantity' => 50,
				'seller_name'        => 'Test Store',
				'seller_url'         => 'https://teststore.com',
				'return_policy'      => 'https://teststore.com/returns',
				'return_window'      => 30,
				// 'brand' is missing.
			];

			$issues = $this->validator->validate_entry( $entry, $this->mock_product );

			// Should not have the custom exemption message.
			$has_exemption_message = ! empty(
				array_filter(
					$issues,
					function ( $issue ) {
						return strpos( $issue, 'except for movies, books, music' ) !== false;
					}
				)
			);

			$this->assertFalse( $has_exemption_message, "Should not show exemption message for category: {$category}" );
		}
	}

	/**
	 * Test brand custom validation message not shown for partial category match
	 */
	public function test_brand_custom_message_not_shown_for_partial_category_match(): void {
		$entry = [
			'id'                 => '123',
			'title'              => 'Test Product',
			'description'        => 'Test Description',
			'link'               => 'https://example.com/product',
			'enable_search'      => 'true',
			'enable_checkout'    => 'false',
			'product_category'   => 'Movies & TV Shows', // Contains 'movies'.
			'material'           => 'Plastic',
			'weight'             => '0.2 kg',
			'image_link'         => 'https://example.com/image.jpg',
			'price'              => '19.99 USD',
			'availability'       => 'in_stock',
			'inventory_quantity' => 100,
			'seller_name'        => 'Test Store',
			'seller_url'         => 'https://teststore.com',
			'return_policy'      => 'https://teststore.com/returns',
			'return_window'      => 30,
			// 'brand' is missing
		];

		$issues = $this->validator->validate_entry( $entry, $this->mock_product );

		// Should not have the custom exemption message for partial matches.
		$has_exemption_message = ! empty(
			array_filter(
				$issues,
				function ( $issue ) {
					return strpos( $issue, 'except for movies, books, music' ) !== false;
				}
			)
		);

		$this->assertFalse( $has_exemption_message, 'Should not show exemption message for categories containing exempt keywords' );
	}

	/**
	 * Test sale date validation with invalid range (start after end)
	 */
	public function test_sale_dates_invalid_range(): void {
		$entry = [
			'id'                        => '123',
			'title'                     => 'Test Product',
			'description'               => 'Test Description',
			'link'                      => 'https://example.com/product',
			'enable_search'             => 'true',
			'enable_checkout'           => 'false',
			'product_category'          => 'Electronics',
			'brand'                     => 'TestBrand',
			'sale_price_effective_date' => '2024-12-31 / 2024-01-01', // End before start.
		];

		$issues = $this->validator->validate_entry( $entry, $this->mock_product );

		$this->assertNotEmpty( $issues );
		$this->assertCount(
			1,
			array_filter(
				$issues,
				function ( $issue ) {
					return strpos( $issue, 'sale' ) !== false && strpos( $issue, 'precede' ) !== false;
				}
			),
			'Should have issue about sale window start must precede end'
		);
	}

	/**
	 * Test sale date validation with valid range
	 */
	public function test_sale_dates_valid_range(): void {
		$entry = [
			'id'                        => '123',
			'title'                     => 'Test Product',
			'description'               => 'Test Description',
			'link'                      => 'https://example.com/product',
			'enable_search'             => 'true',
			'enable_checkout'           => 'false',
			'product_category'          => 'Electronics',
			'brand'                     => 'TestBrand',
			'sale_price_effective_date' => '2024-01-01 / 2024-12-31',
		];

		$issues = $this->validator->validate_entry( $entry, $this->mock_product );

		$filtered_issues = array_filter(
			$issues,
			function ( $issue ) {
				return strpos( $issue, 'sale' ) !== false;
			}
		);
		$this->assertEmpty( $filtered_issues, 'Valid sale date range should not generate validation issues' );
	}

	/**
	 * Data provider for invalid sale_price_effective_date formats
	 *
	 * @return array
	 */
	public function invalid_sale_date_formats_provider(): array {
		return [
			'missing space around separator' => [ '2024-01-01/2024-12-31' ],
			'wrong date separator'           => [ '2024/01/01 / 2024/12/31' ],
			'incomplete start date'          => [ '2024-01 / 2024-12-31' ],
			'incomplete end date'            => [ '2024-01-01 / 2024-12' ],
			'missing range separator'        => [ '2024-01-01' ],
			'extra spaces around separator'  => [ '2024-01-01  /  2024-12-31' ],
			'only separator with spaces'     => [ ' / ' ],
			'empty start date'               => [ ' / 2024-12-31' ],
			'empty end date'                 => [ '2024-01-01 / ' ],
			'wrong separator character'      => [ '2024-01-01 - 2024-12-31' ],
			'text instead of dates'          => [ 'start / end' ],
			'single digit month and day'     => [ '2024-1-1 / 2024-12-31' ],
		];
	}

	/**
	 * Test sale_price_effective_date with various invalid formats
	 *
	 * @dataProvider invalid_sale_date_formats_provider
	 * @param string $invalid_date Invalid date string.
	 */
	public function test_sale_dates_invalid_formats( string $invalid_date ): void {
		$entry = [
			'id'                        => '123',
			'title'                     => 'Test Product',
			'description'               => 'Test Description',
			'link'                      => 'https://example.com/product',
			'enable_search'             => 'true',
			'enable_checkout'           => 'false',
			'product_category'          => 'Electronics',
			'brand'                     => 'TestBrand',
			'sale_price_effective_date' => $invalid_date,
		];

		$issues = $this->validator->validate_entry( $entry, $this->mock_product );

		$this->assertNotEmpty( $issues, "Expected validation issues for invalid date format: {$invalid_date}" );
		$this->assertCount(
			1,
			array_filter(
				$issues,
				function ( $issue ) {
					return false !== strpos( $issue, 'sale_price_effective_date' ) && false !== strpos( $issue, 'YYYY-MM-DD / YYYY-MM-DD' );
				}
			),
			"Should have issue about invalid sale_price_effective_date format for: {$invalid_date}"
		);
	}

	/**
	 * Test sale_price_effective_date required when sale_price is provided
	 */
	public function test_sale_price_effective_date_required_with_sale_price(): void {
		$entry = [
			'id'               => '123',
			'title'            => 'Test Product',
			'description'      => 'Test Description',
			'link'             => 'https://example.com/product',
			'enable_search'    => 'true',
			'enable_checkout'  => 'false',
			'product_category' => 'Electronics',
			'brand'            => 'TestBrand',
			'sale_price'       => '79.99 USD', // sale_price provided.
			// sale_price_effective_date is missing.
		];

		$issues = $this->validator->validate_entry( $entry, $this->mock_product );

		$this->assertNotEmpty( $issues );
		$this->assertCount(
			1,
			array_filter(
				$issues,
				function ( $issue ) {
					return false !== strpos( $issue, 'sale_price_effective_date' ) && false !== strpos( $issue, 'required' );
				}
			),
			'Should have issue about sale_price_effective_date being required when sale_price is provided'
		);
	}

	/**
	 * Test validation allows '0' as a valid non-empty value
	 */
	public function test_zero_string_treated_as_non_empty(): void {
		$entry = [
			'id'                 => '0', // '0' should be treated as valid
			'title'              => 'Test Product',
			'description'        => 'Test Description',
			'link'               => 'https://example.com/product',
			'enable_search'      => 'true',
			'enable_checkout'    => 'false',
			'product_category'   => 'Electronics',
			'brand'              => 'TestBrand',
			'inventory_quantity' => 0, // 0 should be treated as valid
		];

		$issues = $this->validator->validate_entry( $entry, $this->mock_product );

		$filtered_issues = array_filter(
			$issues,
			function ( $issue ) {
				return strpos( $issue, 'id' ) !== false || strpos( $issue, 'inventory_quantity' ) !== false;
			}
		);
		$this->assertEmpty( $filtered_issues, 'Zero values should not be treated as empty' );
	}

	/**
	 * Test validation with empty optional fields
	 */
	public function test_empty_optional_fields_are_allowed(): void {
		$entry = [
			'id'                 => '123',
			'title'              => 'Test Product',
			'description'        => 'Test Description',
			'link'               => 'https://example.com/product',
			'enable_search'      => 'true',
			'enable_checkout'    => 'false',
			'product_category'   => 'Electronics',
			'brand'              => 'TestBrand',
			'material'           => 'Generic',
			'weight'             => '1 kg',
			'image_link'         => 'https://example.com/image.jpg',
			'price'              => '99.99 USD',
			'availability'       => 'in_stock',
			'inventory_quantity' => 10,
			'seller_name'        => 'Test Store',
			'seller_url'         => 'https://teststore.com',
			'return_policy'      => 'https://teststore.com/returns',
			'return_window'      => 30,
			// Optional fields like gtin, mpn, condition are omitted.
		];

		$issues = $this->validator->validate_entry( $entry, $this->mock_product );

		$this->assertEmpty( $issues, 'Empty optional fields should not generate validation issues' );
	}

	/**
	 * Test complex validation scenario with multiple issues
	 */
	public function test_validate_entry_multiple_issues(): void {
		$entry = [
			'id'                 => '123',
			// Missing 'title' (required).
			'description'        => 'Test Description',
			'link'               => 'not-a-url', // Invalid URL.
			'enable_search'      => 'false',
			'enable_checkout'    => 'true', // Invalid: requires enable_search=true.
			'product_category'   => 'Electronics',
			// Missing 'brand' (required for non-exempt categories).
			'condition'          => 'damaged', // Invalid enum value.
			'inventory_quantity' => 'abc', // Invalid integer.
		];

		$issues = $this->validator->validate_entry( $entry, $this->mock_product );

		$this->assertGreaterThanOrEqual( 5, count( $issues ), 'Should have multiple validation issues' );

		// Check for specific issues.
		$has_title_issue    = ! empty( array_filter( $issues, fn( $issue ) => strpos( $issue, 'title' ) !== false || strpos( $issue, 'Product title' ) !== false ) );
		$has_url_issue      = ! empty( array_filter( $issues, fn( $issue ) => strpos( $issue, 'link' ) !== false && strpos( $issue, 'URL' ) !== false ) );
		$has_checkout_issue = ! empty( array_filter( $issues, fn( $issue ) => strpos( $issue, 'enable_checkout' ) !== false ) );
		$has_brand_issue    = ! empty( array_filter( $issues, fn( $issue ) => strpos( $issue, 'Brand' ) !== false || strpos( $issue, 'brand' ) !== false ) );

		$this->assertTrue( $has_title_issue, 'Should have title validation issue' );
		$this->assertTrue( $has_url_issue, 'Should have URL validation issue' );
		$this->assertTrue( $has_checkout_issue, 'Should have enable_checkout validation issue' );
		$this->assertTrue( $has_brand_issue, 'Should have brand validation issue' );
	}
}
