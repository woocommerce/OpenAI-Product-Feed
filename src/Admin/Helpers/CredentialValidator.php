<?php
/**
 *  Credential Validator class.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

declare(strict_types=1);

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Admin\Helpers;

use Automattic\WooCommerce\ProductFeedForOpenAI\Settings\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates credentials and configuration for feed delivery.
 */
class CredentialValidator {
	/**
	 * Settings repository instance.
	 *
	 * @var SettingsRepository
	 */
	private SettingsRepository $settings;

	/**
	 * Dependency injector.
	 *
	 * @param SettingsRepository $settings The settings repository.
	 */
	public function init( SettingsRepository $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Check if credentials are configured.
	 *
	 * @return bool True if credentials are configured.
	 */
	public function are_credentials_configured(): bool {
		return $this->is_endpoint_configured() && $this->is_token_configured();
	}

	/**
	 * Check if endpoint is configured.
	 *
	 * @return bool True if endpoint is configured.
	 */
	public function is_endpoint_configured(): bool {
		return trim( (string) $this->settings->get( 'endpoint_url', '' ) ) !== '';
	}

	/**
	 * Check if token is configured.
	 *
	 * @return bool True if token is configured.
	 */
	public function is_token_configured(): bool {
		return trim( (string) $this->settings->get( 'auth_token', '' ) ) !== '';
	}

	/**
	 * Get endpoint URL.
	 *
	 * @return string The endpoint URL.
	 */
	public function get_endpoint_url(): string {
		return trim( (string) $this->settings->get( 'endpoint_url', '' ) );
	}

	/**
	 * Get auth token.
	 *
	 * @return string The auth token.
	 */
	public function get_auth_token(): string {
		return trim( (string) $this->settings->get( 'auth_token', '' ) );
	}
}
