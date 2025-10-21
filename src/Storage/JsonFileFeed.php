<?php
/**
 * JSON File Feed class.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

declare(strict_types=1);

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Storage;

use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\FeedInterface;

// This file works directly with local files. That's fine.
// phpcs:disable WordPress.WP.AlternativeFunctions

/**
 * In-memory feed storage.
 *
 * This class simply stores all data in memory and generates JSON at once.
 */
class JsonFileFeed implements FeedInterface {
	/**
	 * Indicates if there are previous entries in the feed.
	 *
	 * @var bool
	 */
	private $has_entries = false;

	/**
	 * The path to the feed file.
	 *
	 * @var string
	 */
	private $file_path;

	/**
	 * The file handle.
	 *
	 * @var resource|null
	 */
	private $file_handle = null;

	/**
	 * Start the feed.
	 *
	 * @return void
	 */
	public function start(): void {
		$upload_dir = wp_upload_dir( null, true );
		$directory  = $upload_dir['basedir'] . '/product-feeds/';

		if ( ! is_dir( $directory ) ) {
			mkdir( $directory, 0755, true ); // We need to reconsider this access.
		}

		// Rudimentary, can be changed in the future.
		$i = 1;
		while ( file_exists( $directory . 'openai-feed-' . $i . '.json' ) ) {
			++$i;
		}

		$this->file_path   = $directory . 'openai-feed-' . $i . '.json';
		$this->file_handle = fopen( $this->file_path, 'w' );

		// Open the array.
		fwrite( $this->file_handle, '[' );
	}

	/**
	 * Add an entry to the feed.
	 *
	 * @param array $entry The entry to add.
	 * @return void
	 */
	public function add_entry( array $entry ): void {
		if ( ! $this->has_entries ) {
			$this->has_entries = true;
		} else {
			fwrite( $this->file_handle, ',' );
		}

		fwrite( $this->file_handle, wp_json_encode( $entry ) );
	}

	/**
	 * End the feed.
	 *
	 * @return void
	 */
	public function end(): void {
		// Close the array and the file.
		fwrite( $this->file_handle, ']' );
		fclose( $this->file_handle );
	}

	/**
	 * Deliver the feed and delete the temporary file.
	 *
	 * @return array An array that will be provided to WP_REST_Response.
	 */
	public function deliver(): array {
		// Temporary. Will be changed once we support multiple formats.
		$data = json_decode( file_get_contents( $this->file_path ), true );
		unlink( $this->file_path );
		return $data;
	}

	/**
	 * Get the path to the feed file.
	 *
	 * @return string The path to the feed file.
	 */
	public function get_file_path(): string {
		return $this->file_path;
	}
}
