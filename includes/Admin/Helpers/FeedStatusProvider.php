<?php

declare(strict_types=1);

namespace OAPFW\Admin\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FeedStatusProvider {

	const SCHEDULED_ACTION_HOOK = 'oapfw_push_feed_event';

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

	public function getValidationIssues(): array {
		$issues = get_transient( 'oapfw_last_validation' );
		return ! empty( $issues ) && is_array( $issues ) ? $issues : array();
	}

	public function hasValidationIssues(): bool {
		return ! empty( $this->getValidationIssues() );
	}

	public function getValidationIssueCount(): int {
		return count( $this->getValidationIssues() );
	}

	public function getLogsUrl(): string {
		return admin_url( 'admin.php?page=wc-status&tab=logs&source=oapfw&paged=1' );
	}

	public function getFeedStatus(): array {
		return array(
			'next_push' => $this->getNextScheduledPush(),
			'validation_issues' => $this->getValidationIssues(),
			'has_issues' => $this->hasValidationIssues(),
			'issue_count' => $this->getValidationIssueCount(),
			'logs_url' => $this->getLogsUrl(),
		);
	}
}