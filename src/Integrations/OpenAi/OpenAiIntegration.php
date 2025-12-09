<?php
/**
 * OpenAI Provider class.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

declare(strict_types=1);

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Integrations\OpenAi;

use Automattic\WooCommerce\Internal\ProductFeed\Feed\FeedInterface;
use Automattic\WooCommerce\Internal\ProductFeed\Feed\FeedValidatorInterface;
use Automattic\WooCommerce\Internal\ProductFeed\Feed\ProductMapperInterface;
use Automattic\WooCommerce\Internal\ProductFeed\Integrations\IntegrationInterface;
use Automattic\WooCommerce\Internal\ProductFeed\Storage\JsonFileFeed;
use Automattic\WooCommerce\ProductFeedForOpenAI\Core\DependencyManagement\Container;
use Automattic\WooCommerce\ProductFeedForOpenAI\DeliveryMethods\FileDeliveryInterface;
use Automattic\WooCommerce\ProductFeedForOpenAI\DeliveryMethods\PushFile;
use Automattic\WooCommerce\ProductFeedForOpenAI\Integrations\PushIntegrationInterface;

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
	 * {@inheritdoc}
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
	 * {@inheritdoc}
	 */
	public function activate(): void {
		if ( ! as_has_scheduled_action( ScheduledActionManager::SCHEDULED_ACTION_HOOK ) ) {
			as_schedule_recurring_action( time(), 60 * 15, ScheduledActionManager::SCHEDULED_ACTION_HOOK );
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function deactivate(): void {
		// Clean up scheduled events using Action Scheduler.
		if ( function_exists( 'as_cancel_all_actions' ) ) {
			as_cancel_all_actions( ScheduledActionManager::SCHEDULED_ACTION_HOOK );
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_id(): string {
		return 'openai';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_product_feed_query_args(): array {
		return [
			'type' => [ 'simple', 'variation' ],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function create_feed(): FeedInterface {
		return new JsonFileFeed( 'openai-feed' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_product_mapper(): ProductMapperInterface {
		// Instantiate only when needed, meaning while generating feeds.
		return $this->container->get( ProductMapper::class );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_feed_validator(): FeedValidatorInterface {
		// Instantiate only when needed, meaning while generating feeds.
		return $this->container->get( FeedValidator::class );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_push_delivery_method(): FileDeliveryInterface {
		return new PushFile( $this->settings->get_endpoint_url() ?? '' );
	}
}
