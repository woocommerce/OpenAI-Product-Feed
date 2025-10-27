<?php
/**
 * CLI Command class.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

declare(strict_types=1);

namespace Automattic\WooCommerce\ProductFeedForOpenAI\CLI;

use RuntimeException;
use WP_CLI;
use WP_CLI_Command;
use Automattic\WooCommerce\ProductFeedForOpenAI\DeliveryMethods\PushFile;
use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\ProductWalker;
use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\WalkerProgress;
use Automattic\WooCommerce\ProductFeedForOpenAI\Integrations\IntegrationRegistry;
use Automattic\WooCommerce\ProductFeedForOpenAI\Integrations\PushIntegrationInterface;
use Automattic\WooCommerce\ProductFeedForOpenAI\Utils\MemoryManager;

// This is CLI. Non-escaped content should not break it.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

/**
 * CLI command for generating a product feed.
 */
class Command extends WP_CLI_Command {
	/**
	 * Integration registry instance.
	 *
	 * @var IntegrationRegistry
	 */
	private IntegrationRegistry $integration_registry;

	/**
	 * Dependency injector.
	 *
	 * @param IntegrationRegistry $integration_registry The integration registry.
	 */
	public function init( IntegrationRegistry $integration_registry ) {
		$this->integration_registry = $integration_registry;
	}

	/**
	 * Generates a product feed.
	 *
	 * ## OPTIONS
	 *
	 * [--integration=<integration>]
	 * : The slug of the integration to use. Required.
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
	 * [--push]
	 * : Whether to send/push the feed to an API.
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
	 * @throws RuntimeException If the cURL request fails.
	 */
	public function generate( $args, $assoc_args ) {
		// Read args and prepare defaults.
		$timeout    = (int) $assoc_args['timeout'];
		$batch_size = (int) $assoc_args['batch-size'];
		$silent     = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'silent', false );
		$push       = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'push', false );

		if ( ! isset( $assoc_args['integration'] ) ) {
			return WP_CLI::error( 'Please provide the required --integration=<integration> parameter' );
		}
		$integration = $this->integration_registry->get_integration( $assoc_args['integration'] );
		if ( null === $integration ) {
			return WP_CLI::error( 'Integration not found' );
		}

		// Check the delivery method before generating the feed.
		$delivery_method = null;
		if ( $push ) {
			if ( ! $integration instanceof PushIntegrationInterface ) {
				return WP_CLI::error( 'This integration does not support push delivery.' );
			}

			$delivery_method = $integration->get_push_delivery_method();
			if ( ! $delivery_method->check_setup() ) {
				return WP_CLI::error( 'Push delivery method for this integration is not configured.' );
			}
		}

		// Initialize the feed and walker, set them up.
		$feed   = $integration->create_feed();
		$walker = new ProductWalker( $integration->get_product_mapper(), $integration->get_feed_validator(), $feed );
		$walker->set_batch_size( $batch_size );
		$walker->add_time_limit( $timeout );

		if ( ! $silent ) {
			WP_CLI::log( 'Starting feed generation...' );
		}

		$total_time     = microtime( true );
		$total_items    = 0;
		$iteration_time = microtime( true );
		$walker->walk(
			function ( WalkerProgress $progress ) use ( $silent, &$iteration_time, &$total_items ) {
				if ( $silent ) {
					return;
				}

				$items_count = $progress->processed_items - $total_items;
				$total_items = $progress->processed_items; // reset.

				$duration       = microtime( true ) - $iteration_time;
				$iteration_time = microtime( true ); // reset.

				$per_item = round( ( $duration / $items_count ) * 1000, 2 );

				WP_CLI::log( "Batch $progress->processed_batches/$progress->total_batch_count: Processed $progress->processed_items/$progress->total_count products. Available memory: " . MemoryManager::get_available_memory() . "%. Time per item: $per_item ms" );
			}
		);

		$path = $feed->get_file_path();
		if ( $silent && null === $delivery_method ) {
			WP_CLI::out( $path );
			return;
		}

		if ( ! $silent ) {
			WP_CLI::success( 'Feed generated successfully' );
			WP_CLI::log( "Path: $path" );
			WP_CLI::log( 'Time taken: ' . intval( ( microtime( true ) - $total_time ) ) . ' seconds' );

			if ( null === $delivery_method ) {
				// Only log that the feed was not pushed if the integration supports push.
				if ( $integration instanceof PushIntegrationInterface ) {
					WP_CLI::log( 'The --push option was not provided, the feed has not been pushed.' );
				}
				return;
			}

			WP_CLI::log( 'Sending feed to API...' );
		}

		$result = $delivery_method->deliver( $feed );

		// No need to do wonders with the response, just print it.
		if ( ! $silent ) {
			WP_CLI::success( 'Received a successful response from the API:' );
		}
		WP_CLI::print_value( $result, [ 'format' => 'json' ] );
	}
}
