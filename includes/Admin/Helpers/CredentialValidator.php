<?php

declare(strict_types=1);

namespace OAPFW\Admin\Helpers;

use OAPFW\Core\Interfaces\SettingsRepositoryInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CredentialValidator {

	private SettingsRepositoryInterface $settings;

	public function __construct( SettingsRepositoryInterface $settings ) {
		$this->settings = $settings;
	}

	public function areCredentialsConfigured(): bool {
		return $this->isEndpointConfigured() && $this->isTokenConfigured();
	}

	public function isEndpointConfigured(): bool {
		return trim( (string) $this->settings->get( 'endpoint_url', '' ) ) !== '';
	}

	public function isTokenConfigured(): bool {
		return trim( (string) $this->settings->get( 'auth_token', '' ) ) !== '';
	}

	public function canPushFeed(): bool {
		$delivery_enabled = $this->settings->get( 'delivery_enabled', 'false' ) === 'true';
		return $delivery_enabled && $this->areCredentialsConfigured();
	}

	public function getEndpointUrl(): string {
		return trim( (string) $this->settings->get( 'endpoint_url', '' ) );
	}

	public function getAuthToken(): string {
		return trim( (string) $this->settings->get( 'auth_token', '' ) );
	}
}
