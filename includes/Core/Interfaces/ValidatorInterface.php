<?php

declare(strict_types=1);

namespace OAPFW\Core\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Feed validator interface
 */
interface ValidatorInterface {

	public function validateRow( array $row ): array;
	public function validateFeed( array $rows ): array;
}
