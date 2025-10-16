<?php

declare(strict_types=1);

namespace OAPFW\Core\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Feed generator interface
 */
interface FeedGeneratorInterface {

	public function buildFeed(): array;
	public function buildForProductId( int $product_id ): array;
	public function serialize( array $rows, string $format, ?string &$content_type = null ): string;
}
