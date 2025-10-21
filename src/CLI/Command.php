<?php
/**
 * CLI Command class.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

declare(strict_types=1);

namespace Automattic\WooCommerce\ProductFeedForOpenAI\CLI;

use WP_CLI;
use WP_CLI_Command;
use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\FeedValidatorInterface;
use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\ProductMapperInterface;
use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\ProductWalker;
use Automattic\WooCommerce\ProductFeedForOpenAI\Platforms\OpenAI\FeedValidator;
use Automattic\WooCommerce\ProductFeedForOpenAI\Platforms\OpenAI\ProductMapper;
use Automattic\WooCommerce\ProductFeedForOpenAI\Storage\JsonFileFeed;

/**
 * CLI command for generating a product feed.
 */
class Command extends WP_CLI_Command {
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
	 * ## OPTIONS
	 *
	 * [--timeout=<seconds>]
	 * : The number of seconds to extend the execution time limit per batch.
	 * ---
	 * default: 300
	 * ---
	 *
	 * [--batch-size=<number>]
	 * : The number of products to process per batch.
	 * ---
	 * default: 100
	 * ---
	 *
	 * [--silent]
	 * : Whether to suppress informational messages.
	 * ---
	 * default: false
	 * ---
	 *
	 * ## EXAMPLES
	 *    wp product-feed generate
	 *    wp product-feed generate --timeout=200
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function generate( $args, $assoc_args ) {
		$timeout    = (int) $assoc_args['timeout'];
		$batch_size = (int) $assoc_args['batch-size'];
		$silent     = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'silent', false );

		$feed   = new JsonFileFeed();
		$walker = new ProductWalker( $this->product_mapper, $this->validator, $feed );
		$walker->set_batch_size( $batch_size );
		$walker->add_time_limit( $timeout );

		if ( ! $silent ) {
			WP_CLI::log( 'Starting feed generation...' );
		}

		$walker->walk(
			function ( $processed, $page, $pages ) use ( $silent ) {
				if ( $silent ) {
					return;
				}

				WP_CLI::log( "Batch $page/$pages: Processed $processed products" );
			}
		);

		$path = $feed->get_file_path();
		if ( $silent ) {
			WP_CLI::out( $path );
			return;
		}

		WP_CLI::success( 'Feed generated successfully' );
		WP_CLI::log( "Path: $path" );
	}
}
