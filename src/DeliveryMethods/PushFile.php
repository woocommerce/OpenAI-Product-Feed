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
		return ! empty( $this->endpoint ) && wp_http_validate_url( $this->endpoint );
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
		 * @return array|null Short-circuit response (same shape as deliver()) or null.
		 * @since 0.1.0
		 * @internal This filter should only be used for testing purposes.
		 */
		$pre = apply_filters( 'wpfoai_push_file_pre_request', null, $path, $this->endpoint );
		if ( ! is_null( $pre ) ) {
			return $pre;
		}

		$file = fopen( $path, 'rb' );
		if ( false === $file ) {
			throw new RuntimeException( 'Unable to open feed file for reading.' );
		}

		$size = filesize( $path );
		if ( false === $size ) {
			fclose( $file );
			throw new RuntimeException( 'Unable to determine feed file size.' );
		}

		// To avoid timeouts, but also give the responder enough time to receive the file, calculate the timeout.
		$timeout = max( 10, $size / MB_IN_BYTES * 10 ); // 10 seconds per MB.

		$curl_handle = curl_init( $this->endpoint );
		curl_setopt_array(
			$curl_handle,
			[
				CURLOPT_POST            => true,
				CURLOPT_INFILE          => $file,
				CURLOPT_INFILESIZE      => $size,
				CURLOPT_RETURNTRANSFER  => true,
				CURLOPT_CONNECTTIMEOUT  => 10,
				CURLOPT_TIMEOUT         => $timeout,
				CURLOPT_PROTOCOLS       => CURLPROTO_HTTP | CURLPROTO_HTTPS,
				CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
				CURLOPT_HTTPHEADER      => [
					'Content-Type: application/json',
					'Expect:', // avoid 100-continue stalls on some servers.
				],
			]
		);

		try {
			$response = curl_exec( $curl_handle );
			if ( false === $response ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				throw new RuntimeException( 'cURL error: ' . curl_error( $curl_handle ) );
			}

			$http_code = curl_getinfo( $curl_handle, CURLINFO_HTTP_CODE );
			if ( $http_code < 200 || $http_code > 299 ) {
				throw new RuntimeException( 'Received non-2xx HTTP code: ' . $http_code );
			}

			if ( false === $response ) {
				throw new RuntimeException( 'cURL error: ' . curl_error( $curl_handle ) );
			}
		} finally {
			if ( is_resource( $file ) ) {
				fclose( $file );
			}
			curl_close( $curl_handle );
		}

		return [
			'body'     => $response,
			'response' => [
				'code' => $http_code,
			],
		];
	}
}
