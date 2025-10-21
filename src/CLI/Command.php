<?php
/**
 * CLI Command class.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

declare(strict_types=1);

namespace Automattic\WooCommerce\ProductFeedForOpenAI\CLI;

use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\FeedValidatorInterface;
use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\ProductMapperInterface;
use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\ProductWalker;
use Automattic\WooCommerce\ProductFeedForOpenAI\Platforms\OpenAI\FeedValidator;
use Automattic\WooCommerce\ProductFeedForOpenAI\Platforms\OpenAI\ProductMapper;
use Automattic\WooCommerce\ProductFeedForOpenAI\Storage\JsonInMemoryFeed;

/**
 * CLI command for generating a product feed.
 */
class Command extends \WP_CLI_Command {
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
	private FeedValidatorInterface $validator;

	/**
	 * Dependency injector.
	 *
	 * @param ProductMapper $product_mapper The product mapper.
	 * @param FeedValidator $validator The feed validator.
	 */
	public function init(
		ProductMapper $product_mapper,
		FeedValidator $validator
	) {
		$this->product_mapper = $product_mapper;
		$this->validator      = $validator;
	}

	/**
	 * Generates a product feed.
	 *
	 * ## EXAMPLES
	 *    wp product-feed generate
	 */
	public function generate() {
		$feed   = new JsonInMemoryFeed();
		$walker = new ProductWalker( $this->product_mapper, $this->validator, $feed );
		$walker->walk( 300 );

		\WP_CLI::success( wp_json_encode( $feed->deliver() ) );
	}
}
