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
		if (
			! class_exists( 'WooCommerce' ) // Woo is not instaled.
			|| ! function_exists( 'wc_get_container' ) // The container is not available in Woo.
			|| ! class_exists( ProductFeed::class ) // It's an old Woo version without ProductFeed bundled.
		) {
			add_action( 'admin_notices', [ $this, 'show_woo_commerce_missing_notice' ] );
			return;
		}

		$this->container = new Container();

		add_action( 'cli_init', [ $this, 'register_cli_commands' ] );

		$open_ai_integration = $this->container->get( OpenAiIntegration::class );
		$product_feed        = wc_get_container()->get( ProductFeed::class );
		$product_feed->register_integration( $open_ai_integration );
		$open_ai_integration->register_hooks();
	}

	/**
	 * Register WP-CLI commands.
	 *
	 * @throws \RuntimeException If container is not initialized.
	 */
	public function register_cli_commands(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		if ( null === $this->container ) {
			throw new \RuntimeException( 'Container is not initialized. WooCommerce may not be active.' );
		}

		$command = $this->container->get( Command::class );
		\WP_CLI::add_command( 'product-feed', $command );
	}

	/**
	 * Plugin activation
	 *
	 * @throws \RuntimeException If container is not initialized.
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

		if ( null === $this->container ) {
			throw new \RuntimeException( 'Container is not initialized. WooCommerce may not be active.' );
		}

		$this->container->get( OpenAiIntegration::class )->activate();
	}

	/**
	 * Plugin deactivation
	 *
	 * @throws \RuntimeException If container is not initialized.
	 */
	public function deactivate(): void {
		if ( null === $this->container ) {
			throw new \RuntimeException( 'Container is not initialized. WooCommerce may not be active.' );
		}

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
	 * @throws \RuntimeException If container is not initialized.
	 */
	public function get( string $id ) {
		if ( null === $this->container ) {
			throw new \RuntimeException( 'Container is not initialized. WooCommerce may not be active.' );
		}

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
