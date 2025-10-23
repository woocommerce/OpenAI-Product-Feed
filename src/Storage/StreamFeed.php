<?php
/**
 * StreamFeed class.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

declare(strict_types=1);

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Storage;

use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\FeedInterface;

/**
 * Feed that streams immediately.
 */
class StreamFeed implements FeedInterface {
	/**
	 * Indicates if there are previous entries in the feed.
	 *
	 * @var bool
	 */
	private $has_entries = false;

	/**
	 * Start the feed.
	 *
	 * @return void
	 */
	public function start(): void {
		header( 'Content-Type: application/json' );
		echo '[';
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
			echo ',';
		}

		echo wp_json_encode( $entry );

		// Make sure nothing stays in the buffer.
		flush();
	}

	/**
	 * End the feed.
	 *
	 * @return void
	 */
	public function end(): void {
		echo ']';
		exit;
	}
}
