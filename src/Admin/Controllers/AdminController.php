<?php
/**
 *  Admin Controller class.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

declare(strict_types=1);

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Admin\Controllers;

use Automattic\WooCommerce\ProductFeedForOpenAI\Platforms\OpenAI\FeedValidator;
use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\ProductMapperInterface;
use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\ProductWalker;
use Automattic\WooCommerce\ProductFeedForOpenAI\Platforms\OpenAI\ProductMapper;
use Automattic\WooCommerce\ProductFeedForOpenAI\Settings\SettingsRepository;
use Automattic\WooCommerce\ProductFeedForOpenAI\Storage\JsonInMemoryFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin controller for handling admin interface functionality.
 */
class AdminController {
	/**
	 * Product mapper instance.
	 *
	 * @var ProductMapperInterface
	 */
	private ProductMapperInterface $product_mapper;

	/**
	 * Validator instance.
	 *
	 * @var FeedValidator
	 */
	private FeedValidator $validator;

	/**
	 * Settings repository instance.
	 *
	 * @var SettingsRepository
	 */
	private SettingsRepository $settings;

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
	 * @param FeedValidator      $validator The validator.
	 * @param ProductMapper      $product_mapper The product mapper.
	 * @param SettingsRepository $settings The settings repository.
	 */
	public function init(
		FeedValidator $validator,
		ProductMapper $product_mapper,
		SettingsRepository $settings
	) {
		$this->validator      = $validator;
		$this->product_mapper = $product_mapper;
		$this->logger         = function_exists( 'wc_get_logger' ) ? wc_get_logger() : null;
		$this->settings       = $settings;
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

		$endpoint = $this->settings->get( 'endpoint_url', '' );
		if ( empty( $endpoint ) ) {
			return;
		}

		$feed   = new JsonInMemoryFeed();
		$walker = new ProductWalker( $this->product_mapper, $this->validator, $feed );
		$walker->walk();

		$response = wp_remote_post(
			$endpoint,
			[
				'headers' => $headers,
				'timeout' => 30,
				'body'    => wp_json_encode( $feed->deliver() ),
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
