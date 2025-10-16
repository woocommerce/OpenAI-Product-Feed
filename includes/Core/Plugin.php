<?php
/**
 *  Plugin class.
 *
 * @package OAPFW
 */

declare(strict_types=1);

namespace OAPFW\Core;

use OAPFW\Settings\SettingsRepository;
use OAPFW\Settings\SettingsRenderer;
use OAPFW\Feed\FeedGenerator;
use OAPFW\Platforms\OpenAI\Mappers\ProductMapper;
use OAPFW\Platforms\OpenAI\Validators\FeedValidator;
use OAPFW\Admin\Controllers\AdminController;
use OAPFW\Admin\Controllers\ProductFieldsController;
use OAPFW\API\Controllers\ApiController;

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

		$this->initialized = true;
	}

	/**
	 * Initialize plugin components
	 */
	public function init(): void {
		$this->register_services();
		$this->initialize_components();
	}

	/**
	 * Register services in container.
	 */
	private function register_services(): void {
		$this->container->set(
			'settings.repository',
			function () {
				return new SettingsRepository();
			}
		);

		$this->container->set(
			'settings.renderer',
			function () {
				return new SettingsRenderer( $this->container->get( 'settings.repository' ) );
			}
		);

		$this->container->set(
			'feed.mapper',
			function () {
				return new ProductMapper( $this->container->get( 'settings.repository' ) );
			}
		);

		$this->container->set(
			'feed.generator',
			function () {
				return new FeedGenerator( $this->container->get( 'feed.mapper' ) );
			}
		);

		$this->container->set(
			'feed.validator',
			function () {
				return new FeedValidator();
			}
		);

		$this->container->set(
			'admin.controller',
			function () {
				return new AdminController(
					$this->container->get( 'settings.repository' ),
					$this->container->get( 'feed.generator' ),
					$this->container->get( 'feed.validator' )
				);
			}
		);

		$this->container->set(
			'admin.product_fields_controller',
			function () {
				return new ProductFieldsController();
			}
		);

		$this->container->set(
			'api.controller',
			function () {
				return new ApiController(
					$this->container->get( 'settings.repository' ),
					$this->container->get( 'feed.generator' )
				);
			}
		);
	}

	/**
	 * Initialize components.
	 */
	private function initialize_components(): void {
		$this->container->get( 'settings.renderer' )->register();

		$this->container->get( 'admin.controller' )->init();

		$this->container->get( 'admin.product_fields_controller' )->init();

		$this->container->get( 'api.controller' )->init();
	}

	/**
	 * Plugin activation
	 */
	public function activate(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			deactivate_plugins( plugin_basename( OAPFW_PLUGIN_FILE ) );
			wp_die(
				esc_html__(
					'OpenAI Product Feed for Woo requires WooCommerce to be installed and active.',
					'openai-product-feed-for-woo'
				)
			);
		}

		$settings = new SettingsRepository();
		if ( ! get_option( $settings->get_option_name() ) ) {
			update_option( $settings->get_option_name(), $settings->get_defaults() );
		}
	}

	/**
	 * Plugin deactivation
	 */
	public function deactivate(): void {
		// Clean up scheduled events using Action Scheduler.
		if ( function_exists( 'as_cancel_all_actions' ) ) {
			as_cancel_all_actions( 'oapfw_push_feed_event' );
			as_cancel_all_actions( 'oapfw_push_delta_event' );
		}
	}

	/**
	 * Show WooCommerce missing notice.
	 */
	public function show_woo_commerce_missing_notice(): void {
		echo '<div class="notice notice-error"><p>' .
			esc_html__(
				'OpenAI Product Feed for Woo requires WooCommerce to be installed and active.',
				'openai-product-feed-for-woo'
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
		return OAPFW_VERSION;
	}

	/**
	 * Get plugin directory.
	 *
	 * @return string The plugin directory.
	 */
	public function get_plugin_dir(): string {
		return OAPFW_PLUGIN_DIR;
	}

	/**
	 * Get plugin URL.
	 *
	 * @return string The plugin URL.
	 */
	public function get_plugin_url(): string {
		return OAPFW_PLUGIN_URL;
	}
}
