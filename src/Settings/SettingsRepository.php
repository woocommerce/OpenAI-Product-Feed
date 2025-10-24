<?php
/**
 *  Settings Repository class.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

declare(strict_types=1);

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings repository implementation - stateless adapter to Woo core registry
 */
class SettingsRepository {

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
					$value = $openai[ $key ];
					return is_string( $value ) ? trim( $value ) : $value;
				}

				return ! empty( $general[ $key ] )
					? $general[ $key ]
					: $default_value;
		}
	}

	/**
	 * Get the endpoint URL.
	 *
	 * @return string|null The endpoint URL.
	 */
	public function get_endpoint_url(): ?string {
		/**
		 * Allows the endpoint URL to be changed.
		 *
		 * @since 0.1.0
		 * @todo Either change the prefix or remove `openai` from the name, depending on how the plugin is split.
		 *
		 * @param string $endpoint_url The endpoint URL.
		 * @return string
		 */
		$endpoint_url = apply_filters( 'wpfoai_openai_endpoint_url', $this->get( 'endpoint_url', '' ) );

		return ! empty( $endpoint_url ) ? $endpoint_url : null;
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
