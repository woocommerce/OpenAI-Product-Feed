<?php
namespace OAPFW\Core;

use OAPFW\Core\Autoloader;
use OAPFW\Settings\SettingsRepository;
use OAPFW\Settings\SettingsRenderer;
use OAPFW\Feed\FeedGenerator;
use OAPFW\Feed\Mappers\ProductMapper;
use OAPFW\Feed\Validators\FeedValidator;
use OAPFW\Admin\Controllers\AdminController;
use OAPFW\API\Controllers\ApiController;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin class - refactored to use dependency injection
 */
final class Plugin
{
    private static ?Plugin $instance = null;
    
    private Container $container;
    private bool $initialized = false;

    /**
     * Get singleton instance
     */
    public static function getInstance(): Plugin
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor
     */
    private function __construct()
    {
        $this->container = new Container();
        $this->setupAutoloader();
    }

    /**
     * Initialize plugin
     */
    public function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        add_action('plugins_loaded', [$this, 'checkDependencies']);
        add_action('init', [$this, 'init'], 0);
        
        $this->initialized = true;
    }

    /**
     * Check for WooCommerce dependency
     */
    public function checkDependencies(): void
    {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', [$this, 'showWooCommerceMissingNotice']);
            return;
        }
    }

    /**
     * Initialize plugin components
     */
    public function init(): void
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        $this->registerServices();
        $this->initializeComponents();
    }

    /**
     * Setup autoloader
     */
    private function setupAutoloader(): void
    {
        $autoloader = new Autoloader();
        $autoloader->addNamespace('OAPFW\\', OAPFW_PLUGIN_DIR . 'includes/');
        $autoloader->register();
        
        $this->container->set('autoloader', $autoloader);
    }

    /**
     * Register services in container
     */
    private function registerServices(): void
    {
        // Settings
        $this->container->set('settings.repository', function() {
            return new SettingsRepository();
        });

        $this->container->set('settings.renderer', function() {
            return new SettingsRenderer($this->container->get('settings.repository'));
        });

        // Feed components
        $this->container->set('feed.mapper', function() {
            return new ProductMapper($this->container->get('settings.repository'));
        });

        $this->container->set('feed.generator', function() {
            return new FeedGenerator($this->container->get('feed.mapper'));
        });

        $this->container->set('feed.validator', function() {
            return new FeedValidator();
        });

        // Controllers
        $this->container->set('admin.controller', function() {
            return new AdminController(
                $this->container->get('settings.repository'),
                $this->container->get('feed.generator'),
                $this->container->get('feed.validator')
            );
        });

        $this->container->set('api.controller', function() {
            return new ApiController(
                $this->container->get('settings.repository'),
                $this->container->get('feed.generator')
            );
        });
    }

    /**
     * Initialize components
     */
    private function initializeComponents(): void
    {
        // Initialize settings renderer
        $this->container->get('settings.renderer')->register();

        // Initialize admin controller
        $this->container->get('admin.controller')->init();

        // Initialize API controller
        $this->container->get('api.controller')->init();


        // Register activation/deactivation hooks
        register_activation_hook(OAPFW_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(OAPFW_PLUGIN_FILE, [$this, 'deactivate']);
    }

    /**
     * Plugin activation
     */
    public function activate(): void
    {
        if (!class_exists('WooCommerce')) {
            deactivate_plugins(plugin_basename(OAPFW_PLUGIN_FILE));
            wp_die(
                esc_html__(
                    'OpenAI Product Feed for Woo requires WooCommerce to be installed and active.',
                    'openai-product-feed-for-woo'
                )
            );
        }

        // Set default options if they don't exist
        $settings = $this->container->get('settings.repository');
        if (!get_option($settings->getOptionName())) {
            update_option($settings->getOptionName(), $settings->getDefaults());
        }
    }

    /**
     * Plugin deactivation
     */
    public function deactivate(): void
    {
        // Clean up scheduled events
        wp_clear_scheduled_hook('oapfw_push_feed_event');
        wp_clear_scheduled_hook('oapfw_push_delta_event');
    }

    /**
     * Show WooCommerce missing notice
     */
    public function showWooCommerceMissingNotice(): void
    {
        echo '<div class="notice notice-error"><p>' .
            esc_html__(
                'OpenAI Product Feed for Woo requires WooCommerce to be installed and active.',
                'openai-product-feed-for-woo'
            ) .
            '</p></div>';
    }

    /**
     * Get service from container
     */
    public function get(string $id)
    {
        return $this->container->get($id);
    }

    /**
     * Get plugin version
     */
    public function getVersion(): string
    {
        return OAPFW_VERSION;
    }

    /**
     * Get plugin directory
     */
    public function getPluginDir(): string
    {
        return OAPFW_PLUGIN_DIR;
    }

    /**
     * Get plugin URL
     */
    public function getPluginUrl(): string
    {
        return OAPFW_PLUGIN_URL;
    }
}