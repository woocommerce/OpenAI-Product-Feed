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
 * Settings repository implementation - stateless adapter to Woo core registry
 */
class SettingsRepository implements SettingsRepositoryInterface {

	/**
	 * Get setting value.
	 *
	 * @param string $key The setting key.
	 * @param mixed  $default_value Default value if key not found.
	 * @return mixed The setting value.
	 */
	public function get( string $key, $default_value = '' ) {
		$registry = $this->get_agentic_registry();
		$openai   = $registry['openai'] ?? [];
		$general  = $registry['general'] ?? [];

		switch ( $key ) {
			case 'privacy_url':
				return function_exists( 'get_privacy_policy_url' )
					? get_privacy_policy_url()
					: $default_value;
			case 'tos_url':
			case 'returns_url':
				return function_exists( 'wc_terms_and_conditions_page_id' )
					? get_permalink( wc_terms_and_conditions_page_id() )
					: $default_value;
			case 'seller_name':
				return get_bloginfo( 'name' );
			case 'seller_url':
				return function_exists( 'wc_get_page_permalink' )
					? wc_get_page_permalink( 'shop' )
					: home_url();
			case 'endpoint_url':
				$key = 'feed_url';
				// No break.
			default:
				if ( ! empty( $openai[ $key ] ) ) {
					return $openai[ $key ];
				}

				return ! empty( $general[ $key ] )
					? $general[ $key ]
					: $default_value;
		}
	}

	/**
	 * Set setting value.
	 *
	 * @param string $key The setting key.
	 * @param mixed  $value The setting value.
	 */
	public function set( string $key, $value ): void { // phpcs:ignore
		/* no-op */
	}

	/**
	 * Save settings to database.
	 *
	 * @param array $settings The settings to save.
	 * @return bool True on success.
	 */
	public function save( array $settings ): bool { // phpcs:ignore
		return false;
	}

	/**
	 * Get the full Woo agentic registry option value.
	 *
	 * @return array
	 */
	private function get_agentic_registry(): array {
		$val = get_option( 'woocommerce_agentic_agent_registry' );
		return is_array( $val ) ? $val : [];
	}
}
