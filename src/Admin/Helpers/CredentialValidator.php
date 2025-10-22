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
	 * Get endpoint URL.
	 *
	 * @return string The endpoint URL.
	 */
	public function get_endpoint_url(): string {
		return trim( (string) $this->settings->get( 'endpoint_url', '' ) );
	}
}
