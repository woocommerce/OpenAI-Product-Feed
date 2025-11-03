<?php
/**
 *  Settings Repository class.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

declare(strict_types=1);

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Integrations\OpenAi;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings repository implementation - stateless adapter to Woo core registry
 */
class Settings {
	/**
	 * Registers all needed hooks.
	 */
	public function register_hooks(): void {
		// Add OpenAI fields to the ChatGPT provider in Woo Integrations.
		add_filter( 'woocommerce_agentic_commerce_providers', [ $this, 'extend_providers' ], 10, 2 );

		// Persist additional fields to the registry when Integrations are saved.
		// Accept a single arg for forward compatibility; rely on $_POST for values.
		add_filter( 'woocommerce_agentic_commerce_save_settings', [ $this, 'save_settings' ], 10, 1 );
	}

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
				$privacy_url = get_privacy_policy_url();
				return $privacy_url ? $privacy_url : $default_value;
			case 'tos_url':
			case 'returns_url':
				$returns_url = get_permalink( wc_terms_and_conditions_page_id() );
				return $returns_url ? $returns_url : $default_value;
			case 'seller_name':
				return get_bloginfo( 'name' );
			case 'seller_url':
				$seller_url = wc_get_page_permalink( 'shop' );
				return $seller_url ? $seller_url : $default_value;
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
	 * Add fields to the OpenAI (ChatGPT) provider.
	 *
	 * @param array $providers Provider definitions.
	 * @param array $registry  Current registry values.
	 * @return array
	 */
	public function extend_providers( array $providers, array $registry ): array {
		foreach ( $providers as &$provider ) {
			if ( isset( $provider['id'] ) && 'openai' === $provider['id'] ) {
				$openai = isset( $registry['openai'] ) && is_array( $registry['openai'] ) ? $registry['openai'] : [];

				// Feed delivery URL (push endpoint) used by this plugin when pushing full/delta feeds.
				$provider['fields'][] = [
					'title'       => __( 'Feed Delivery URL', 'woocommerce-product-feed-openai' ),
					/* translators: admin help text for feed destination URL */
					'desc'        => __( 'The URL where your product feed is delivered to ChatGPT. ', 'woocommerce-product-feed-openai' ) .
						// Keep anchor simple to avoid translation coupling; core may render validation separately.
						__( 'Example: https://api.openai.com/v1/feeds/products', 'woocommerce-product-feed-openai' ),
					'id'          => 'woocommerce_agentic_openai_feed_url',
					'type'        => 'text',
					'css'         => 'min-width:400px;',
					'placeholder' => 'https://api.openai.com/v1/feeds/products',
					'default'     => isset( $openai['feed_url'] ) ? (string) $openai['feed_url'] : '',
				];

				// Return window in days. Used by feed mapper when rendering merchant policy.
				$provider['fields'][] = [
					'title'             => __( 'Return Window (Days)', 'woocommerce-product-feed-openai' ),
					'desc'              => __( 'Number of days customers have to return products.', 'woocommerce-product-feed-openai' ),
					'id'                => 'woocommerce_agentic_openai_return_window',
					'type'              => 'number',
					'css'               => 'width:80px;',
					'default'           => isset( $openai['return_window'] ) ? (int) $openai['return_window'] : 30,
					'custom_attributes' => [
						'min'  => '0',
						'step' => '1',
					],
				];
			}
		}

		return $providers;
	}

	/**
	 * Save our additional OpenAI fields into the registry.
	 *
	 * @param array $registry Registry to be saved by Woo core.
	 * @return array
	 */
	public function save_settings( array $registry ): array {
		if ( ! defined( 'PRODUCT_FEED_UNIT_TESTS' ) || ! PRODUCT_FEED_UNIT_TESTS ) {
			check_admin_referer( 'woocommerce-settings' );
		}

		// Feed URL.
		if ( isset( $_POST['woocommerce_agentic_openai_feed_url'] ) ) {
			$value = sanitize_text_field( wp_unslash( $_POST['woocommerce_agentic_openai_feed_url'] ) );

			$registry['openai']['feed_url'] = esc_url_raw( (string) $value );
		}

		// Return window (days).
		if ( isset( $_POST['woocommerce_agentic_openai_return_window'] ) ) {
			$value = sanitize_text_field( wp_unslash( $_POST['woocommerce_agentic_openai_return_window'] ) );

			$registry['openai']['return_window'] = max( 0, absint( $value ) );
		}

		return $registry;
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
