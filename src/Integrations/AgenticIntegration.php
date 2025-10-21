<?php
/**
 * WooCommerce Agentic (Integrations) bridge.
 *
 * Adds OpenAI-specific fields to the WooCommerce Integrations > ChatGPT section
 * and persists them in the shared registry. This lets the plugin simplify its
 * own settings and rely on Woo core’s unified settings surface.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

declare(strict_types=1);

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AgenticIntegration
 */
class AgenticIntegration {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		// Add OpenAI fields to the ChatGPT provider in Woo Integrations.
		add_filter( 'woocommerce_agentic_commerce_providers', [ $this, 'extend_providers' ], 10, 2 );

		// Persist additional fields to the registry when Integrations are saved.
		// Accept a single arg for forward compatibility; rely on $_POST for values.
		add_filter( 'woocommerce_agentic_commerce_save_settings', [ $this, 'save_settings' ], 10, 1 );
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
					'title'       => __( 'Feed Delivery URL', 'openai-product-feed-for-woo' ),
					/* translators: admin help text for feed destination URL */
					'desc'        => __( 'The URL where your product feed is delivered to ChatGPT. ', 'openai-product-feed-for-woo' ) .
						// Keep anchor simple to avoid translation coupling; core may render validation separately.
						__( 'Example: https://api.openai.com/v1/feeds/products', 'openai-product-feed-for-woo' ),
					'id'          => 'woocommerce_agentic_openai_feed_url',
					'type'        => 'text',
					'css'         => 'min-width:400px;',
					'placeholder' => 'https://api.openai.com/v1/feeds/products',
					'default'     => isset( $openai['feed_url'] ) ? (string) $openai['feed_url'] : '',
				];

				// Return window in days. Used by feed mapper when rendering merchant policy.
				$provider['fields'][] = [
					'title'             => __( 'Return Window (Days)', 'openai-product-feed-for-woo' ),
					'desc'              => __( 'Number of days customers have to return products.', 'openai-product-feed-for-woo' ),
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
		check_admin_referer( 'woocommerce-settings' );

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
}
