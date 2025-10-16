<?php
/**
 *  Validator Interface interface.
 *
 * @package OAPFW
 */

declare(strict_types=1);

namespace OAPFW\Core\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Feed validator interface
 */
interface ValidatorInterface {

	/**
	 * Validate a single row of data.
	 *
	 * @param array $row The row data to validate.
	 * @return array Validation results.
	 */
	public function validate_row( array $row ): array;

	/**
	 * Validate an entire feed.
	 *
	 * @param array $rows The feed rows to validate.
	 * @return array Validation results.
	 */
	public function validate_feed( array $rows ): array;
}
