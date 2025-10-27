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
	 * The Action Scheduler action hook for the feed generation.
	 *
	 * @var string
	 */
	const FEED_GENERATION_ACTION = 'wpfoai_pos_catalog_feed_generation';

	/**
	 * The Action Scheduler action hook for the feed deletion.
	 *
	 * @var string
	 */
	const FEED_DELETION_ACTION = 'wpfoai_pos_catalog_feed_deletion';

	/**
	 * The option key for the feed generation status.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'pos_feed_status';

	/**
	 * Feed expiry time, once completed.
	 * If the feed is not downloaded within this timeframe, a new one will need to be generated.
	 *
	 * @var int
	 */
	const FEED_EXPIRY = 20 * MINUTE_IN_SECONDS;

	/**
	 * Possible states of generation.
	 */
	const STATE_SCHEDULED   = 'scheduled';
	const STATE_IN_PROGRESS = 'in_progress';
	const STATE_COMPLETED   = 'completed';

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
		add_action( self::FEED_DELETION_ACTION, [ $this, 'feed_deletion_action' ] );
	}

	/**
	 * Returns the current feed generation status.
	 * Initiates one if not already running.
	 *
	 * @return array The feed generation status.
	 */
	public function get_status(): array {
		$status = get_option( self::OPTION_KEY );

		if ( false === $status ) {
			// Clear all previous actions to avoid race conditions.
			as_unschedule_all_actions( self::FEED_GENERATION_ACTION );

			// Add a bit of delay to avoid race conditions.
			$delay     = 10;
			$action_id = as_schedule_single_action( time() + $delay, self::FEED_GENERATION_ACTION, [] );

			$status = [
				'action_id' => $action_id,
				'state'     => self::STATE_SCHEDULED,
				'progress'  => 0,
				'processed' => 0,
				'total'     => -1,
			];

			update_option(
				self::OPTION_KEY,
				$status
			);
		}

		return $status;
	}

	/**
	 * Action scheduler callback for the feed generation.
	 *
	 * @return void
	 */
	public function feed_generation_action() {
		$status = get_option( self::OPTION_KEY );

		if ( ! is_array( $status ) || ! isset( $status['state'] ) || self::STATE_SCHEDULED !== $status['state'] ) {
			// We should log that something was not right here.
			return;
		}

		$status['state'] = self::STATE_IN_PROGRESS;
		update_option( self::OPTION_KEY, $status );

		$feed   = $this->integration->create_feed();
		$walker = new ProductWalker(
			$this->integration->get_product_mapper(),
			$this->integration->get_feed_validator(),
			$feed
		);

		$walker->walk(
			function ( WalkerProgress $progress ) use ( &$status ) {
				$status = $this->update_feed_progress( $status, $progress );
				update_option( self::OPTION_KEY, $status );
			}
		);

		// Store the final details.
		$status['state'] = self::STATE_COMPLETED;
		$status['url']   = $feed->get_file_url();
		$status['path']  = $feed->get_file_path();
		update_option( self::OPTION_KEY, $status );

		// Schedule another action to delete the file after the expiry time.
		as_schedule_single_action(
			time() + self::FEED_EXPIRY,
			self::FEED_DELETION_ACTION,
			[ 'path' => $feed->get_file_path() ]
		);
	}

	/**
	 * Forces a regeneration of the feed.
	 *
	 * @return array The feed generation status.
	 * @throws \Exception When there is a reason why the regeneration cannot be forced.
	 */
	public function force_regeneration(): array {
		$status = get_option( self::OPTION_KEY );

		// If there is no option, there is nothing to force.
		if ( false === $status ) {
			return $this->get_status();
		}

		switch ( $status['state'] ?? '' ) {
			case self::STATE_SCHEDULED:
				// If generation is scheduled, we can just let it be and return the current status.
				// It should start shortly.
				return $status;

			case self::STATE_IN_PROGRESS:
				throw new \Exception( 'Feed generation is already in progress and cannot be stopped.' );

			case self::STATE_COMPLETED:
				// Delete the existing file, clear the option and let generation start again.
				wp_delete_file( $status['path'] );
				delete_option( self::OPTION_KEY );
				return $this->get_status();

			default:
				throw new \Exception( 'Unknown feed generation state.' );
		}
	}

	/**
	 * Action scheduler callback for the feed deletion after expiry.
	 *
	 * @param array $args The arguments passed to the action.
	 * @return void
	 */
	public function feed_deletion_action( array $args ) {
		$path = $args['path'];
		wp_delete_file( $path );
		delete_option( self::OPTION_KEY );
	}

	/**
	 * Updates the feed progress while the feed is being generated.
	 *
	 * @param array          $status   The last previously known status.
	 * @param WalkerProgress $progress The progress of the walker.
	 * @return array                   Updated status of the feed generation.
	 */
	private function update_feed_progress( array $status, WalkerProgress $progress ): array {
		$status['progress']  = $progress->total_count > 0
			? round( ( $progress->processed_items / $progress->total_count ) * 100, 2 )
			: 0;
		$status['processed'] = $progress->processed_items;
		$status['total']     = $progress->total_count;
		return $status;
	}
}
