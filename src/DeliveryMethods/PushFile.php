<?php
/**
 * Push delivery method.
 *
 * @package Automattic\WooCommerce\ProductFeedForOpenAI
 */

namespace Automattic\WooCommerce\ProductFeedForOpenAI\DeliveryMethods;

use RuntimeException;
use Automattic\WooCommerce\ProductFeedForOpenAI\Feed\FileBasedFeedInterface;
use WP_REST_Response;

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
	 * Deliver the feed.
	 *
	 * Yes, further headers and checks for the response are missing.
	 * That will be one of the next PRs.
	 *
	 * @param FileBasedFeedInterface $feed The feed to deliver.
	 * @return WP_REST_Response The response from the remote endpoint.
	 * @throws RuntimeException If the request fails.
	 * @throws RuntimeException If the HTTP code is not between 200 and 299.
	 */
	public function deliver( FileBasedFeedInterface $feed ): WP_REST_Response {
		$path = $feed->get_file_path();

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

		return new WP_REST_Response( $response, $http_code );
	}
}
