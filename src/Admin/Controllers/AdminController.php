<?php
/**
 *  Admin Controller class.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

declare(strict_types=1);

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Admin\Controllers;

use Automattic\WooCommerce\ProductFeedForOpenAI\Settings\SettingsRepository;
use Automattic\WooCommerce\ProductFeedForOpenAI\Platforms\OpenAI\Validators\FeedValidator;
use Automattic\WooCommerce\ProductFeedForOpenAI\Admin\Helpers\CredentialValidator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin controller for handling admin interface functionality.
 */
class AdminController {

	/**
	 * Settings repository instance.
	 *
	 * @var SettingsRepository
	 */
	private SettingsRepository $settings;

	/**
	 * Validator instance.
	 *
	 * @var FeedValidator
	 */
	private FeedValidator $validator;

	/**
	 * Credential validator instance.
	 *
	 * @var CredentialValidator
	 */
	private CredentialValidator $credential_validator;



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
	 * @param SettingsRepository $settings The settings repository.
	 * @param FeedValidator      $validator The validator.
	 */
	public function init(
		SettingsRepository $settings,
		FeedValidator $validator
	) {
		$this->settings  = $settings;
		$this->validator = $validator;
		$this->logger    = function_exists( 'wc_get_logger' ) ? wc_get_logger() : null;

		$this->credential_validator = new CredentialValidator( $settings );
	}

	/**
	 * Initialize the admin controller.
	 */
	public function initialize(): void {
		add_action( self::SCHEDULED_ACTION_HOOK, [ $this, 'cron_push_feed' ] );
	}

	/**
	 * Cron job to push feed.
	 */
	public function cron_push_feed(): void {
		$this->push_to_endpoint();
	}

	/**
	 * Push feed to endpoint.
	 */
	private function push_to_endpoint(): void {
		$rows = $this->feed_generator->build_feed();
		$this->push_feed_data( $rows, false );
	}

	/**
	 * Push feed data to endpoint.
	 *
	 * @param array $rows The feed rows.
	 */
	private function push_feed_data( array $rows ): void {
		$issues = $this->validator->validate_feed( $rows );

		if ( $issues ) {
			return;
		}

		$endpoint = $this->credential_validator->get_endpoint_url();

		if ( empty( $endpoint ) ) {
			return;
		}

		$payload = $this->feed_generator->serialize( $rows );
		$headers = [ 'Content-Type' => 'application/json' ];

		$token = $this->credential_validator->get_auth_token();
		if ( ! empty( $token ) ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		$response = wp_remote_post(
			$endpoint,
			[
				'headers' => $headers,
				'timeout' => 30,
				'body'    => $payload,
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
