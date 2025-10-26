<?php
/**
 *  Async Generator class.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

declare(strict_types=1);

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Integrations\POSCatalog;

use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\ProductWalker;
use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\WalkerProgress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Async Generator for the POS catalog.
 */
final class AsyncGenerator {
	/**
	 * The Action Scheduleraction hook for the feed generation.
	 *
	 * @var string
	 */
	const FEED_GENERATION_ACTION = 'wpfoai_pos_catalog_feed_generation';

	/**
	 * The transient key for the feed generation status.
	 *
	 * @var string
	 */
	const TRANSIENT_KEY = 'pos_feed_status';

	/**
	 * Integration instance.
	 *
	 * @var POSIntegration
	 */
	private $integration;

	/**
	 * Dependency injector.
	 *
	 * @param POSIntegration $integration The integration instance.
	 */
	public function init( POSIntegration $integration ) {
		$this->integration = $integration;
	}

	/**
	 * Register hooks for the async generator.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( self::FEED_GENERATION_ACTION, [ $this, 'feed_generation_action' ] );
	}

	/**
	 * Returns the current feed generation status.
	 * Initiates one if not already running.
	 *
	 * @return array The feed generation status.
	 */
	public function get_status(): array {
		$status = get_transient( self::TRANSIENT_KEY );

		if ( false === $status ) {
			// Clear all previous actions to avoid race conditions.
			as_unschedule_all_actions( self::FEED_GENERATION_ACTION );

			$action_id = as_schedule_single_action( time() + 10, self::FEED_GENERATION_ACTION, [] );

			$status = [
				'action_id' => $action_id,
				'status'    => 'scheduled',
				'progress'  => 0,
				'processed' => 0,
				'total'     => -1,
			];

			set_transient(
				self::TRANSIENT_KEY,
				$status,
				DAY_IN_SECONDS
			);
		}

		$response = array_merge( [], $status );
		unset( $response['action_id'] );
		return $response;
	}

	/**
	 * Action scheduler callback for the feed generation.
	 *
	 * @return void
	 */
	public function feed_generation_action() {
		$status = get_transient( self::TRANSIENT_KEY );

		if ( 'scheduled' !== $status['status'] ) {
			// We should log that something was not right here.
			return;
		}

		$status['status'] = 'in_progress';
		set_transient( self::TRANSIENT_KEY, $status, DAY_IN_SECONDS );

		$feed   = $this->integration->create_feed();
		$walker = new ProductWalker(
			$this->integration->get_product_mapper(),
			$this->integration->get_feed_validator(),
			$feed
		);

		// Used while testing...
		$walker->set_batch_size( 5 );
		$walker->walk(
			function ( WalkerProgress $progress ) use ( &$status ) {
				$status = $this->update_feed_progress( $status, $progress );
			}
		);

		// Store the final details.
		$status['status'] = 'completed';
		$status['url']    = $feed->get_file_url();
		set_transient( self::TRANSIENT_KEY, $status, DAY_IN_SECONDS );
	}

	/**
	 * Updates the feed progress while the feed is being generated.
	 *
	 * @param array          $status   The last previously known status.
	 * @param WalkerProgress $progress The progress of the walker.
	 * @return array                   Updated status of the feed generation.
	 */
	private function update_feed_progress( array $status, WalkerProgress $progress ): array {
		$status['progress']  = round( ( $progress->processed_items / $progress->total_count ) * 100, 2 );
		$status['processed'] = $progress->processed_items;
		$status['total']     = $progress->total_count;

		set_transient( self::TRANSIENT_KEY, $status, DAY_IN_SECONDS );

		// Add a bit of sleep to assist with testing.
		sleep( 4 );

		return $status;
	}
}
