<?php
/**
 * File delivery interface.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

namespace Automattic\WooCommerce\ProductFeedForOpenAI\DeliveryMethods;

use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\FileBasedFeedInterface;
use WP_REST_Response;

interface FileDeliveryInterface {
	/**
	 * Deliver the feed.
	 *
	 * @param FileBasedFeedInterface $feed The feed to deliver.
	 * @return WP_REST_Response The response from the remote endpoint.
	 */
	public function deliver( FileBasedFeedInterface $feed ): WP_REST_Response;
}
