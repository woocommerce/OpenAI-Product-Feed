<?php
/**
 * OpenAI Provider class.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

declare(strict_types=1);

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Integrations\OpenAi;

use Automattic\WooCommerce\ProductFeedForOpenAI\Core\DependencyManagement\Container;
use Automattic\WooCommerce\ProductFeedForOpenAI\DeliveryMethods\FileDeliveryInterface;
use Automattic\WooCommerce\ProductFeedForOpenAI\DeliveryMethods\PushFile;
use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\FeedInterface;
use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\FeedValidatorInterface;
use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\ProductMapperInterface;
use Automattic\WooCommerce\ProductFeedForOpenAI\Integrations\IntegrationInterface;
use Automattic\WooCommerce\ProductFeedForOpenAI\Integrations\PushIntegrationInterface;
use Automattic\WooCommerce\ProductFeedForOpenAI\Storage\JsonFileFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OpenAI Provider
 */
class OpenAiIntegration implements IntegrationInterface, PushIntegrationInterface {
	/**
	 * Container instance.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Dependency injector.
	 *
	 * @param Container $container Dependency container.
	 * @param Settings  $settings Settings repository.
	 */
	public function init( Container $container, Settings $settings ) {
		$this->container = $container;
		$this->settings  = $settings;
	}

	/**
	 * Registers all needed hooks.
	 */
	public function register_hooks(): void {
		$this->settings->register_hooks();

		// Initialize the integration classes.
		// Done straight through the container, as most of them depend on this class.
		$this->container->get( ScheduledActionManager::class )->initialize();
		$this->container->get( ProductFieldsController::class )->initialize();
		$this->container->get( DevHelpers::class )->initialize();
	}

	/**
	 * Activate the integration.
	 *
	 * This method is called when the plugin is activated.
	 * If there is ever a setting that controls active integrations,
	 * this method might also be called when the integration is activated.
	 *
	 * @return void
	 */
	public function activate(): void {
		if ( ! as_has_scheduled_action( ScheduledActionManager::SCHEDULED_ACTION_HOOK ) ) {
			as_schedule_recurring_action( time(), 60 * 15, ScheduledActionManager::SCHEDULED_ACTION_HOOK );
		}
	}

	/**
	 * Deactivate the integration.
	 *
	 * This method is called when the plugin is deactivated.
	 * If there is ever a setting that controls active integrations,
	 * this method might also be called when the integration is deactivated.
	 *
	 * @return void
	 */
	public function deactivate(): void {
		// Clean up scheduled events using Action Scheduler.
		if ( function_exists( 'as_cancel_all_actions' ) ) {
			as_cancel_all_actions( ScheduledActionManager::SCHEDULED_ACTION_HOOK );
		}
	}

	/**
	 * Get the ID of the provider.
	 *
	 * @return string The ID of the provider.
	 */
	public function get_id(): string {
		return 'openai';
	}

	/**
	 * Get the query arguments for the product feed.
	 *
	 * @return array The query arguments.
	 */
	public function get_product_feed_query_args(): array {
		return [
			'type' => [ 'simple', 'variation' ],
		];
	}

	/**
	 * Create a feed that is to be populated.
	 *
	 * @return FeedInterface The feed.
	 */
	public function create_feed(): FeedInterface {
		return new JsonFileFeed( 'openai-feed' );
	}

	/**
	 * Get the product mapper for the provider.
	 *
	 * @return ProductMapperInterface The product mapper.
	 */
	public function get_product_mapper(): ProductMapperInterface {
		// Instantiate only when needed, meaning while generating feeds.
		return $this->container->get( ProductMapper::class );
	}

	/**
	 * Get the feed validator for the provider.
	 *
	 * @return FeedValidatorInterface The feed validator.
	 */
	public function get_feed_validator(): FeedValidatorInterface {
		// Instantiate only when needed, meaning while generating feeds.
		return $this->container->get( FeedValidator::class );
	}

	/**
	 * Get the push delivery method for the provider.
	 *
	 * @return FileDeliveryInterface The push delivery method.
	 */
	public function get_push_delivery_method(): FileDeliveryInterface {
		return new PushFile( $this->settings->get_endpoint_url() ?? '' );
	}
}
