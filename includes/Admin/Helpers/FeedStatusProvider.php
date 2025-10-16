<?php

declare(strict_types=1);

namespace OAPFW\Admin\Helpers;

use OAPFW\Core\Interfaces\FeedGeneratorInterface;
use OAPFW\Core\Interfaces\ValidatorInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides feed status information and validation services
 * 
 * Handles feed validation, scheduled push tracking, and status reporting
 * for the admin interface.
 */
class FeedStatusProvider {

	/**
	 * Hook name for scheduled feed push actions
	 */
	const SCHEDULED_ACTION_HOOK = 'oapfw_push_feed_event';

	/**
	 * Feed generator instance
	 *
	 * @var FeedGeneratorInterface
	 */
	private FeedGeneratorInterface $feedGenerator;

	/**
	 * Feed validator instance
	 *
	 * @var ValidatorInterface
	 */
	private ValidatorInterface $validator;

	/**
	 * WooCommerce logger instance
	 *
	 * @var \WC_Logger_Interface|null
	 */
	private $logger;

	/**
	 * Initialize feed status provider
	 *
	 * @param FeedGeneratorInterface $feedGenerator Feed generator instance.
	 * @param ValidatorInterface     $validator     Feed validator instance.
	 */
	public function __construct( FeedGeneratorInterface $feedGenerator, ValidatorInterface $validator ) {
		$this->feedGenerator = $feedGenerator;
		$this->validator = $validator;
		$this->logger = function_exists( 'wc_get_logger' ) ? wc_get_logger() : null;
	}

	/**
	 * Get timestamp of next scheduled feed push
	 *
	 * @return int|null Unix timestamp of next push, or null if none scheduled.
	 */
	public function getNextScheduledPush(): ?int {
		if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
			return null;
		}

		$scheduled_actions = as_get_scheduled_actions(
			array(
				'hook'     => self::SCHEDULED_ACTION_HOOK,
				'per_page' => 1,
				'order'    => 'ASC',
			)
		);
		
		if ( ! empty( $scheduled_actions ) && isset( $scheduled_actions[0] ) ) {
			return $scheduled_actions[0]->get_schedule()->get_date()->getTimestamp();
		}

		return null;
	}

	/**
	 * Validate the current feed and log results
	 *
	 * Generates the feed, validates it, caches results, and logs issues/success.
	 *
	 * @return array Array of validation issues (empty if valid).
	 */
	public function validateFeedNow(): array {
		$rows = $this->feedGenerator->buildFeed();
		$issues = $this->validator->validateFeed( $rows );
		
		if ( $issues ) {
			set_transient( 'oapfw_last_validation', $issues, 5 * MINUTE_IN_SECONDS );
			
			// Log validation issues
			if ( $this->logger ) {
				$issue_count = count( $issues );
				$total_individual_issues = array_sum( array_map( function( $issue ) { 
					return count( $issue['issues'] ?? array() ); 
				}, $issues ) );
				
				$this->logger->warning( "Feed validation failed: {$total_individual_issues} validation issues across {$issue_count} items", array( 'source' => 'oapfw' ) );
				
				foreach ( $issues as $issue ) {
					$product_id = $issue['id'] ?? 'unknown';
					$product_issues = $issue['issues'] ?? array();
					
					if ( $product_id === 'feed' ) {
						// Feed-level issues (like empty feed)
						foreach ( $product_issues as $feed_issue ) {
							$this->logger->warning( "Feed validation: {$feed_issue}", array( 'source' => 'oapfw' ) );
						}
					} else {
						// Product-level issues - log each individual validation issue
						foreach ( $product_issues as $product_issue ) {
							$this->logger->warning( "Product validation failed for ID {$product_id}: {$product_issue}", array( 'source' => 'oapfw' ) );
						}
					}
				}
			}
		} else {
			delete_transient( 'oapfw_last_validation' );
			
			// Log successful validation
			if ( $this->logger ) {
				$product_count = count( $rows );
				$this->logger->info( "Feed validation passed: {$product_count} products validated successfully", array( 'source' => 'oapfw' ) );
			}
		}
		
		return $issues;
	}

	/**
	 * Get validation issues
	 *
	 * @param bool $fresh Whether to run fresh validation or use cached results.
	 * @return array Array of validation issues.
	 */
	public function getValidationIssues( bool $fresh = true ): array {
		if ( $fresh ) {
			return $this->validateFeedNow();
		}
		
		$issues = get_transient( 'oapfw_last_validation' );
		return ! empty( $issues ) && is_array( $issues ) ? $issues : array();
	}

	/**
	 * Check if feed has validation issues
	 *
	 * @param bool $fresh Whether to run fresh validation or use cached results.
	 * @return bool True if feed has validation issues.
	 */
	public function hasValidationIssues( bool $fresh = true ): bool {
		return ! empty( $this->getValidationIssues( $fresh ) );
	}

	/**
	 * Get count of validation issues
	 *
	 * @param bool $fresh Whether to run fresh validation or use cached results.
	 * @return int Number of validation issues.
	 */
	public function getValidationIssueCount( bool $fresh = true ): int {
		return count( $this->getValidationIssues( $fresh ) );
	}

	/**
	 * Get URL to WooCommerce logs filtered for this plugin
	 *
	 * @return string Admin URL to plugin logs.
	 */
	public function getLogsUrl(): string {
		return admin_url( 'admin.php?page=wc-status&tab=logs&source=oapfw&paged=1' );
	}

	/**
	 * Get complete feed status information
	 *
	 * @param bool $fresh_validation Whether to run fresh validation or use cached results.
	 * @return array Complete status array with next_push, validation_issues, etc.
	 */
	public function getFeedStatus( bool $fresh_validation = true ): array {
		$validation_issues = $this->getValidationIssues( $fresh_validation );
		
		return array(
			'next_push' => $this->getNextScheduledPush(),
			'validation_issues' => $validation_issues,
			'has_issues' => ! empty( $validation_issues ),
			'issue_count' => count( $validation_issues ),
			'logs_url' => $this->getLogsUrl(),
		);
	}
}
