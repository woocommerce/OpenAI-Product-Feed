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
use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\FileBasedFeedInterface;
use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\ProductWalker;
use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\WalkerProgress;
use Automattic\WooCommerce\ProductFeedForOpenAI\Settings\SettingsRepository;
use RuntimeException;

// This file uses cURL heavily. It's a requirement for the plugin.
// phpcs:disable WordPress.WP.AlternativeFunctions

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
			$endpoint = $this->settings->get( 'endpoint_url', '' );
			if ( empty( $endpoint ) ) {
				return WP_CLI::error( 'Endpoint URL is not configured. Aborting.' );
			}
		}

		$endpoint = 'http://host.docker.internal:9086';

		// Initialize the feed and walker, set them up.
		$feed   = $integration->create_feed();
		$walker = new ProductWalker( $integration->get_product_mapper(), $integration->get_feed_validator(), $feed );
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

			if ( ! $send ) {
				WP_CLI::log( 'The --send option was not provided, the feed has not been sent.' );
				return;
			}

			WP_CLI::log( 'Sending feed to API...' );
		}

		$ch = curl_init( $endpoint );
		curl_setopt_array(
			$ch,
			[
				CURLOPT_POST           => true,
				CURLOPT_INFILE         => fopen( $path, 'rb' ),
				CURLOPT_INFILESIZE     => filesize( $path ),
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HTTPHEADER     => [
					'Content-Type: application/json',
				],
			]
		);
		$response = curl_exec( $ch );

		if ( false === $response ) {
			// phpcs:ignore
			throw new RuntimeException( 'cURL error: ' . curl_error( $ch ) );
		}

		$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		if ( $http_code < 200 || $http_code > 299 ) {
			throw new RuntimeException( 'Received non-200 HTTP code: ' . $http_code );
		}
		curl_close( $ch );

		// No need to do wonders with the response, just print it.
		WP_CLI::success( 'Received a successful response from the API:' );
		WP_CLI::print_value( json_decode( $response, true ) );
	}
}
