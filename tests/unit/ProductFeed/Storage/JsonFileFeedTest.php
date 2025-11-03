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
	public function tearDown(): void {
		parent::tearDown();
		remove_all_filters( 'wpfoai_feed_time' );
	}

	public function test_feed_file_is_created() {
		// Use the current time for the test as the time in the SUT to avoid flakiness.
		$current_time = time();
		add_filter( 'wpfoai_feed_time', fn() => $current_time );

		// Make sure there is no directory and that it will be created.
		$directory = $this->get_and_delete_dir();

		$feed = new JsonFileFeed( 'test-feed' );
		$feed->start();
		$feed->end();

		$path = $feed->get_file_path();
		$this->assertStringContainsString( 'product-feeds', $path );
		$this->assertStringContainsString( $directory, $path );
		$this->assertStringContainsString( gmdate( 'Y-m-d', $current_time ), $path );
		$this->assertStringContainsString( wp_hash( 'test-feed' . gmdate( 'r', $current_time ) ), $path );
		$this->assertTrue( file_exists( $path ) );
		$this->assertEquals( '[]', file_get_contents( $path ) );

		$url = $feed->get_file_url();
		$this->assertNotNull( $url );
		$this->assertStringEndsWith( '.json', (string) $url );
		$this->assertStringContainsString( '/product-feeds/', (string) $url );
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

	public function test_get_file_path_before_start_throws_type_error() {
		$feed = new JsonFileFeed( 'test-feed' );
		$this->expectException( \TypeError::class );
		// Property is unset until start(); return type is string → TypeError.
		$feed->get_file_path();
	}

	public function test_add_entry_before_start_throws_type_error() {
		$feed = new JsonFileFeed( 'test-feed' );
		$this->expectException( \TypeError::class );
		$feed->add_entry( [ 'name' => 'oops' ] );
	}

	public function test_end_before_start_throws_type_error() {
		$feed = new JsonFileFeed( 'test-feed' );
		$this->expectException( \TypeError::class );
		$feed->end();
	}

	public function test_start_throws_when_directory_cannot_be_created() {
		// Ensure clean state then create a FILE where the directory should be.
		$this->get_and_delete_dir();
		$uploads_dir = wp_upload_dir()['basedir'];
		$block_path  = $uploads_dir . '/product-feeds';

		// Create a file to block directory creation.
		file_put_contents( $block_path, 'blocking file' );

		try {
			$feed = new JsonFileFeed( 'test-feed' );
			$this->expectException( \Exception::class );
			$feed->start();
		} finally {
			// Cleanup: remove blocking file.
			if ( file_exists( $block_path ) && is_file( $block_path ) ) {
				unlink( $block_path );
			}
		}
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
