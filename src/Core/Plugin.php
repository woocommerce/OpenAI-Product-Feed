<?php
/**
 *  Plugin class.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

declare(strict_types=1);

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Core;

use Automattic\WooCommerce\Internal\ProductFeed\ProductFeed;
use Automattic\WooCommerce\ProductFeedForOpenAI\CLI\Command;
use Automattic\WooCommerce\ProductFeedForOpenAI\Core\DependencyManagement\Container;
use Automattic\WooCommerce\ProductFeedForOpenAI\Integrations\OpenAi\OpenAiIntegration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class.
 */
final class Plugin {
	/**
	 * Dependency injection container.
	 *
	 * @var Container
	 */
	private $container;

	/**
	 * Get singleton instance.
	 *
	 * @return Plugin The plugin instance.
	 */
	public static function get_instance(): Plugin {
		static $instance;
		if ( null === $instance ) {
			$instance = new self();
		}
		return $instance;
	}

	/**
	 * Private constructor
	 */
	private function __construct() {
		$this->container = new Container();

		// Immediately initialize by adding the necessary top-level hooks.
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', [ $this, 'show_woo_commerce_missing_notice' ] );
			return;
		}

		add_action( 'cli_init', [ $this, 'register_cli_commands' ] );

		if ( function_exists( 'wc_get_container' ) ) {
			$open_ai_integration = $this->container->get( OpenAiIntegration::class );

			try {
				$product_feed = wc_get_container()->get( ProductFeed::class );
				$product_feed->register_integration( $open_ai_integration );
			} catch ( \Throwable $e ) {
				// ProductFeed service not available in this WooCommerce version.
				return;
			}

			$open_ai_integration->register_hooks();
		}
	}

	/**
	 * Register WP-CLI commands.
	 */
	public function register_cli_commands(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		$command = $this->container->get( Command::class );
		\WP_CLI::add_command( 'product-feed', $command );
	}

	/**
	 * Plugin activation
	 */
	public function activate(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			deactivate_plugins( plugin_basename( WPFOAI_PLUGIN_FILE ) );
			wp_die(
				esc_html__(
					'WooCommerce Product Feed for OpenAI requires WooCommerce to be installed and active.',
					'woocommerce-product-feed-openai'
				)
			);
		}

		$this->container->get( OpenAiIntegration::class )->activate();
	}

	/**
	 * Plugin deactivation
	 */
	public function deactivate(): void {
		$this->container->get( OpenAiIntegration::class )->deactivate();
	}

	/**
	 * Show WooCommerce missing notice.
	 */
	public function show_woo_commerce_missing_notice(): void {
		echo '<div class="notice notice-error"><p>' .
			esc_html__(
				'WooCommerce Product Feed for OpenAI requires WooCommerce to be installed and active.',
				'woocommerce-product-feed-openai'
			) .
			'</p></div>';
	}

	/**
	 * Get service from container.
	 *
	 * @param string $id The service ID.
	 * @return mixed The service instance.
	 */
	public function get( string $id ) {
		return $this->container->get( $id );
	}

	/**
	 * Get plugin version.
	 *
	 * @return string The plugin version.
	 */
	public function get_version(): string {
		return WPFOAI_VERSION;
	}

	/**
	 * Get plugin directory.
	 *
	 * @return string The plugin directory.
	 */
	public function get_plugin_dir(): string {
		return WPFOAI_PLUGIN_DIR;
	}

	/**
	 * Get plugin URL.
	 *
	 * @return string The plugin URL.
	 */
	public function get_plugin_url(): string {
		return WPFOAI_PLUGIN_URL;
	}
}
