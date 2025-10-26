<?php
/**
 * JSON File Feed class.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

declare(strict_types=1);

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Storage;

use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\FeedInterface;
use RuntimeException;

// This file works directly with local files. That's fine.
// phpcs:disable WordPress.WP.AlternativeFunctions

/**
 * File-backed JSON feed storage.
 *
 * This class writes JSON directly to a file, entry by entry, without keeping everything in memory.
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
	 * The base name of the feed file.
	 *
	 * @var string
	 */
	private $base_name;

	/**
	 * Indicates if the feed file has been completed.
	 *
	 * @var bool
	 */
	private $file_completed = false;

	/**
	 * Constructor.
	 *
	 * @param string $base_name The base name of the feed file.
	 */
	public function __construct( string $base_name ) {
		$this->base_name = $base_name;
	}

	/**
	 * Start the feed.
	 *
	 * @return void
	 * @throws RuntimeException If the feed directory cannot be created.
	 */
	public function start(): void {
		$upload_dir = wp_upload_dir( null, true );
		$directory  = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'product-feeds' . DIRECTORY_SEPARATOR;

		if ( ! is_dir( $directory ) && ! wp_mkdir_p( $directory ) ) {
			throw new RuntimeException(
				esc_html(
					sprintf(
						/* translators: %s: directory path */
						__( 'Unable to create feed directory: %s', 'woocommerce-product-feed-openai' ),
						$directory
					)
				)
			);
		}

		$this->file_path   = $directory . wp_unique_filename( $directory, $this->base_name . '.json' );
		$this->file_handle = fopen( $this->file_path, 'w' );

		if ( false === $this->file_handle ) {
			throw new RuntimeException(
				esc_html(
					sprintf(
						/* translators: %s: directory path */
						__( 'Unable to open feed file for writing: %s', 'woocommerce-product-feed-openai' ),
						$this->file_path
					)
				)
			);
		}

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

		// Indicate that we have a complete file.
		$this->file_completed = true;
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
