<?php
/**
 * File delivery interface.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

namespace Automattic\WooCommerce\ProductFeedForOpenAI\DeliveryMethods;

use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\FeedInterface;
use WP_REST_Response;

interface FileDeliveryInterface {
	/**
	 * Deliver the feed.
	 *
	 * @param FeedInterface $feed The feed to deliver.
	 * @return WP_REST_Response The response from the remote endpoint.
	 */
	public function deliver( FeedInterface $feed ): WP_REST_Response;

	/**
	 * Check if the delivery method is setup.
	 *
	 * @return bool True if the delivery method is setup, false otherwise.
	 */
	public function check_setup(): bool;
}
