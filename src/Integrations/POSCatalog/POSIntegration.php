<?php
/**
 * POS Catalog Integration class.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

namespace Automattic\WooCommerce\ProductFeedForOpenAI\Integrations\POSCatalog;

use Automattic\WooCommerce\ProductFeedForOpenAI\Core\DependencyManagement\Container;
use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\FeedInterface;
use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\FeedValidatorInterface;
use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\ProductMapperInterface;
use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\ProductWalker;
use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\WalkerProgress;
use Automattic\WooCommerce\ProductFeedForOpenAI\Integrations\IntegrationInterface;
use Automattic\WooCommerce\ProductFeedForOpenAI\Storage\JsonFileFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * POS Catalog Integration
 */
class POSIntegration implements IntegrationInterface {
	const FEED_GENERATION_ACTION = 'wpfoai_pos_catalog_feed_generation';

	/**
	 * Container instance.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Dependency injector.
	 *
	 * @param Container $container Dependency container.
	 */
	public function init( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Get the ID of the provider.
	 *
	 * @return string The ID of the provider.
	 */
	public function get_id(): string {
		return 'pos';
	}

	/**
	 * Register hooks for the integration.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'rest_api_init', [ $this, 'rest_api_init' ] );
		add_action( self::FEED_GENERATION_ACTION, [ $this, 'feed_generation_action' ] );
	}

	/**
	 * Initialize the REST API.
	 *
	 * @return void
	 */
	public function rest_api_init(): void {
		// Only load the controller when necessary.
		$this->container->get( ApiController::class )->register_routes();
	}

	/**
	 * Activate the integration.
	 *
	 * @return void
	 */
	public function activate(): void {
		// At the moment, there are no activation steps for the POS catalog.
	}

	/**
	 * Deactivate the integration.
	 *
	 * @return void
	 */
	public function deactivate(): void {
		// At the moment, there are no deactivation steps for the POS catalog.
	}

	/**
	 * Create a feed that is to be populated.
	 *
	 * @return FeedInterface The feed.
	 */
	public function create_feed(): FeedInterface {
		return new JsonFileFeed( 'pos-catalog-feed' );
	}

	/**
	 * Get the product mapper for the provider.
	 *
	 * @return ProductMapperInterface The product mapper.
	 */
	public function get_product_mapper(): ProductMapperInterface {
		return $this->container->get( ProductMapper::class );
	}

	/**
	 * Get the feed validator for the provider.
	 *
	 * @return FeedValidatorInterface|null The feed validator.
	 */
	public function get_feed_validator(): ?FeedValidatorInterface {
		return (
			new class implements FeedValidatorInterface {
				public function validate_entry( array $row, \WC_Product $product ): array {
					return [];
				}
			}
		);
	}

	public function generate_feed() {
		$status = get_transient( 'pos_feed_status' );

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
				'pos_feed_status',
				$status,
				DAY_IN_SECONDS
			);
		}

		$response = array_merge( [], $status );
		unset( $response['action_id'] );
		return $response;
	}

	public function feed_generation_action() {
		$status = get_transient( 'pos_feed_status' );

		if ( 'scheduled' === $status['status'] ) {
			$status['status'] = 'in_progress';
			set_transient( 'pos_feed_status', $status, DAY_IN_SECONDS );

			$feed = $this->create_feed();

			$walker = new ProductWalker(
				$this->get_product_mapper(),
				$this->get_feed_validator(),
				$feed
			);

			// Used while testing...
			$walker->set_batch_size( 5 );

			$walker->walk(
				function ( WalkerProgress $progress ) use ( $status, $feed ) {
					$this->update_feed_progress( $status, $progress, $feed );
				}
			);

			// Get the updated transient.
			$status = get_transient( 'pos_feed_status' );
			$status['status'] = 'completed';
			$status['url'] = $feed->get_file_url();
			set_transient( 'pos_feed_status', $status, DAY_IN_SECONDS );
		}
	}

	private function update_feed_progress( array $status, WalkerProgress $progress, FeedInterface $feed ) {
		$status['progress'] = round( ( $progress->processed_items / $progress->total_count ) * 100, 2 );
		$status['processed'] = $progress->processed_items;
		$status['total'] = $progress->total_count;

		set_transient( 'pos_feed_status', $status, DAY_IN_SECONDS );

		sleep( 2 );
	}
}
