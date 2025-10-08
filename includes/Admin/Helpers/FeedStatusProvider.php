<?php

declare(strict_types=1);

namespace OAPFW\Admin\Helpers;

use OAPFW\Core\FeedGeneratorInterface;
use OAPFW\Core\ValidatorInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FeedStatusProvider {

	const SCHEDULED_ACTION_HOOK = 'oapfw_push_feed_event';

	private FeedGeneratorInterface $feedGenerator;
	private ValidatorInterface $validator;

	public function __construct( FeedGeneratorInterface $feedGenerator, ValidatorInterface $validator ) {
		$this->feedGenerator = $feedGenerator;
		$this->validator = $validator;
	}

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

	public function validateFeedNow(): array {
		$rows = $this->feedGenerator->buildFeed();
		$issues = $this->validator->validateFeed( $rows );
		
		if ( $issues ) {
			set_transient( 'oapfw_last_validation', $issues, 5 * MINUTE_IN_SECONDS );
		} else {
			delete_transient( 'oapfw_last_validation' );
		}
		
		return $issues;
	}

	public function getValidationIssues( bool $fresh = true ): array {
		if ( $fresh ) {
			return $this->validateFeedNow();
		}
		
		$issues = get_transient( 'oapfw_last_validation' );
		return ! empty( $issues ) && is_array( $issues ) ? $issues : array();
	}

	public function hasValidationIssues( bool $fresh = true ): bool {
		return ! empty( $this->getValidationIssues( $fresh ) );
	}

	public function getValidationIssueCount( bool $fresh = true ): int {
		return count( $this->getValidationIssues( $fresh ) );
	}

	public function getLogsUrl(): string {
		return admin_url( 'admin.php?page=wc-status&tab=logs&source=oapfw&paged=1' );
	}

	public function getFeedStatus( bool $fresh_validation = true ): array {
		return array(
			'next_push' => $this->getNextScheduledPush(),
			'validation_issues' => $this->getValidationIssues( $fresh_validation ),
			'has_issues' => $this->hasValidationIssues( $fresh_validation ),
			'issue_count' => $this->getValidationIssueCount( $fresh_validation ),
			'logs_url' => $this->getLogsUrl(),
		);
	}
}