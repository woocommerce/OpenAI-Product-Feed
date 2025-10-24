<?php
/**
 *  Dev Helpers class.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

declare(strict_types=1);

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Platforms\OpenAI;

use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dev Helpers class.
 */
class DevHelpers {
	/**
	 * Product mapper instance.
	 *
	 * @var ProductMapper
	 */
	private ProductMapper $mapper;

	/**
	 * Feed validator instance.
	 *
	 * @var FeedValidator
	 */
	private FeedValidator $validator;

	/**
	 * The ID of the custom column.
	 *
	 * @var string
	 */
	const COLUMN_ID = 'openai_catalog_status';

	/**
	 * Dependency injector.
	 *
	 * @param ProductMapper $mapper    The product mapper.
	 * @param FeedValidator $validator The feed validator.
	 */
	public function init(
		ProductMapper $mapper,
		FeedValidator $validator,
	) {
		$this->mapper    = $mapper;
		$this->validator = $validator;
	}

	/**
	 * Initialize the dev helpers.
	 */
	public function initialize() {
		add_action( 'admin_init', [ $this, 'add_admin_hooks' ] );
	}

	/**
	 * Add admin hooks.
	 */
	public function add_admin_hooks() {
		/**
		 * Filter to enable/disable dev helpers.
		 *
		 * @since 1.0.0
		 * @param bool $enabled Whether to enable dev helpers.
		 * @return bool Whether to enable dev helpers.
		 */
		if ( ! apply_filters( 'wpfoai_dev_helpers_enabled', false ) ) {
			return;
		}

		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
		add_filter( 'manage_edit-product_columns', [ $this, 'add_custom_product_column' ] );
		add_action( 'manage_product_posts_custom_column', [ $this, 'render_custom_product_column' ], 10, 2 );
		add_action( 'admin_head', [ $this, 'add_custom_column_styles' ] );
	}

	/**
	 * Add meta boxes to the product edit screen.
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'wpfoai_dev_helpers',
			'OpenAI Product Feed',
			[ $this, 'render_meta_box' ],
			'product',
			'side',
			'high'
		);
	}

	/**
	 * Render the meta box content.
	 *
	 * @param WP_Post $post The post object.
	 */
	public function render_meta_box( WP_Post $post ) {
		if ( 'auto-draft' === $post->post_status ) {
			echo wp_kses_post( wpautop( 'Save the product to see details' ) );
			return;
		}

		// phpcs:disable VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$product     = wc_get_product( $post->ID );
		$mapped_data = $this->mapper->map_product( $product );
		$errors      = $this->validator->validate_entry( $mapped_data, $product );

		include __DIR__ . '/Templates/DevMetaBox.php';
		// phpcs:enable VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
	}

	/**
	 * Add custom column to the products table.
	 *
	 * @param array $columns The existing columns.
	 * @return array The modified columns.
	 */
	public function add_custom_product_column( $columns ) {
		$new_columns = [];
		foreach ( $columns as $key => $column ) {
			// Insert the custom column before the 'date' column.
			if ( 'date' === $key ) {
				$new_columns[ self::COLUMN_ID ] = 'OpenAI';
			}
			$new_columns[ $key ] = $column;
		}
		return $new_columns;
	}

	/**
	 * Render the custom column content.
	 *
	 * @param string $column  The column name.
	 * @param int    $post_id The post ID.
	 *
	 * @phpcs:disable VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- $post_id required by hook signature.
	 */
	public function render_custom_product_column( $column, $post_id ) {
		if ( self::COLUMN_ID !== $column ) {
			return;
		}

		$product     = wc_get_product( $post_id );
		$mapped_data = $this->mapper->map_product( $product );
		$errors      = $this->validator->validate_entry( $mapped_data, $product );

		if ( 'false' === $mapped_data['enable_search'] ) {
			echo '<span class="dashicons dashicons-no-alt" title="Hidden from search"></span>';
			return;
		}

		if ( empty( $errors ) ) {
			echo '<span class="dashicons dashicons-yes" title="Visible in search"></span>';
			return;
		}

		?>
		<div class="product-feed-indicator-container">
			<a href="javascript:void(0)" class="product-feed-error-indicator"><span class="dashicons dashicons-warning" style="color: red;"></span></a>
			<div class="product-feed-errors">
				<ul>
					<?php foreach ( $errors as $error ) : ?>
						<li><?php echo wp_kses_post( $error ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Add custom styles for the custom column.
	 */
	public function add_custom_column_styles() {
		$screen = get_current_screen();
		if ( ! $screen || 'edit-product' !== $screen->id ) {
			return;
		}
		?>
		<style>
		.wp-list-table .column-openai_catalog_status {
			width: 100px;
		}

		.product-feed-indicator-container {
			position: relative;
		}

		.product-feed-error-indicator + .product-feed-errors {
			display: none;
			position: absolute;
			top: 110%;
			left: 0;
			background-color: #fff;
			border: 1px solid #ccc;
			z-index: 1000;
			width: auto;
		}

		.product-feed-errors ul {
			padding: 0;
			margin: 0;
		}

		.product-feed-error-indicator:focus + .product-feed-errors {
			display: block;
		}

		.product-feed-errors {
			list-style: none;
			padding: 0;
			margin: 0;
		}

		.product-feed-errors li {
			margin: 0;
			padding: 5px;
		}

		.product-feed-errors li + li {
			border-top: 1px solid #ccc;
		}
		</style>
		<?php
	}
}
