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
	 * @return int The total number of products walked through.
	 */
	public function walk(): int {
		$page     = 1;
		$per_page = 100;
		$total    = 0;

		do {
			$iterated = $this->iterate( $page, $per_page );
			$total   += $iterated;
		} while ( $iterated === $per_page );

		return $total;
	}

	/**
	 * Iterates through a batch of products.
	 *
	 * @param int $page The page number to iterate through.
	 * @param int $limit The maximum number of products to iterate through.
	 * @return int The number of products iterated through.
	 */
	public function iterate( int $page, int $limit = 100 ): int {
		$products = wc_get_products(
			[
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
