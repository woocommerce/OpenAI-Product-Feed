<?php
/**
 *  Plugin class.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

declare(strict_types=1);

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Core;

use Automattic\WooCommerce\ProductFeedForOpenAI\Admin\Controllers\AdminController;
use Automattic\WooCommerce\ProductFeedForOpenAI\Platforms\OpenAI\ProductFieldsController;
use Automattic\WooCommerce\ProductFeedForOpenAI\API\Controllers\ApiController;
use Automattic\WooCommerce\ProductFeedForOpenAI\CLI\Command;
use Automattic\WooCommerce\ProductFeedForOpenAI\Platforms\OpenAI\AgenticIntegration;
use Automattic\WooCommerce\ProductFeedForOpenAI\Core\DependencyManagement\Container;
use Automattic\WooCommerce\ProductFeedForOpenAI\Platforms\OpenAI\DevHelpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class - refactored to use dependency injection
 */
final class Plugin {

	/**
	 * Plugin instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Dependency injection container.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Whether plugin has been initialized.
	 *
	 * @var bool
	 */
	private bool $initialized = false;

	/**
	 * Get singleton instance.
	 *
	 * @return Plugin The plugin instance.
	 */
	public static function get_instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor
	 */
	private function __construct() {
		$this->container = new Container();
	}

	/**
	 * Initialize plugin
	 */
	public function initialize(): void {
		if ( $this->initialized ) {
			return;
		}

		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', [ $this, 'show_woo_commerce_missing_notice' ] );
			$this->initialized = true;
			return;
		}

		// Initialize components on WordPress init hook.
		add_action( 'init', [ $this, 'init' ], 0 );

		// Register the CLI command as well.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			add_action( 'cli_init', [ $this, 'register_cli_commands' ] );
		}

		$this->initialized = true;
	}

	/**
	 * Initialize plugin components
	 */
	public function init(): void {
		// Bridge into Woo Integrations (ChatGPT provider) for simplified settings.
		$this->container->get( AgenticIntegration::class )->register();

		// Initialize admin controller (no separate settings tab; configuration lives under Integrations → ChatGPT).
		$this->container->get( AdminController::class )->initialize();

		$this->container->get( ProductFieldsController::class )->initialize();

		$this->container->get( ApiController::class )->initialize();

		$this->container->get( DevHelpers::class )->initialize();
	}

	/**
	 * Register WP-CLI commands.
	 */
	public function register_cli_commands(): void {
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

		if ( ! as_has_scheduled_action( AdminController::SCHEDULED_ACTION_HOOK ) ) {
			as_schedule_recurring_action( time(), 60 * 15, AdminController::SCHEDULED_ACTION_HOOK );
		}
	}

	/**
	 * Plugin deactivation
	 */
	public function deactivate(): void {
		// Clean up scheduled events using Action Scheduler.
		if ( function_exists( 'as_cancel_all_actions' ) ) {
			as_cancel_all_actions( 'wpfoai_push_feed_event' );
		}
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
