<?php
/**
 *  Product Fields Controller class.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

declare(strict_types=1);

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Admin\Controllers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles WooCommerce product data tab for OpenAI feed attributes
 *
 * Adds custom product fields for OpenAI feed data that can't be automatically
 * derived from WooCommerce's existing product data structure.
 */
class ProductFieldsController {

	/**
	 * Initialize product fields functionality
	 *
	 * Hooks into WooCommerce product editing to add OpenAI-specific fields.
	 */
	public function initialize(): void {
		add_filter( 'woocommerce_product_data_tabs', [ $this, 'add_product_data_tab' ] );
		add_action( 'woocommerce_product_data_panels', [ $this, 'render_product_data_panel' ] );
		add_action( 'woocommerce_admin_process_product_object', [ $this, 'save_product_fields' ] );
	}

	/**
	 * Add OpenAI Feed tab to product data tabs
	 *
	 * @param array $tabs Existing product data tabs.
	 * @return array Modified tabs array.
	 */
	public function add_product_data_tab( array $tabs ): array {
		$tabs['wpfoai'] = [
			'label'    => __( 'OpenAI Feed', 'openai-product-feed-for-woo' ),
			'target'   => 'wpfoai_product_data',
			'class'    => [ 'show_if_simple', 'show_if_variable' ],
			'priority' => 80,
		];
		return $tabs;
	}

