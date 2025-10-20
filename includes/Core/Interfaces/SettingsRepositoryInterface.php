<?php
/**
 *  Settings Repository Interface interface.
 *
 * @package OAPFW
 */

declare(strict_types=1);

namespace OAPFW\Core\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings repository interface
 */
interface SettingsRepositoryInterface {
	/**
	 * Get a setting value.
	 *
	 * @param string $key The setting key.
	 * @param mixed  $default_value Default value if key not found.
	 * @return mixed The setting value.
	 */
	public function get( string $key, $default_value = '' );
}
