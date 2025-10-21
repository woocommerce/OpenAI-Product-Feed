<?php
/**
 * Product Walker class.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

declare(strict_types=1);

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Feed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Walker for products.
 */
class ProductWalker {
	/**
	 * The product mapper.
	 *
	 * @var ProductMapperInterface
	 */
	private $mapper;

	/**
	 * The feed.
	 *
	 * @var FeedInterface
	 */
	private $feed;

	/**
	 * The feed validator.
	 *
	 * @var FeedValidatorInterface
	 */
	private $validator;

	/**
	 * Class constructor.
	 *
	 * This class will not be available through DI. Instead, it needs to be instantiated directly.
	 *
	 * @param ProductMapperInterface $mapper The product mapper.
	 * @param FeedValidatorInterface $validator The feed validator.
	 * @param FeedInterface          $feed The feed.
	 */
	public function __construct(
		ProductMapperInterface $mapper,
		FeedValidatorInterface $validator,
		FeedInterface $feed
	) {
		$this->mapper    = $mapper;
		$this->validator = $validator;
		$this->feed      = $feed;
	}

	/**
	 * Walks through all products.
	 *
	 * @param int $extend_execution_time_limit The number of seconds to extend the execution time limit per batch.
	 * @return int The total number of products walked through.
	 */
	public function walk( int $extend_execution_time_limit = 0 ): int {
		$page     = 1;
		$per_page = 100;
		$total    = 0;

		/**
		 * Allows the base arguments for querying products for product feeds to be changed.
		 *
		 * Variable products are not included by default, as their variations will be included.
		 *
		 * @since 0.1.0
		 *
		 * @param array $args The arguments to pass to wc_get_products().
		 * @return array
		 */
		$args = apply_filters(
			'wpfoai_product_feed_args',
			[
				'status' => [ 'publish' ],
				'type'   => [ 'simple','variation' ],
				'return' => 'objects',
			]
		);

		do {
			$iterated = $this->iterate( $args, $page, $per_page );
			$total   += $iterated;

			if ( $extend_execution_time_limit > 0 ) {
				set_time_limit( $extend_execution_time_limit );
			}
		} while ( $iterated === $per_page );

		return $total;
	}

	/**
	 * Iterates through a batch of products.
	 *
	 * @param array $args The arguments to pass to wc_get_products().
	 * @param int   $page The page number to iterate through.
	 * @param int   $limit The maximum number of products to iterate through.
	 * @return int The number of products iterated through.
	 */
	public function iterate( array $args = [], int $page = 1, int $limit = 100 ): int {
		$products = wc_get_products(
			[
				...$args,
				'page'  => $page,
				'limit' => $limit,
			]
		);

		foreach ( $products as $product ) {
			$mapped_data = $this->mapper->map_product( $product );

			if ( ! empty( $this->validator->validate_entry( $mapped_data, $product ) ) ) {
				continue;
			}

			$this->feed->add_entry( $mapped_data );
		}

		return count( $products );
	}
}
