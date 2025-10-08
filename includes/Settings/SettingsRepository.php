<?php
namespace OAPFW\Settings;

use OAPFW\Core\SettingsRepositoryInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings repository implementation
 */
class SettingsRepository implements SettingsRepositoryInterface {

	const OPTION_NAME = 'oapfw_settings';

	private array $cache = array();
	private bool $loaded = false;

	/**
	 * Get setting value
	 */
	public function get( string $key, $default = '' ) {
		$this->loadSettings();
		return $this->cache[ $key ] ?? $default;
	}

	/**
	 * Set setting value
	 */
	public function set( string $key, $value ): void {
		$this->loadSettings();
		$this->cache[ $key ] = $value;
	}

	/**
	 * Get all settings
	 */
	public function all(): array {
		$this->loadSettings();
		return $this->cache;
	}

	/**
	 * Save settings to database
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
	 * Get option name
	 */
	public function getOptionName(): string {
		return self::OPTION_NAME;
	}

	/**
	 * Load settings from database
	 */
	private function loadSettings(): void {
		if ( ! $this->loaded ) {
			$this->cache  = get_option( self::OPTION_NAME, array() );
			$this->loaded = true;
		}
	}

	/**
	 * Sanitize settings input
	 */
	public function sanitize( array $input ): array {
		$out = array();

		$out['format'] = in_array( ( $input['format'] ?? 'json' ), array( 'json', 'csv', 'xml', 'tsv' ), true )
			? $input['format'] : 'json';

		$out['delivery_enabled'] = $this->boolString( $input['delivery_enabled'] ?? 'false' );
		$out['endpoint_url']     = esc_url_raw( $input['endpoint_url'] ?? '' );
		$out['auth_token']       = sanitize_text_field( $input['auth_token'] ?? '' );
		$out['pickup_sla']       = sanitize_text_field( $input['pickup_sla'] ?? '' );

		$out['enable_search_default']   = $this->boolString( $input['enable_search_default'] ?? 'false' );
		$out['enable_checkout_default'] = $this->boolString( $input['enable_checkout_default'] ?? 'false' );

		$out['seller_name']   = sanitize_text_field( $input['seller_name'] ?? '' );
		$out['seller_url']    = esc_url_raw( $input['seller_url'] ?? '' );
		$out['privacy_url']   = esc_url_raw( $input['privacy_url'] ?? '' );
		$out['tos_url']       = esc_url_raw( $input['tos_url'] ?? '' );
		$out['returns_url']   = esc_url_raw( $input['returns_url'] ?? '' );
		$out['return_window'] = isset( $input['return_window'] ) ? max( 0, absint( $input['return_window'] ) ) : 0;

		return $out;
	}

	/**
	 * Convert value to boolean string
	 */
	private function boolString( $value ): string {
		$value = strtolower( (string) $value );
		return ( $value === 'true' || $value === '1' || $value === 'yes' ) ? 'true' : 'false';
	}

	/**
	 * Get default values with WordPress integration
	 */
	public function getDefaults(): array {
		$defaults = array(
			'format'                  => 'json',
			'delivery_enabled'        => 'false',
			'endpoint_url'            => '',
			'auth_token'              => '',
			'pickup_sla'              => '',
			'enable_search_default'   => 'true',
			'enable_checkout_default' => 'false',
			'return_window'           => 30,
		);

		// WordPress-integrated defaults
		$defaults['seller_name'] = get_bloginfo( 'name' );

		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$shop_url               = wc_get_page_permalink( 'shop' );
			$defaults['seller_url'] = $shop_url ?: home_url( '/' );
		} else {
			$defaults['seller_url'] = home_url( '/' );
		}

		if ( function_exists( 'get_privacy_policy_url' ) ) {
			$defaults['privacy_url'] = get_privacy_policy_url();
		}

		if ( function_exists( 'wc_terms_and_conditions_page_id' ) ) {
			$tos_page_id = wc_terms_and_conditions_page_id();
			if ( $tos_page_id ) {
				$defaults['tos_url'] = get_permalink( $tos_page_id );
			}
		}

		return $defaults;
	}
}
