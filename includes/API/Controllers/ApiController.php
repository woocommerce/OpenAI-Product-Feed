<?php

declare(strict_types=1);

namespace OAPFW\API\Controllers;

use OAPFW\Core\SettingsRepositoryInterface;
use OAPFW\Core\FeedGeneratorInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API controller for REST endpoints
 */
class ApiController {

	private SettingsRepositoryInterface $settings;
	private FeedGeneratorInterface $feedGenerator;

	public function __construct(
		SettingsRepositoryInterface $settings,
		FeedGeneratorInterface $feedGenerator
	) {
		$this->settings      = $settings;
		$this->feedGenerator = $feedGenerator;
	}

	/**
	 * Initialize API endpoints
	 */
	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
	}

	/**
	 * Register REST API routes
	 */
	public function registerRoutes(): void {
		// Public preview endpoint for testing
		register_rest_route(
			'oapfw/v1',
			'/feed',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => array( $this, 'handlePreviewFeed' ),
			)
		);
	}

	/**
	 * Handle preview feed request
	 */
	public function handlePreviewFeed( \WP_REST_Request $request ): \WP_REST_Response {
		$product_id = absint( (string) $request->get_param( 'product_id' ) );

		if ( $product_id ) {
			$rows = $this->feedGenerator->buildForProductId( $product_id );
		} else {
			$rows = $this->feedGenerator->buildFeed();
		}

		return rest_ensure_response( $rows );
	}
}
