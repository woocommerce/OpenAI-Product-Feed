<?php
declare( strict_types = 1 );

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Storage;

use Automattic\WooCommerce\ProductFeedForOpenAI\ProductFeedTestCase;

// This file works directly with local files. That's fine.
// phpcs:disable WordPress.WP.AlternativeFunctions

if ( ! function_exists( 'WP_Filesystem' ) ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
}


/**
 * JsonFileFeedTest class.
 */
class JsonFileFeedTest extends ProductFeedTestCase {
	public function test_feed_file_is_created() {
		// Make sure there is no directory and that it will be created.
		$directory = $this->get_and_delete_dir();

		$feed = new JsonFileFeed( 'test-feed' );
		$feed->start();
		$feed->end();

		$path = $feed->get_file_path();
		$this->assertStringContainsString( 'product-feeds', $path );
		$this->assertStringContainsString( $directory, $path );
		$this->assertTrue( file_exists( $path ) );
		$this->assertEquals( '[]', file_get_contents( $path ) );
	}

	public function test_feed_file_is_created_with_entries() {
		$data = [
			[
				'name'  => 'First Entry',
				'price' => 100,
			],
			[
				'name'  => 'Second Entry',
				'price' => 333,
			],
		];

		$feed = new JsonFileFeed( 'test-feed' );
		$feed->start();
		foreach ( $data as $entry ) {
			$feed->add_entry( $entry );
		}
		$feed->end();

		$this->assertEquals(
			wp_json_encode( $data ),
			file_get_contents( $feed->get_file_path() )
		);
	}

	public function test_get_file_url_returns_null_if_not_completed() {
		$feed = new JsonFileFeed( 'test-feed' );
		$feed->start();
		$this->assertNull( $feed->get_file_url() );
		$feed->end();
	}

	/**
	 * Gets the directory for feed files, but also deletes it.
	 *
	 * @return string The directory path.
	 */
	private function get_and_delete_dir(): string {
		$directory = wp_upload_dir()['basedir'] . '/product-feeds';
		if ( is_dir( $directory ) ) {
			global $wp_filesystem;
			WP_Filesystem();
			$wp_filesystem->rmdir( $directory, true );
		}
		return $directory;
	}
}
