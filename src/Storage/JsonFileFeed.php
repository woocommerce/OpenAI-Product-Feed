<?php
/**
 * JSON File Feed class.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

declare(strict_types=1);

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Storage;

use Automattic\WooCommerce\Internal\Utilities\FilesystemUtil;
use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\FeedInterface;
use Exception;

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
	 * The URL of the feed file.
	 *
	 * @var string|null
	 */
	private $file_url = null;

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
	 * @throws Exception If the feed directory cannot be created.
	 */
	public function start(): void {
		$upload_dir = wp_upload_dir( null, true );
		$directory  = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'product-feeds' . DIRECTORY_SEPARATOR;

		// Try to create the directory if it does not exist.
		if ( ! is_dir( $directory ) ) {
			FileSystemUtil::mkdir_p_not_indexable( $directory );
		}

		// `mkdir_p_not_indexable()` returns `void`, so we need to check again.
		if ( ! is_dir( $directory ) ) {
			throw new Exception(
				esc_html(
					sprintf(
						/* translators: %s: directory path */
						__( 'Unable to create feed directory: %s', 'woocommerce-product-feed-openai' ),
						$directory
					)
				)
			);
		}

		/**
		 * Generate a unique and private file name.
		 *
		 * @see https://github.com/woocommerce/woocommerce/pull/61332#discussion_r2431786208.
		 *
		 * Unlike that discussion, we are keeping track of the file name, so we can use the current date.
		 */
		$hash_data = $this->base_name . gmdate( 'r' );
		$file_name = $this->base_name . '-' . time() . '-' . wp_hash( $hash_data ) . '.json';

		$this->file_path   = $directory . $file_name;
		$this->file_url    = $upload_dir['baseurl'] . '/product-feeds/' . $file_name;
		$this->file_handle = fopen( $this->file_path, 'w' );

		if ( false === $this->file_handle ) {
			throw new Exception(
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

	/**
	 * Get the URL of the feed file.
	 *
	 * @return string|null The URL of the feed file, null if not completed.
	 */
	public function get_file_url(): ?string {
		if ( ! $this->file_completed ) {
			return null;
		}

		return $this->file_url;
	}
}
