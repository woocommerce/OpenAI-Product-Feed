<?php
/**
 * File delivery interface.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

namespace Automattic\WooCommerce\ProductFeedForOpenAI\DeliveryMethods;

use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\FileBasedFeedInterface;

interface FileDeliveryInterface {
	public function deliver( FileBasedFeedInterface $feed );
}
