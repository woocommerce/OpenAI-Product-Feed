<?php
/**
 * Push delivery method.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

namespace Automattic\WooCommerce\ProductFeedForOpenAI\DeliveryMethods;

use RuntimeException;
use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\FeedInterface;

// This file uses cURL heavily. It's a requirement for the plugin.
// phpcs:disable WordPress.WP.AlternativeFunctions

/**
 * Delivery method for pushing file feeds to a remote through cURL.
 */
class PushFile implements FileDeliveryInterface {
	/**
	 * The endpoint to push the feed to.
	 *
	 * @var string
	 */
	private string $endpoint;

	/**
	 * Constructor.
	 *
	 * @param string $endpoint The endpoint to push the feed to.
	 */
	public function __construct( string $endpoint ) {
		$this->endpoint = $endpoint;
	}

	/**
	 * Check if the delivery method is setup.
	 *
	 * @return bool True if the delivery method is setup, false otherwise.
	 */
	public function check_setup(): bool {
		return ! empty( $this->endpoint );
	}

	/**
	 * Deliver the feed.
	 *
	 * Yes, further headers and checks for the response are missing.
	 * That will be one of the next PRs.
	 *
	 * @param FeedInterface $feed The feed to deliver.
	 * @return array The response from the remote endpoint.
	 * @throws RuntimeException If the request fails.
	 * @throws RuntimeException If the HTTP code is not between 200 and 299.
	 */
	public function deliver( FeedInterface $feed ): array {
		$path = $feed->get_file_path();

		/**
		 * Allows the request to be pre-processed.
		 *
		 * @param array|null $pre The pre-request data.
		 * @param string     $path The path to the feed file.
		 * @param string     $endpoint The endpoint to push the feed to.
		 * @return callable|null The pre-request action.
		 * @since 0.1.0
		 * @internal This filter should only be used for testing purposes.
		 */
		$pre = apply_filters( 'wpfoai_push_file_pre_request', null, $path, $this->endpoint );
		if ( ! is_null( $pre ) ) {
			return $pre;
		}

		$ch = curl_init( $this->endpoint );
		curl_setopt_array(
			$ch,
			[
				CURLOPT_POST           => true,
				CURLOPT_INFILE         => fopen( $path, 'rb' ),
				CURLOPT_INFILESIZE     => filesize( $path ),
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HTTPHEADER     => [
					'Content-Type: application/json',
				],
			]
		);
		$response = curl_exec( $ch );

		if ( false === $response ) {
			// phpcs:ignore
			throw new RuntimeException( 'cURL error: ' . curl_error( $ch ) );
		}

		$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		if ( $http_code < 200 || $http_code > 299 ) {
			throw new RuntimeException( esc_html( 'Received non-200 HTTP code: ' . $http_code ) );
		}
		curl_close( $ch );

		return [
			'body'      => $response,
			'http_code' => $http_code,
		];
	}
}
