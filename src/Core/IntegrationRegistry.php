<?php
/**
 * Integration Registry class.
 *
 * Stores all provider integrations that are available.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

declare(strict_types=1);

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Core;

use Automattic\WooCommerce\ProductFeedForOpenAI\Platforms\IntegrationInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * IntegrationRegistry
 */
class IntegrationRegistry {
	/**
	 * List of all available Integrations.
	 *
	 * @var array<string,IntegrationInterface>
	 */
	private array $integrations = [];

	/**
	 * Register a Integration.
	 *
	 * @param IntegrationInterface $integration The integration to register.
	 */
	public function register_integration( IntegrationInterface $integration ): void {
		$this->integrations[ $integration->get_id() ] = $integration;
	}

	/**
	 * Get a Integration by ID.
	 *
	 * @param string $id The ID of the Integration.
	 * @return IntegrationInterface|null The Integration, or null if it is not registered.
	 */
	public function get_integration( string $id ): ?IntegrationInterface {
		return $this->integrations[ $id ] ?? null;
	}
}
