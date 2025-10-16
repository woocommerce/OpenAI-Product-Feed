<?php
/**
 *  Settings Repository class.
 *
 * @package OAPFW
 */

declare(strict_types=1);

namespace OAPFW\Settings;

use OAPFW\Core\Interfaces\SettingsRepositoryInterface;
use OAPFW\Utils\StringHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings repository implementation
 */
class SettingsRepository implements SettingsRepositoryInterface {

	const OPTION_NAME = 'oapfw_settings';

	/**
	 * Settings cache.
	 *
	 * @var array
	 */
	private array $cache = [];

	/**
	 * Whether settings have been loaded.
	 *
	 * @var bool
	 */
	private bool $loaded = false;

	/**
	 * Get setting value.
	 *
	 * @param string $key The setting key.
	 * @param mixed  $default_value Default value if key not found.
	 * @return mixed The setting value.
	 */
	public function get( string $key, $default_value = '' ) {
		$this->load_settings();
		return $this->cache[ $key ] ?? $default_value;
	}

	/**
	 * Set setting value.
	 *
	 * @param string $key The setting key.
	 * @param mixed  $value The setting value.
	 */
	public function set( string $key, $value ): void {
		$this->load_settings();
		$this->cache[ $key ] = $value;
	}

	/**
	 * Get all settings.
	 *
	 * @return array All settings.
	 */
	public function all(): array {
		$this->load_settings();
		return $this->cache;
	}

	/**
	 * Save settings to database.
	 *
	 * @param array $settings The settings to save.
	 * @return bool True on success.
	 */
	public function save( array $settings ): bool {
		$sanitized = $this->sanitize( $settings );
		$result    = update_option( self::OPTION_NAME, $sanitized );

		if ( $result ) {
			$this->cache  = $sanitized;
			$this->loaded = true;
		}

		return $result;
	}

	/**
	 * Get option name.
	 *
	 * @return string The option name.
	 */
	public function get_option_name(): string {
		return self::OPTION_NAME;
	}

	/**
	 * Load settings from database.
	 */
	private function load_settings(): void {
		if ( ! $this->loaded ) {
			$this->cache  = get_option( self::OPTION_NAME, [] );
			$this->loaded = true;
		}
	}

	/**
	 * Sanitize settings input.
	 *
	 * @param array $input The input data to sanitize.
	 * @return array Sanitized settings.
	 */
	public function sanitize( array $input ): array {
		// Start with existing settings to preserve values not in current form.
		$this->load_settings();
		$out = $this->cache;

		// Only update fields that are present in the input.
		if ( isset( $input['format'] ) ) {
			$out['format'] = in_array( $input['format'], [ 'json', 'csv', 'xml', 'tsv', true ], true )
				? $input['format'] : 'json';
		}

		// Handle delivery_enabled checkbox - always set since unchecked checkboxes don't appear in POST data.
		$out['delivery_enabled'] = isset( $input['delivery_enabled'] ) ? StringHelper::bool_string( $input['delivery_enabled'] ) : 'false';

		if ( isset( $input['endpoint_url'] ) ) {
			$out['endpoint_url'] = esc_url_raw( $input['endpoint_url'] );
		}

		if ( isset( $input['auth_token'] ) ) {
			$out['auth_token'] = sanitize_text_field( $input['auth_token'] );
		}

		if ( isset( $input['pickup_sla'] ) ) {
			$out['pickup_sla'] = sanitize_text_field( $input['pickup_sla'] );
		}

		if ( array_key_exists( 'enable_search_default', $input ) ) {
			$out['enable_search_default'] = StringHelper::bool_string( $input['enable_search_default'] );
		} elseif ( ! ( isset( $input['delivery_enabled'] ) || isset( $input['endpoint_url'] ) ) ) {
			$out['enable_search_default'] = 'false';
		}

		if ( array_key_exists( 'enable_checkout_default', $input ) ) {
			$out['enable_checkout_default'] = StringHelper::bool_string( $input['enable_checkout_default'] );
		} elseif ( ! ( isset( $input['delivery_enabled'] ) || isset( $input['endpoint_url'] ) ) ) {
			$out['enable_checkout_default'] = 'false';
		}

		if ( isset( $input['seller_name'] ) ) {
			$out['seller_name'] = sanitize_text_field( $input['seller_name'] );
		}

		if ( isset( $input['seller_url'] ) ) {
			$out['seller_url'] = esc_url_raw( $input['seller_url'] );
		}

		if ( isset( $input['privacy_url'] ) ) {
			$out['privacy_url'] = esc_url_raw( $input['privacy_url'] );
		}

		if ( isset( $input['tos_url'] ) ) {
			$out['tos_url'] = esc_url_raw( $input['tos_url'] );
		}

		if ( isset( $input['returns_url'] ) ) {
			$out['returns_url'] = esc_url_raw( $input['returns_url'] );
		}

		if ( isset( $input['return_window'] ) ) {
			$out['return_window'] = max( 0, absint( $input['return_window'] ) );
		}

		return $out;
	}

	/**
	 * Get default values with WordPress integration.
	 *
	 * @return array Default settings.
	 */
	public function get_defaults(): array {
		$default_values = [
			'format'                  => 'json',
			'delivery_enabled'        => 'false',
			'endpoint_url'            => '',
			'auth_token'              => '',
			'pickup_sla'              => '',
			'enable_search_default'   => 'true',
			'enable_checkout_default' => 'false',
			'return_window'           => 30,
		];

		// WordPress-integrated defaults.
		$default_values['seller_name'] = get_bloginfo( 'name' );

		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$shop_url                     = wc_get_page_permalink( 'shop' );
			$default_values['seller_url'] = $shop_url ? $shop_url : home_url( '/' );
		} else {
			$default_values['seller_url'] = home_url( '/' );
		}

		if ( function_exists( 'get_privacy_policy_url' ) ) {
			$default_values['privacy_url'] = get_privacy_policy_url();
		}

		if ( function_exists( 'wc_terms_and_conditions_page_id' ) ) {
			$tos_page_id = wc_terms_and_conditions_page_id();
			if ( $tos_page_id ) {
				$default_values['tos_url'] = get_permalink( $tos_page_id );
			}
		}

		return $default_values;
	}
}
