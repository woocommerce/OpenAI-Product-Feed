<?php
/**
 * OpenAI Provider class.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

declare(strict_types=1);

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Platforms\OpenAI;

use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\FeedInterface;
use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\FeedValidatorInterface;
use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\ProductMapperInterface;
use Automattic\WooCommerce\ProductFeedForOpenAI\Platforms\IntegrationInterface;
use Automattic\WooCommerce\ProductFeedForOpenAI\Storage\JsonFileFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OpenAI Provider
 */
class OpenAIIntegration implements IntegrationInterface {
	/**
	 * Product mapper instance.
	 *
	 * @var ProductMapperInterface
	 */
	private ProductMapperInterface $product_mapper;

	/**
	 * Feed validator instance.
	 *
	 * @var FeedValidatorInterface
	 */
	private FeedValidatorInterface $feed_validator;

	/**
	 * Dependency injector.
	 *
	 * @param ProductMapper $product_mapper The product mapper.
	 * @param FeedValidator $feed_validator The feed validator.
	 */
	public function init(
		ProductMapper $product_mapper,
		FeedValidator $feed_validator
	) {
		$this->product_mapper = $product_mapper;
		$this->feed_validator = $feed_validator;
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
		return new JsonFileFeed( 'openai' );
	}

	/**
	 * Get the product mapper for the provider.
	 *
	 * @return ProductMapperInterface The product mapper.
	 */
	public function get_product_mapper(): ProductMapperInterface {
		return $this->product_mapper;
	}

	/**
	 * Get the feed validator for the provider.
	 *
	 * @return FeedValidatorInterface The feed validator.
	 */
	public function get_feed_validator(): FeedValidatorInterface {
		return $this->feed_validator;
	}
}
