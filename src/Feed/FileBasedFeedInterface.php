<?php
/**
 * File-based feed interface.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

declare(strict_types=1);

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Feed;

interface FileBasedFeedInterface {
	/**
	 * Get the path to the feed file.
	 *
	 * @return string The path to the feed file.
	 */
	public function get_file_path(): string;
}