	/**
	 * Render the OpenAI Feed product data panel
	 *
	 * Displays custom fields for OpenAI feed attributes in the WooCommerce product editor.
	 */
	public function render_product_data_panel(): void {
		echo '<div id="wpfoai_product_data" class="panel woocommerce_options_panel hidden">';

		$product = function_exists( 'wc_get_product' ) ? wc_get_product( get_the_ID() ) : null;

		echo '<p class="description">' . esc_html__( 'These fields inherit from WooCommerce global settings and existing product data (title, pricing, attributes). Set a value here only if you want to override the inherited value.', 'openai-product-feed-for-woo' ) . '</p>';
		echo '<div class="options_group">';

		woocommerce_wp_text_input(
			[
				'id'          => '_gtin',
				'label'       => __( 'GTIN', 'openai-product-feed-for-woo' ),
				'desc_tip'    => true,
				'description' => __( 'Global Trade Item Number (GTIN, UPC, EAN, etc.)', 'openai-product-feed-for-woo' ),
			]
		);

		woocommerce_wp_text_input(
			[
				'id'          => '_mpn',
				'label'       => __( 'MPN', 'openai-product-feed-for-woo' ),
				'desc_tip'    => true,
				'description' => __( 'Required if GTIN is not provided.', 'openai-product-feed-for-woo' ),
			]
		);

		woocommerce_wp_text_input(
			[
				'id'          => '_brand',
				'label'       => __( 'Brand (fallback)', 'openai-product-feed-for-woo' ),
				'desc_tip'    => true,
				'description' => __( 'Used if attribute pa_brand is not set.', 'openai-product-feed-for-woo' ),
				'placeholder' => $this->get_attribute_placeholder( $product, 'pa_brand' ),
			]
		);

		echo '</div><div class="options_group">';

		woocommerce_wp_checkbox(
			[
				'id'          => '_wpfoai_disable_search',
				'value'       => get_post_meta( get_the_ID(), '_wpfoai_disable_search', true ) === 'yes' ? 'yes' : 'no',
				'label'       => __( 'Disable search (ChatGPT)', 'openai-product-feed-for-woo' ),
				'description' => __( 'Overrides global default for this product.', 'openai-product-feed-for-woo' ),
			]
		);

		woocommerce_wp_checkbox(
			[
				'id'          => '_wpfoai_disable_checkout',
				'value'       => get_post_meta( get_the_ID(), '_wpfoai_disable_checkout', true ) === 'yes' ? 'yes' : 'no',
				'label'       => __( 'Disable checkout (ChatGPT)', 'openai-product-feed-for-woo' ),
				'description' => __( 'Requires search to be enabled.', 'openai-product-feed-for-woo' ),
			]
		);

		echo '</div><div class="options_group">';

		woocommerce_wp_text_input(
			[
				'id'          => '_wpfoai_material',
				'label'       => __( 'Material', 'openai-product-feed-for-woo' ),
				'description' => __( 'Primary material (e.g., cotton, plastic, metal)', 'openai-product-feed-for-woo' ),
			]
		);

		woocommerce_wp_select(
			[
				'id'          => '_wpfoai_condition',
				'label'       => __( 'Condition', 'openai-product-feed-for-woo' ),
				'options'     => [
					''            => __( '— Select —', 'openai-product-feed-for-woo' ),
					'new'         => 'new',
					'refurbished' => 'refurbished',
					'used'        => 'used',
				],
				'description' => __( 'Required if not new.', 'openai-product-feed-for-woo' ),
			]
		);

		woocommerce_wp_select(
			[
				'id'          => '_wpfoai_age_group',
				'label'       => __( 'Age group', 'openai-product-feed-for-woo' ),
				'options'     => [
					''        => __( '— Optional —', 'openai-product-feed-for-woo' ),
					'newborn' => 'newborn',
					'infant'  => 'infant',
					'toddler' => 'toddler',
					'kids'    => 'kids',
					'adult'   => 'adult',
				],
				'placeholder' => $this->get_attribute_placeholder( $product, 'pa_age_group' ),
			]
		);

		echo '</div><div class="options_group">';

		woocommerce_wp_text_input(
			[
				'id'                => '_wpfoai_age_restriction',
				'label'             => __( 'Age restriction', 'openai-product-feed-for-woo' ),
				'type'              => 'number',
				'custom_attributes' => [
					'min'  => '0',
					'step' => '1',
				],
			]
		);

		woocommerce_wp_text_input(
			[
				'id'    => '_wpfoai_warning',
				'label' => __( 'Warning text', 'openai-product-feed-for-woo' ),
			]
		);

		woocommerce_wp_text_input(
			[
				'id'          => '_wpfoai_warning_url',
				'label'       => __( 'Warning URL', 'openai-product-feed-for-woo' ),
				'placeholder' => 'https://',
			]
		);

		echo '</div><div class="options_group">';

		woocommerce_wp_text_input(
			[
				'id'          => '_wpfoai_color',
				'label'       => __( 'Color (override)', 'openai-product-feed-for-woo' ),
				'description' => __( 'Used if attribute pa_color is not set.', 'openai-product-feed-for-woo' ),
				'placeholder' => $this->get_attribute_placeholder( $product, 'pa_color' ),
			]
		);

		woocommerce_wp_text_input(
			[
				'id'          => '_wpfoai_size',
				'label'       => __( 'Size (override)', 'openai-product-feed-for-woo' ),
				'description' => __( 'Used if attribute pa_size is not set.', 'openai-product-feed-for-woo' ),
				'placeholder' => $this->get_attribute_placeholder( $product, 'pa_size' ),
			]
		);

		woocommerce_wp_select(
			[
				'id'          => '_wpfoai_size_system',
				'label'       => __( 'Size system', 'openai-product-feed-for-woo' ),
				'options'     => [
					''    => __( '— Select —', 'openai-product-feed-for-woo' ),
					'US'  => 'US',
					'UK'  => 'UK',
					'EU'  => 'EU',
					'FR'  => 'FR',
					'DE'  => 'DE',
					'IT'  => 'IT',
					'JP'  => 'JP',
					'CN'  => 'CN',
					'MEX' => 'MEX',
					'BR'  => 'BR',
				],
				'description' => __( 'Size measurement system.', 'openai-product-feed-for-woo' ),
			]
		);

		woocommerce_wp_select(
			[
				'id'      => '_wpfoai_gender',
				'label'   => __( 'Target gender', 'openai-product-feed-for-woo' ),
				'options' => [
					''       => __( '— Select —', 'openai-product-feed-for-woo' ),
					'male'   => 'Male',
					'female' => 'Female',
					'unisex' => 'Unisex',
				],
			]
		);

		echo '</div><div class="options_group">';

		woocommerce_wp_text_input(
			[
				'id'          => '_wpfoai_video_link',
				'label'       => __( 'Product video URL', 'openai-product-feed-for-woo' ),
				'placeholder' => 'https://',
			]
		);

		woocommerce_wp_text_input(
			[
				'id'          => '_wpfoai_model_3d_link',
				'label'       => __( '3D model URL', 'openai-product-feed-for-woo' ),
				'placeholder' => 'https://',
			]
		);

		echo '</div><div class="options_group">';

		woocommerce_wp_textarea_input(
			[
				'id'    => '_wpfoai_q_and_a',
				'label' => __( 'Q&A (plain text)', 'openai-product-feed-for-woo' ),
				'rows'  => 3,
			]
		);

		echo '</div><div class="options_group">';

		woocommerce_wp_text_input(
			[
				'id'    => '_wpfoai_applicable_taxes_fees',
				'label' => __( 'Additional taxes/fees (e.g., 7 USD)', 'openai-product-feed-for-woo' ),
			]
		);

		woocommerce_wp_text_input(
			[
				'id'    => '_wpfoai_unit_pricing_measure',
				'label' => __( 'Unit pricing measure (e.g., 16 oz)', 'openai-product-feed-for-woo' ),
			]
		);

		woocommerce_wp_text_input(
			[
				'id'    => '_wpfoai_base_measure',
				'label' => __( 'Base measure (e.g., 1 oz)', 'openai-product-feed-for-woo' ),
			]
		);

		woocommerce_wp_text_input(
			[
				'id'    => '_wpfoai_pricing_trend',
				'label' => __( 'Pricing trend (short text)', 'openai-product-feed-for-woo' ),
			]
		);

		echo '</div><div class="options_group">';

		woocommerce_wp_text_input(
			[
				'id'    => '_wpfoai_availability_date',
				'label' => __( 'Availability date (YYYY-MM-DD)', 'openai-product-feed-for-woo' ),
			]
		);

		woocommerce_wp_text_input(
			[
				'id'    => '_wpfoai_expiration_date',
				'label' => __( 'Expiration date (YYYY-MM-DD)', 'openai-product-feed-for-woo' ),
			]
		);

		echo '</div><div class="options_group">';

		woocommerce_wp_text_input(
			[
				'id'    => '_wpfoai_popularity_score',
				'label' => __( 'Popularity score (0–5)', 'openai-product-feed-for-woo' ),
			]
		);

		woocommerce_wp_text_input(
			[
				'id'    => '_wpfoai_return_rate',
				'label' => __( 'Return rate (%)', 'openai-product-feed-for-woo' ),
			]
		);

		woocommerce_wp_text_input(
			[
				'id'    => '_wpfoai_geo_price',
				'label' => __( 'Geo price (e.g., 79.99 USD (CA))', 'openai-product-feed-for-woo' ),
			]
		);

		woocommerce_wp_text_input(
			[
				'id'    => '_wpfoai_geo_availability',
				'label' => __( 'Geo availability (e.g., in_stock (TX), out_of_stock (NY))', 'openai-product-feed-for-woo' ),
			]
		);

		echo '</div><div class="options_group">';

		woocommerce_wp_text_input(
			[
				'id'    => '_wpfoai_related_product_id',
				'label' => __( 'Related product IDs (CSV)', 'openai-product-feed-for-woo' ),
			]
		);

		woocommerce_wp_select(
			[
				'id'      => '_wpfoai_relationship_type',
				'label'   => __( 'Relationship type', 'openai-product-feed-for-woo' ),
				'options' => [
					''                  => __( '— Optional —', 'openai-product-feed-for-woo' ),
					'part_of_set'       => 'part_of_set',
					'required_part'     => 'required_part',
					'often_bought_with' => 'often_bought_with',
					'substitute'        => 'substitute',
					'different_brand'   => 'different_brand',
					'accessory'         => 'accessory',
				],
			]
		);

		echo '</div>';

		$rest_url    = rest_url( 'wc/v3/openai-feed' );
		$preview_url = add_query_arg(
			[
				'product_id' => get_the_ID(),
				'_wpnonce'   => wp_create_nonce( 'wp_rest' ),
			],
			$rest_url
		);
		echo '<p style="margin: 8px 0;">' . esc_html__( 'Preview this product in the feed (admin-only):', 'openai-product-feed-for-woo' ) . ' ';
		echo '<a href="' . esc_url( $preview_url ) . '" target="_blank">' . esc_html__( 'Open preview', 'openai-product-feed-for-woo' ) . '</a></p>';

		echo '</div>';
	}

