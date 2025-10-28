<?php
/**
 * Scheduled Action Manager class.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

declare(strict_types=1);

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Integrations\OpenAi;

use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\ProductWalker;
use Automattic\WooCommerce\ProductFeedForOpenAI\Integrations\OpenAi\OpenAiIntegration;
use Automattic\WooCommerce\ProductFeedForOpenAI\Storage\JsonFileFeed;
use WC_Logger_Interface;

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
	 * @var OpenAiIntegration
	 */
	private OpenAiIntegration $openai_integration;

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
	 * @param OpenAiIntegration   $openai_integration The OpenAI integration.
	 * @param WC_Logger_Interface $logger The logger.
	 */
	public function init( OpenAiIntegration $openai_integration, WC_Logger_Interface $logger ) {
		$this->openai_integration = $openai_integration;
		$this->logger             = $logger;
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
		$delivery_method = $this->openai_integration->get_push_delivery_method();
		if ( ! $delivery_method->check_setup() ) {
			$this->logger->info( 'Push delivery method not setup', [ 'source' => 'wpfoai' ] );
			return;
		}

		$feed   = new JsonFileFeed( 'openai-feed' );
		$walker = new ProductWalker(
			$this->openai_integration->get_product_mapper(),
			$this->openai_integration->get_feed_validator(),
			$feed
		);
		$walker->walk();

		$response = $delivery_method->deliver( $feed );

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
