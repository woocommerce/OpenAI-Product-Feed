<?php
/**
 * CLI Command class.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

declare(strict_types=1);

namespace Automattic\WooCommerce\ProductFeedForOpenAI\CLI;

use Automattic\WooCommerce\ProductFeedForOpenAI\Admin\Helpers\CredentialValidator;
use WP_CLI;
use WP_CLI_Command;
use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\FeedValidatorInterface;
use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\ProductMapperInterface;
use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\ProductWalker;
use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\WalkerProgress;
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
	 * Credential validator instance.
	 *
	 * @var CredentialValidator
	 */
	private CredentialValidator $credential_validator;

	/**
	 * Dependency injector.
	 *
	 * @param ProductMapper       $product_mapper The product mapper.
	 * @param FeedValidator       $validator The feed validator.
	 * @param CredentialValidator $credential_validator The credential validator.
	 */
	public function init(
		ProductMapper $product_mapper,
		FeedValidator $validator,
		CredentialValidator $credential_validator
	) {
		$this->product_mapper       = $product_mapper;
		$this->validator            = $validator;
		$this->credential_validator = $credential_validator;
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
	 * [--send]
	 * : Whether to send the feed to an API.
	 * ---
	 * default: false
	 * ---
	 *
	 * ## EXAMPLES
	 *    wp product-feed generate
	 *    wp product-feed generate --timeout=200
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function generate( $args, $assoc_args ) {
		// Read args and prepare defaults.
		$timeout    = (int) $assoc_args['timeout'];
		$batch_size = (int) $assoc_args['batch-size'];
		$silent     = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'silent', false );
		$send       = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'send', false );

		// Verify settings in advance if there is a requirement to send the feed.
		$endpoint = null;
		if ( $send ) {
			$endpoint = $this->credential_validator->get_endpoint_url();
			if ( empty( $endpoint ) ) {
				return WP_CLI::error( 'Endpoint URL is not configured. Aborting.' );
			}
		}

		// Initialize the feed and walker, set them up.
		$feed   = new JsonFileFeed();
		$walker = new ProductWalker( $this->product_mapper, $this->validator, $feed );
		$walker->set_batch_size( $batch_size );
		$walker->add_time_limit( $timeout );

		if ( ! $silent ) {
			WP_CLI::log( 'Starting feed generation...' );
		}

		$walker->walk(
			function ( WalkerProgress $progress ) use ( $silent ) {
				if ( $silent ) {
					return;
				}

				WP_CLI::log( "Batch $progress->processed_batches/$progress->total_batch_count: Processed $progress->processed_items/$progress->total_count products" );
			}
		);

		$path = $feed->get_file_path();
		if ( $silent && ! $send ) {
			WP_CLI::out( $path );
			return;
		}

		if ( ! $silent ) {
			WP_CLI::success( 'Feed generated successfully' );
			WP_CLI::log( "Path: $path" );

			if ( ! $send ) {
				WP_CLI::log( 'The --send option was not provided, the feed has not been sent.' );
				return;
			}

			WP_CLI::log( 'Sending feed to API...' );
		}

		// Add the needed additional headers.
		$headers = [];

		$response = wp_remote_post(
			$endpoint,
			[
				'headers' => $headers,
				'timeout' => 30,
				'body'    => wp_json_encode( $feed->deliver() ),
			]
		);

		// No need to do wonders with the response, just print it.
		WP_CLI::print_value( $response );
	}
}
