<?php
/**
 * Interface that allows providers to setup a push feed delivery mechanism.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

declare(strict_types=1);

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Integrations;

use Automattic\WooCommerce\ProductFeedForOpenAI\DeliveryMethods\FileDeliveryInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PushIntegrationInterface
 */
interface PushIntegrationInterface {
	/**
	 * Gets a delivery method that will push the feed to a remote endpoint.
	 *
	 * @return FileDeliveryInterface The push delivery method.
	 */
	public function get_push_delivery_method(): FileDeliveryInterface;
}
