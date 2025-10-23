<?php
/**
 * OpenAI Feed product data panel template
 *
 * This template is included from ProductFieldsController::render_product_data_panel()
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

declare(strict_types=1);

use Automattic\WooCommerce\ProductFeedForOpenAI\Platforms\OpenAI\ProductFieldsController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$product = wc_get_product( get_the_ID() );
if ( ! $product instanceof \WC_Product ) {
	return;
}

?>

<div id="wpfoai_product_data" class="panel woocommerce_options_panel hidden">

	<div class="options_group">

		<?php
		woocommerce_wp_checkbox(
			[
				'id'          => ProductFieldsController::KEY_DISABLE_SEARCH,
				'value'       => $product->get_meta( ProductFieldsController::KEY_DISABLE_SEARCH ) === 'yes' ? 'yes' : 'no',
				'label'       => __( 'Disable search', 'woocommerce-product-feed-openai' ),
				'description' => __( 'Overrides global default for this product.', 'woocommerce-product-feed-openai' ),
			]
		);

		woocommerce_wp_checkbox(
			[
				'id'          => ProductFieldsController::KEY_DISABLE_CHECKOUT,
				'value'       => $product->get_meta( ProductFieldsController::KEY_DISABLE_CHECKOUT ) === 'yes' ? 'yes' : 'no',
				'label'       => __( 'Disable checkout', 'woocommerce-product-feed-openai' ),
				'description' => __( 'Requires search to be enabled.', 'woocommerce-product-feed-openai' ),
			]
		);
		?>

	</div>

	<div class="options_group">

		<?php
		woocommerce_wp_text_input(
			[
				'id'          => ProductFieldsController::KEY_MPN,
				'label'       => __( 'MPN', 'woocommerce-product-feed-openai' ),
				'desc_tip'    => true,
				'description' => __( 'Manufacturer Part Number. Required if GTIN does not exist.', 'woocommerce-product-feed-openai' ),
			]
		);
		?>

	</div>

	<div class="options_group">

		<?php
		woocommerce_wp_select(
			[
				'id'          => ProductFieldsController::KEY_CONDITION,
				'label'       => __( 'Condition', 'woocommerce-product-feed-openai' ),
				'options'     => [
					''            => __( 'new (default)', 'woocommerce-product-feed-openai' ),
					'new'         => __( 'new', 'woocommerce-product-feed-openai' ),
					'refurbished' => __( 'refurbished', 'woocommerce-product-feed-openai' ),
					'used'        => __( 'used', 'woocommerce-product-feed-openai' ),
				],
				'desc_tip'    => true,
				'description' => __( 'Only required if product condition differs from new.', 'woocommerce-product-feed-openai' ),
			]
		);
		?>

	</div>

	<?php
	$rest_url    = rest_url( 'wc/v3/openai-feed' );
	$preview_url = add_query_arg(
		[
			'product_id' => $product->get_id(),
			'_wpnonce'   => wp_create_nonce( 'wp_rest' ),
		],
		$rest_url
	);
	?>
	<p style="margin: 8px 0;">
		<?php echo esc_html__( 'Preview this product in the feed (admin-only):', 'woocommerce-product-feed-openai' ); ?>
		<a href="<?php echo esc_url( $preview_url ); ?>" target="_blank">
			<?php echo esc_html__( 'Open preview', 'woocommerce-product-feed-openai' ); ?>
		</a>
	</p>

</div>
