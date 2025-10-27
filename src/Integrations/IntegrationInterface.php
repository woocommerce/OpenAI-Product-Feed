<?php
/**
 * Interface that should be implemented by all provider integrations.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

declare(strict_types=1);

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Integrations;

use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\FeedInterface;
use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\FeedValidatorInterface;
use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\ProductMapperInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * IntegrationInterface
 */
interface IntegrationInterface {
	/**
	 * Get the ID of the provider.
	 *
	 * @return string The ID of the provider.
	 */
	public function get_id(): string;

	/**
	 * Register hooks for the integration.
	 *
	 * @return void
	 */
	public function register_hooks(): void;

	/**
	 * Activate the integration.
	 *
	 * @return void
	 */
	public function activate(): void;

	/**
	 * Deactivate the integration.
	 *
	 * @return void
	 */
	public function deactivate(): void;

	/**
	 * Create a feed that is to be populated.
	 *
	 * @return FeedInterface The feed.
	 */
	public function create_feed(): FeedInterface;

	/**
	 * Get the product mapper for the provider.
	 *
	 * @return ProductMapperInterface The product mapper.
	 */
	public function get_product_mapper(): ProductMapperInterface;

	/**
	 * Get the feed validator for the provider.
	 *
	 * @return FeedValidatorInterface The feed validator.
	 */
	public function get_feed_validator(): FeedValidatorInterface;

	/**
	 * Get the endpoint URL for pushing feeds.
	 *
	 * As one of the next steps, rather than returning the URL from
	 * the integration, the integration should set up the push mechanism.
	 *
	 * @return string|null The endpoint URL.
	 */
	public function get_push_endpoint_url(): ?string;
}
