<?php
/**
 * Scheduled Action Manager class.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

declare(strict_types=1);

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Integrations\OpenAI;

use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\ProductWalker;
use Automattic\WooCommerce\ProductFeedForOpenAI\Integrations\OpenAI\OpenAIIntegration;
use Automattic\WooCommerce\ProductFeedForOpenAI\Storage\JsonFileFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scheduled Action Manager for handling regular push for the feed.
 */
class ScheduledActionManager {
	/**
	 * OpenAI integration instance.
	 *
	 * @var OpenAIIntegration
	 */
	private OpenAIIntegration $openai_integration;

	/**
	 * Logger instance.
	 *
	 * @var mixed
	 */
	private $logger;

	const SCHEDULED_ACTION_HOOK = 'wpfoai_push_feed_event';

	/**
	 * Dependencies injector.
	 *
	 * @param OpenAIIntegration $openai_integration The OpenAI integration.
	 */
	public function init( OpenAIIntegration $openai_integration ) {
		$this->openai_integration = $openai_integration;
		$this->logger             = function_exists( 'wc_get_logger' ) ? wc_get_logger() : null;
	}

	/**
	 * Initialize the admin controller.
	 */
	public function initialize(): void {
		add_action( self::SCHEDULED_ACTION_HOOK, [ $this, 'scheduled_push' ] );
	}

	/**
	 * Cron job to push feed.
	 */
	public function scheduled_push(): void {
		$headers = [ 'Content-Type' => 'application/json' ];

		$endpoint = $this->openai_integration->get_push_endpoint_url();
		if ( empty( $endpoint ) ) {
			return;
		}

		$feed   = new JsonFileFeed( 'openai-feed' );
		$walker = new ProductWalker(
			$this->openai_integration->get_product_mapper(),
			$this->openai_integration->get_feed_validator(),
			$feed
		);
		$walker->walk();

		$response = wp_remote_post(
			$endpoint,
			[
				'headers' => $headers,
				'timeout' => 30,
				'body'    => file_get_contents( $feed->get_file_path() ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			]
		);

		if ( is_wp_error( $response ) ) {
			if ( $this->logger ) {
				$this->logger->error( 'Feed push failed: ' . $response->get_error_message(), [ 'source' => 'wpfoai' ] );
			}
		} else {
			$code = wp_remote_retrieve_response_code( $response );
			if ( $this->logger ) {
				if ( $code >= 200 && $code < 300 ) {
					$this->logger->info( 'Feed push successful: HTTP ' . $code, [ 'source' => 'wpfoai' ] );
				} else {
					$this->logger->warning( 'Feed push returned HTTP ' . $code, [ 'source' => 'wpfoai' ] );
				}
			}
		}
	}
}
