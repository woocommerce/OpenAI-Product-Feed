<?php
/**
 * Json In Memory Feed class.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

declare(strict_types=1);

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Storage;

use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\FeedInterface;

/**
 * In-memory feed storage.
 *
 * This class simply stores all data in memory and generates JSON at once.
 */
class JsonInMemoryFeed implements FeedInterface {
	/**
	 * The feed data.
	 *
	 * @var array
	 */
	private $data = [];

	/**
	 * Start the feed.
	 *
	 * @return void
	 */
	public function start(): void {
		$this->data = [];
	}

	/**
	 * Add an entry to the feed.
	 *
	 * @param array $entry The entry to add.
	 * @return void
	 */
	public function add_entry( array $entry ): void {
		$this->data[] = $entry;
	}

	/**
	 * End the feed.
	 *
	 * @return void
	 */
	public function end(): void {
		// Nothing to end for JSON files.
	}

	/**
	 * Deliver the feed.
	 *
	 * @return array An array that will be provided to WP_REST_Response.
	 */
	public function deliver(): array {
		return $this->data;
	}
}
