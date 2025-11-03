<?php
declare(strict_types=1);

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Integrations\OpenAi;

use Automattic\WooCommerce\ProductFeedForOpenAI\ProductFeedTestCase;
use WC_Helper_Product;

/**
 * Settings test class.
 */
class SettingsTest extends ProductFeedTestCase {
	/**
	 * System under test.
	 *
	 * @var Settings
	 */
	private Settings $sut;

	public function setUp(): void {
		parent::setUp();

		$this->sut = new Settings();
	}

	public function test_get_returns_privacy_url() {
		// Fall back to the default option if there is no privacy policy page set.
		$this->assertEquals(
			'https://example.com/',
			$this->sut->get( 'privacy_url', 'https://example.com/' )
		);

		// When there is a privacy policy page set, it should return the permalink.
		// Use a random product, it will have a link too.
		$product = WC_Helper_Product::create_simple_product();
		update_option( 'wp_page_for_privacy_policy', $product->get_id() );
		$this->assertEquals(
			$product->get_permalink(),
			$this->sut->get( 'privacy_url', 'https://example.com/' )
		);
	}

	public function test_get_returns_tos_and_returns_url() {
		// Fall back to the default option if there is no T&C policy page set.
		$this->assertEquals(
			'https://example.com/',
			$this->sut->get( 'tos_url', 'https://example.com/' )
		);
		$this->assertEquals(
			'https://example.com/',
			$this->sut->get( 'returns_url', 'https://example.com/' )
		);

		// When there is a T&C policy page set, it should return the permalink.
		// Use a random product, it will have a link too.
		$product = WC_Helper_Product::create_simple_product();
		update_option( 'woocommerce_terms_page_id', $product->get_id() );
		$this->assertEquals(
			$product->get_permalink(),
			$this->sut->get( 'tos_url', 'https://example.com/' )
		);
		$this->assertEquals(
			$product->get_permalink(),
			$this->sut->get( 'returns_url', 'https://example.com/' )
		);
	}

	public function test_get_seller_name_returns_blog_name() {
		$this->assertEquals(
			get_bloginfo( 'name' ),
			$this->sut->get( 'seller_name', 'https://example.com/' )
		);
	}

	public function test_get_returns_random_openai_setting() {
		update_option(
			'woocommerce_agentic_agent_registry',
			[
				'openai'  => [
					'basic_setting' => 'xyz',
				],
				'general' => [
					'basic_setting'   => 'xyz',
					'another_setting' => 'abc',
				],
			]
		);

		$this->assertEquals(
			'xyz',
			$this->sut->get( 'basic_setting' )
		);
		$this->assertEquals(
			'abc',
			$this->sut->get( 'another_setting' )
		);
	}

	public function test_get_endpoint_url_returns_feed_url() {
		update_option(
			'woocommerce_agentic_agent_registry',
			[
				'openai' => [
					'feed_url' => 'https://example.com/',
				],
			]
		);

		$this->assertEquals(
			'https://example.com/',
			$this->sut->get_endpoint_url()
		);
	}

	public function test_get_endpoint_url_returns_null_if_no_feed_url_is_set() {
		update_option(
			'woocommerce_agentic_agent_registry',
			[
				'openai' => [],
			]
		);

		$this->assertNull( $this->sut->get_endpoint_url() );
	}

	public function test_get_endpoint_url_respects_filter() {
		update_option(
			'woocommerce_agentic_agent_registry',
			[
				'openai' => [
					'feed_url' => 'https://example.com/',
				],
			]
		);
		add_filter( 'wpfoai_openai_endpoint_url', fn() => 'https://example.com/filtered/' );
		$this->assertEquals(
			'https://example.com/filtered/',
			$this->sut->get_endpoint_url()
		);
	}

	public function test_extend_providers() {
		$providers = [
			[
				'id'     => 'openai',
				'fields' => [
					[
						'title' => 'Test Field',
						'id'    => 'test_field',
					],
				],
			],
		];

		$updated_providers = $this->sut->extend_providers( $providers, [] );
		$this->assertIsArray( $updated_providers );
		$this->assertContains(
			'test_field',
			wp_list_pluck( $providers[0]['fields'], 'id' ),
			'Previously existing field remains'
		);
		$this->assertNotCount(
			1,
			$updated_providers[0]['fields'],
			'New fields are added'
		);
	}

	public function test_save_settings() {
		$registry = [
			'openai' => [
				'feed_url'      => 'https://example.com/',
				'return_window' => 30,
			],
		];

		$_POST['woocommerce_agentic_openai_feed_url']      = 'https://example.com/updated/';
		$_POST['woocommerce_agentic_openai_return_window'] = '60';

		$updated_registry = $this->sut->save_settings( $registry );

		$this->assertEquals( 'https://example.com/updated/', $updated_registry['openai']['feed_url'] );
		$this->assertEquals( 60, $updated_registry['openai']['return_window'] );
	}
}
