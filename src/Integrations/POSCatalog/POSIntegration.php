<?php
/**
 * POS Catalog Integration class.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Integrations\POSCatalog;

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
 * POS Catalog Integration
 */
class POSIntegration implements IntegrationInterface {
	/**
	 * Container instance.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Dependency injector.
	 *
	 * @param Container $container Dependency container.
	 */
	public function init( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Get the ID of the provider.
	 *
	 * @return string The ID of the provider.
	 */
	public function get_id(): string {
		return 'pos';
	}

	/**
	 * Register hooks for the integration.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'rest_api_init', [ $this, 'rest_api_init' ] );
		$this->container->get( AsyncGenerator::class )->register_hooks();
	}

	/**
	 * Initialize the REST API.
	 *
	 * @return void
	 */
	public function rest_api_init(): void {
		// Only load the controller when necessary.
		$this->container->get( ApiController::class )->register_routes();
	}

	/**
	 * Activate the integration.
	 *
	 * @return void
	 */
	public function activate(): void {
		// At the moment, there are no activation steps for the POS catalog.
	}

	/**
	 * Deactivate the integration.
	 *
	 * @return void
	 */
	public function deactivate(): void {
		// At the moment, there are no deactivation steps for the POS catalog.
	}

	/**
	 * Create a feed that is to be populated.
	 *
	 * @return FeedInterface The feed.
	 */
	public function create_feed(): FeedInterface {
		return new JsonFileFeed( 'pos-catalog-feed' );
	}

	/**
	 * Get the product mapper for the provider.
	 *
	 * @return ProductMapperInterface The product mapper.
	 */
	public function get_product_mapper(): ProductMapperInterface {
		return $this->container->get( ProductMapper::class );
	}

	/**
	 * Get the feed validator for the provider.
	 *
	 * @return FeedValidatorInterface|null The feed validator.
	 */
	public function get_feed_validator(): ?FeedValidatorInterface {
		return $this->container->get( FeedValidator::class );
	}
}