	/**
	 * Get placeholder text from product attribute
	 *
	 * Helper method to reduce redundancy when showing attribute fallbacks.
	 *
	 * @param \WC_Product|null $product Product object.
	 * @param string           $attribute_name Attribute name (e.g., 'pa_brand').
	 * @return string Placeholder text from attribute or empty string.
	 */
	private function get_attribute_placeholder( ?\WC_Product $product, string $attribute_name ): string {
		return ( $product && $product->get_attribute( $attribute_name ) ) ? $product->get_attribute( $attribute_name ) : '';
	}

	/**
	 * Save product fields when product is saved
	 *
	 * @param \WC_Product $product Product object being saved.
	 */
	public function save_product_fields( \WC_Product $product ): void {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! isset( $_POST['woocommerce_meta_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['woocommerce_meta_nonce'] ), 'woocommerce_save_data' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_products' ) ) {
			return;
		}

		$text_keys = [
			'_gtin',
			'_mpn',
			'_brand',
			'_wpfoai_material',
			'_wpfoai_color',
			'_wpfoai_size',
			'_wpfoai_size_system',
			'_wpfoai_gender',
			'_wpfoai_warning',
			'_wpfoai_q_and_a',
			'_wpfoai_condition',
			'_wpfoai_age_group',
			'_wpfoai_applicable_taxes_fees',
			'_wpfoai_unit_pricing_measure',
			'_wpfoai_base_measure',
			'_wpfoai_pricing_trend',
			'_wpfoai_availability_date',
			'_wpfoai_expiration_date',
			'_wpfoai_geo_price',
			'_wpfoai_geo_availability',
			'_wpfoai_related_product_id',
			'_wpfoai_relationship_type',
			'_wpfoai_popularity_score',
			'_wpfoai_return_rate',
		];

		foreach ( $text_keys as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$val = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
				$product->update_meta_data( $key, $val );
			}
		}

		$url_keys = [
			'_wpfoai_warning_url',
			'_wpfoai_video_link',
			'_wpfoai_model_3d_link',
		];

		foreach ( $url_keys as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$val = esc_url_raw( wp_unslash( $_POST[ $key ] ) );
				$product->update_meta_data( $key, $val );
			}
		}

		if ( isset( $_POST['_wpfoai_age_restriction'] ) ) {
			$product->update_meta_data( '_wpfoai_age_restriction', max( 0, absint( wp_unslash( $_POST['_wpfoai_age_restriction'] ) ) ) );
		}

		$product->update_meta_data( '_wpfoai_disable_search', isset( $_POST['_wpfoai_disable_search'] ) ? 'yes' : 'no' );
		$product->update_meta_data( '_wpfoai_disable_checkout', isset( $_POST['_wpfoai_disable_checkout'] ) ? 'yes' : 'no' );
	}
}
