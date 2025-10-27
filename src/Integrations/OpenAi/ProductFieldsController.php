<?php
/**
 *  Product Fields Controller class.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

declare(strict_types=1);

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Integrations\OpenAI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles WooCommerce product data tab for OpenAI feed attributes
 */
class ProductFieldsController {

	/**
	 * Meta key for Manufacturer Part Number (MPN)
	 */
	public const KEY_MPN = '_wpfoai_mpn';

	/**
	 * Meta key for product condition
	 */
	public const KEY_CONDITION = '_wpfoai_condition';

	/**
	 * Meta key for disabling product in search
	 */
	public const KEY_DISABLE_SEARCH = '_wpfoai_disable_search';

	/**
	 * Meta key for disabling product in checkout
	 */
	public const KEY_DISABLE_CHECKOUT = '_wpfoai_disable_checkout';

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
			'label'    => __( 'OpenAI Feed', 'woocommerce-product-feed-openai' ),
			'target'   => 'wpfoai_product_data',
			'class'    => [ 'show_if_simple', 'show_if_variable' ],
			'priority' => 80,
		];
		return $tabs;
	}

	/**
	 * Render the OpenAI Feed product data panel
	 */
	public function render_product_data_panel(): void {
		include __DIR__ . '/Templates/ProductDataPanel.php';
	}

	/**
	 * Save product fields when product is saved
	 *
	 * @param \WC_Product $product Product object being saved.
	 */
	public function save_product_fields( \WC_Product $product ): void {
		if ( ! isset( $_POST['woocommerce_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_products' ) ) {
			return;
		}

		$text_keys = [
			self::KEY_MPN,
			self::KEY_CONDITION,
		];

		foreach ( $text_keys as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$val = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
				$product->update_meta_data( $key, $val );
			}
		}

		$product->update_meta_data( self::KEY_DISABLE_SEARCH, isset( $_POST[ self::KEY_DISABLE_SEARCH ] ) ? 'yes' : 'no' );
		$product->update_meta_data( self::KEY_DISABLE_CHECKOUT, isset( $_POST[ self::KEY_DISABLE_CHECKOUT ] ) ? 'yes' : 'no' );
	}
}
