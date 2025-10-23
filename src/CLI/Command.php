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
use Automattic\WooCommerce\ProductFeedForOpenAI\Core\IntegrationRegistry;
use Automattic\WooCommerce\ProductFeedForOpenAI\DeliveryMethods\PushFile;
use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\FileBasedFeedInterface;
use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\ProductWalker;
use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\WalkerProgress;
use Automattic\WooCommerce\ProductFeedForOpenAI\Settings\SettingsRepository;
use Automattic\WooCommerce\ProductFeedForOpenAI\Utils\MemoryManager;
use RuntimeException;

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
	 * Settings repository instance.
	 *
	 * @var SettingsRepository
	 */
	private SettingsRepository $settings;

	/**
	 * Dependency injector.
	 *
	 * @param IntegrationRegistry $integration_registry The integration registry.
	 * @param SettingsRepository  $settings The settings repository.
	 */
	public function init(
		IntegrationRegistry $integration_registry,
		SettingsRepository $settings
	) {
		$this->integration_registry = $integration_registry;
		$this->settings             = $settings;
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
	 * @throws RuntimeException If the cURL request fails.
	 */
	public function generate( $args, $assoc_args ) {
		// Read args and prepare defaults.
		$timeout    = (int) $assoc_args['timeout'];
		$batch_size = (int) $assoc_args['batch-size'];
		$silent     = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'silent', false );
		$send       = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'send', false );

		if ( ! isset( $assoc_args['integration'] ) ) {
			return WP_CLI::error( 'Please provide the required --integration=<integration> parameter' );
		}
		$integration = $this->integration_registry->get_integration( $assoc_args['integration'] );
		if ( null === $integration ) {
			return WP_CLI::error( 'Integration not found' );
		}

		// Verify settings in advance if there is a requirement to send the feed.
		$endpoint = null;
		if ( $send ) {
			$endpoint = $this->settings->get_endpoint_url();
			if ( empty( $endpoint ) ) {
				return WP_CLI::error( 'Endpoint URL is not configured. Aborting.' );
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

		if ( ! is_a( $feed, FileBasedFeedInterface::class ) ) {
			// To be figured out next.
			return;
		}

		$path = $feed->get_file_path();
		if ( $silent && ! $send ) {
			WP_CLI::out( $path );
			return;
		}

		if ( ! $silent ) {
			WP_CLI::success( 'Feed generated successfully' );
			WP_CLI::log( "Path: $path" );
			WP_CLI::log( 'Time taken: ' . intval( ( microtime( true ) - $total_time ) ) . ' seconds' );

			if ( ! $send ) {
				WP_CLI::log( 'The --send option was not provided, the feed has not been sent.' );
				return;
			}

			WP_CLI::log( 'Sending feed to API...' );
		}

		$push   = new PushFile( $endpoint );
		$result = $push->deliver( $feed );

		// No need to do wonders with the response, just print it.
		if ( ! $silent ) {
			WP_CLI::success( 'Received a successful response from the API:' );
		}
		WP_CLI::print_value( $result->get_data() );
	}
}
