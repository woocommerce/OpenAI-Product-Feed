<?php
/**
 *  Feed Generator Interface interface.
 *
 * @package OAPFW
 */

declare(strict_types=1);

namespace OAPFW\Core\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Feed generator interface
 */
interface FeedGeneratorInterface {

	/**
	 * Build complete feed.
	 *
	 * @return array Feed rows.
	 */
	public function build_feed(): array;

	/**
	 * Build feed for specific product ID.
	 *
	 * @param int $product_id The product ID.
	 * @return array Feed rows.
	 */
	public function build_for_product_id( int $product_id ): array;

	/**
	 * Serialize feed data to specified format.
	 *
	 * @param array       $rows The feed rows.
	 * @param string      $format The output format.
	 * @param string|null $content_type The content type (passed by reference).
	 * @return string Serialized data.
	 */
	public function serialize( array $rows, string $format, ?string &$content_type = null ): string;
}
