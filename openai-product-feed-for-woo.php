<?php
/**
 * WooCommerce Product Feed for OpenAI main plugin file.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 *
 * Plugin Name:          WooCommerce Product Feed for OpenAI
 * Plugin URI:           https://automattic.ai
 * Description:          Generate and manage AI-optimized product feeds for WooCommerce.
 * Version:              0.1.0
 * Author:               WooCommerce
 * Author URI:           https://woocommerce.org
 * License:              GPL-2.0+
 * Text Domain:          woocommerce-product-feed-openai
 * Requires at least:    6.0
 * Requires PHP:         7.4
 * WC requires at least: 7.0
 * WC tested up to:      9.6
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

define( 'WPFOAI_VERSION', '0.1.0' );
define( 'WPFOAI_PLUGIN_FILE', __FILE__ );
define( 'WPFOAI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPFOAI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once WPFOAI_PLUGIN_DIR . 'vendor/autoload.php';

// Initialize plugin after all plugins are loaded to ensure WooCommerce is available.
add_action(
	'plugins_loaded',
	function () {
		\Automattic\WooCommerce\ProductFeedForOpenAI\Core\Plugin::get_instance();
	}
);

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

// Activation and deactivation hooks - logic handled in Plugin class.
register_activation_hook(
	__FILE__,
	function () {
		$plugin = \Automattic\WooCommerce\ProductFeedForOpenAI\Core\Plugin::get_instance();
		$plugin->activate();
	}
);
register_deactivation_hook(
	__FILE__,
	function () {
		$plugin = \Automattic\WooCommerce\ProductFeedForOpenAI\Core\Plugin::get_instance();
		$plugin->deactivate();
	}
);

/**
 * Helper function to get plugin instance.
 *
 * @return \Automattic\WooCommerce\ProductFeedForOpenAI\Core\Plugin Plugin instance.
 */
function wpfoai_plugin(): \Automattic\WooCommerce\ProductFeedForOpenAI\Core\Plugin {
	return \Automattic\WooCommerce\ProductFeedForOpenAI\Core\Plugin::get_instance();
}

/**
 * Helper function to get service from container.
 *
 * @param string $service_id Service identifier.
 * @return mixed Service instance.
 */
function wpfoai_get_service( string $service_id ) {
	return wpfoai_plugin()->get( $service_id );
}
