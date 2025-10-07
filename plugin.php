<?php
/**
 * Plugin Name:          OpenAI Product Feed for Woo
 * Plugin URI:           https://automattic.ai
 * Description:          Generate and manage AI-optimized product feeds for WooCommerce.
 * Version:              0.1.0
 * Author:               WooCommerce
 * Author URI:           https://woocommerce.org
 * License:              GPL-2.0+
 * Text Domain:          openai-product-feed-for-woo
 * Requires at least:    6.0
 * Requires PHP:         7.4
 * WC requires at least: 7.0
 * WC tested up to:      9.6
 */

if (!defined("ABSPATH")) {
    exit();
}

define("OAPFW_VERSION", "0.1.0");
define("OAPFW_PLUGIN_FILE", __FILE__);
define("OAPFW_PLUGIN_DIR", plugin_dir_path(__FILE__));
define("OAPFW_PLUGIN_URL", plugin_dir_url(__FILE__));

require_once OAPFW_PLUGIN_DIR . 'includes/Core/Autoloader.php';
require_once OAPFW_PLUGIN_DIR . 'includes/Core/Interfaces.php';
require_once OAPFW_PLUGIN_DIR . 'includes/Core/Container.php';
require_once OAPFW_PLUGIN_DIR . 'includes/Core/Plugin.php';

add_action('plugins_loaded', function () {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>' .
                esc_html__(
                    "OpenAI Product Feed for Woo requires WooCommerce to be installed and active.",
                    "openai-product-feed-for-woo",
                ) .
                "</p></div>";
        });
        return;
    }

    $plugin = \OAPFW\Core\Plugin::getInstance();
    $plugin->initialize();
});

add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

register_activation_hook(__FILE__, function () {
    if (!class_exists("WooCommerce")) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            esc_html__(
                "OpenAI Product Feed for Woo requires WooCommerce to be installed and active.",
                "openai-product-feed-for-woo",
            ),
        );
    }
});

register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('oapfw_push_feed_event');
    wp_clear_scheduled_hook('oapfw_push_delta_event');
});

/**
 * Helper function to get plugin instance
 */
function oapfw_plugin(): \OAPFW\Core\Plugin
{
    return \OAPFW\Core\Plugin::getInstance();
}

/**
 * Helper function to get service from container
 */
function oapfw_get_service(string $service_id)
{
    return oapfw_plugin()->get($service_id);
}