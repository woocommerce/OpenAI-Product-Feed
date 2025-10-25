<?php
/**
 * OpenAI Provider class.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

declare(strict_types=1);

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Integrations\OpenAI;

use Automattic\WooCommerce\ProductFeedForOpenAI\Core\DependencyManagement\Container;
use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\FeedInterface;
use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\FeedValidatorInterface;
use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\ProductMapperInterface;
use Automattic\WooCommerce\ProductFeedForOpenAI\Integrations\IntegrationInterface;
use Automattic\WooCommerce\ProductFeedForOpenAI\Storage\JsonFileFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OpenAI Provider
 */
class OpenAIIntegration implements IntegrationInterface {
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
}
