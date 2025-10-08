<?php

declare(strict_types=1);

namespace OAPFW\Admin\Controllers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles WooCommerce product data tab for OpenAI feed attributes
 */
class ProductFieldsController {

	/**
	 * Initialize product fields functionality
	 */
	public function init(): void {
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'addProductDataTab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'renderProductDataPanel' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'saveProductFields' ) );
	}

	/**
	 * Add OpenAI Feed tab to product data tabs
	 *
	 * @param array $tabs Existing product data tabs.
	 * @return array Modified tabs array.
	 */
	public function addProductDataTab( array $tabs ): array {
		$tabs['oapfw'] = array(
			'label'    => __( 'OpenAI Feed', 'openai-product-feed-for-woo' ),
			'target'   => 'oapfw_product_data',
			'class'    => array( 'show_if_simple', 'show_if_variable' ),
			'priority' => 80,
		);
		return $tabs;
	}

	/**
	 * Render the OpenAI Feed product data panel
	 */
	public function renderProductDataPanel(): void {
		echo '<div id="oapfw_product_data" class="panel woocommerce_options_panel hidden">';
		
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( get_the_ID() ) : null;
		
		echo '<p class="description">' . esc_html__( 'These fields inherit from WooCommerce global settings and existing product data (title, pricing, attributes). Set a value here only if you want to override the inherited value.', 'openai-product-feed-for-woo' ) . '</p>';
		echo '<div class="options_group">';

		// Product identifiers
		woocommerce_wp_text_input(
			array(
				'id'          => '_gtin',
				'label'       => __( 'GTIN', 'openai-product-feed-for-woo' ),
				'desc_tip'    => true,
				'description' => __( '8–14 digits; no spaces or dashes.', 'openai-product-feed-for-woo' ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => '_mpn',
				'label'       => __( 'MPN', 'openai-product-feed-for-woo' ),
				'desc_tip'    => true,
				'description' => __( 'Required if GTIN is not provided.', 'openai-product-feed-for-woo' ),
			)
		);

		$brand_placeholder = ( $product && $product->get_attribute( 'pa_brand' ) ) ? $product->get_attribute( 'pa_brand' ) : '';
		woocommerce_wp_text_input(
			array(
				'id'          => '_brand',
				'label'       => __( 'Brand (fallback)', 'openai-product-feed-for-woo' ),
				'desc_tip'    => true,
				'description' => __( 'Used if attribute pa_brand is not set.', 'openai-product-feed-for-woo' ),
				'placeholder' => $brand_placeholder,
			)
		);

		echo '</div><div class="options_group">';

		// ChatGPT flags
		woocommerce_wp_checkbox(
			array(
				'id'          => '_oapfw_enable_search',
				'label'       => __( 'Enable search (ChatGPT)', 'openai-product-feed-for-woo' ),
				'description' => __( 'Overrides global default for this product.', 'openai-product-feed-for-woo' ),
			)
		);

		woocommerce_wp_checkbox(
			array(
				'id'          => '_oapfw_enable_checkout',
				'label'       => __( 'Enable checkout (ChatGPT)', 'openai-product-feed-for-woo' ),
				'description' => __( 'Requires enable search.', 'openai-product-feed-for-woo' ),
			)
		);

		echo '</div><div class="options_group">';

		// Product condition and age group
		woocommerce_wp_select(
			array(
				'id'          => '_oapfw_condition',
				'label'       => __( 'Condition', 'openai-product-feed-for-woo' ),
				'options'     => array(
					''            => __( '— Select —', 'openai-product-feed-for-woo' ),
					'new'         => 'new',
					'refurbished' => 'refurbished',
					'used'        => 'used',
				),
				'description' => __( 'Required if not new.', 'openai-product-feed-for-woo' ),
			)
		);

		$age_placeholder = ( $product && $product->get_attribute( 'pa_age_group' ) ) ? $product->get_attribute( 'pa_age_group' ) : '';
		woocommerce_wp_select(
			array(
				'id'          => '_oapfw_age_group',
				'label'       => __( 'Age group', 'openai-product-feed-for-woo' ),
				'options'     => array(
					''        => __( '— Optional —', 'openai-product-feed-for-woo' ),
					'newborn' => 'newborn',
					'infant'  => 'infant',
					'toddler' => 'toddler',
					'kids'    => 'kids',
					'adult'   => 'adult',
				),
				'placeholder' => $age_placeholder,
			)
		);

		echo '</div><div class="options_group">';

		// Compliance fields
		woocommerce_wp_text_input(
			array(
				'id'                => '_oapfw_age_restriction',
				'label'             => __( 'Age restriction', 'openai-product-feed-for-woo' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '1',
				),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'    => '_oapfw_warning',
				'label' => __( 'Warning text', 'openai-product-feed-for-woo' ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => '_oapfw_warning_url',
				'label'       => __( 'Warning URL', 'openai-product-feed-for-woo' ),
				'placeholder' => 'https://',
			)
		);

		echo '</div><div class="options_group">';

		// Media extras
		woocommerce_wp_text_input(
			array(
				'id'          => '_oapfw_video_link',
				'label'       => __( 'Product video URL', 'openai-product-feed-for-woo' ),
				'placeholder' => 'https://',
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => '_oapfw_model_3d_link',
				'label'       => __( '3D model URL', 'openai-product-feed-for-woo' ),
				'placeholder' => 'https://',
			)
		);

		echo '</div><div class="options_group">';

		// Q&A
		woocommerce_wp_textarea_input(
			array(
				'id'    => '_oapfw_q_and_a',
				'label' => __( 'Q&A (plain text)', 'openai-product-feed-for-woo' ),
				'rows'  => 3,
			)
		);

		echo '</div><div class="options_group">';

		// Pricing extras
		woocommerce_wp_text_input(
			array(
				'id'    => '_oapfw_applicable_taxes_fees',
				'label' => __( 'Additional taxes/fees (e.g., 7 USD)', 'openai-product-feed-for-woo' ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'    => '_oapfw_unit_pricing_measure',
				'label' => __( 'Unit pricing measure (e.g., 16 oz)', 'openai-product-feed-for-woo' ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'    => '_oapfw_base_measure',
				'label' => __( 'Base measure (e.g., 1 oz)', 'openai-product-feed-for-woo' ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'    => '_oapfw_pricing_trend',
				'label' => __( 'Pricing trend (short text)', 'openai-product-feed-for-woo' ),
			)
		);

		echo '</div><div class="options_group">';

		// Availability extras
		woocommerce_wp_text_input(
			array(
				'id'    => '_oapfw_availability_date',
				'label' => __( 'Availability date (YYYY-MM-DD)', 'openai-product-feed-for-woo' ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'    => '_oapfw_expiration_date',
				'label' => __( 'Expiration date (YYYY-MM-DD)', 'openai-product-feed-for-woo' ),
			)
		);

		echo '</div><div class="options_group">';

		// Performance & Geo
		woocommerce_wp_text_input(
			array(
				'id'    => '_oapfw_popularity_score',
				'label' => __( 'Popularity score (0–5)', 'openai-product-feed-for-woo' ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'    => '_oapfw_return_rate',
				'label' => __( 'Return rate (%)', 'openai-product-feed-for-woo' ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'    => '_oapfw_geo_price',
				'label' => __( 'Geo price (e.g., 79.99 USD (CA))', 'openai-product-feed-for-woo' ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'    => '_oapfw_geo_availability',
				'label' => __( 'Geo availability (e.g., in_stock (TX), out_of_stock (NY))', 'openai-product-feed-for-woo' ),
			)
		);

		echo '</div><div class="options_group">';

		// Related products
		woocommerce_wp_text_input(
			array(
				'id'    => '_oapfw_related_product_id',
				'label' => __( 'Related product IDs (CSV)', 'openai-product-feed-for-woo' ),
			)
		);

		woocommerce_wp_select(
			array(
				'id'      => '_oapfw_relationship_type',
				'label'   => __( 'Relationship type', 'openai-product-feed-for-woo' ),
				'options' => array(
					''                    => __( '— Optional —', 'openai-product-feed-for-woo' ),
					'part_of_set'         => 'part_of_set',
					'required_part'       => 'required_part',
					'often_bought_with'   => 'often_bought_with',
					'substitute'          => 'substitute',
					'different_brand'     => 'different_brand',
					'accessory'           => 'accessory',
				),
			)
		);

		echo '</div>';

		// Preview link
		$rest_url = rest_url( 'oapfw/v1/feed' );
		echo '<p style="margin: 8px 0;">' . esc_html__( 'Preview this product in the feed (admin-only):', 'openai-product-feed-for-woo' ) . ' ';
		echo '<a href="' . esc_url( add_query_arg( array( 'product_id' => get_the_ID() ), $rest_url ) ) . '" target="_blank">' . esc_html__( 'Open preview', 'openai-product-feed-for-woo' ) . '</a></p>';

		echo '</div>';
	}

	/**
	 * Save product fields when product is saved
	 *
	 * @param \WC_Product $product Product object.
	 */
	public function saveProductFields( \WC_Product $product ): void {
		// Text fields
		$text_keys = array(
			'_gtin',
			'_mpn',
			'_brand',
			'_oapfw_warning',
			'_oapfw_q_and_a',
			'_oapfw_condition',
			'_oapfw_age_group',
			'_oapfw_applicable_taxes_fees',
			'_oapfw_unit_pricing_measure',
			'_oapfw_base_measure',
			'_oapfw_pricing_trend',
			'_oapfw_availability_date',
			'_oapfw_expiration_date',
			'_oapfw_geo_price',
			'_oapfw_geo_availability',
			'_oapfw_related_product_id',
			'_oapfw_relationship_type',
			'_oapfw_popularity_score',
			'_oapfw_return_rate',
		);

		foreach ( $text_keys as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$val = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
				$product->update_meta_data( $key, $val );
			}
		}

		// URL fields
		$url_keys = array(
			'_oapfw_warning_url',
			'_oapfw_video_link',
			'_oapfw_model_3d_link',
		);

		foreach ( $url_keys as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$val = esc_url_raw( wp_unslash( $_POST[ $key ] ) );
				$product->update_meta_data( $key, $val );
			}
		}

		// Number fields
		if ( isset( $_POST['_oapfw_age_restriction'] ) ) {
			$product->update_meta_data( '_oapfw_age_restriction', max( 0, absint( wp_unslash( $_POST['_oapfw_age_restriction'] ) ) ) );
		}

		// Checkbox fields (store as 'true' or '')
		$product->update_meta_data( '_oapfw_enable_search', isset( $_POST['_oapfw_enable_search'] ) ? 'true' : '' );
		$product->update_meta_data( '_oapfw_enable_checkout', isset( $_POST['_oapfw_enable_checkout'] ) ? 'true' : '' );
	}
}