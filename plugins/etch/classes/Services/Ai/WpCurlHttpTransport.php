<?php
/**
 * WpCurlHttpTransport.php
 *
 * HTTP transport using wp_remote_post for regular requests and cURL for streaming.
 *
 * @package Etch\Services\Ai
 */

declare(strict_types=1);

namespace Etch\Services\Ai;

use WP_Error;

/**
 * WpCurlHttpTransport class.
 *
 * @package Etch\Services\Ai
 */
class WpCurlHttpTransport implements HttpTransport {

	/**
	 * Send a POST request using wp_remote_post.
	 *
	 * @param string                $url     The URL to send the request to.
	 * @param array<string, string> $headers The request headers.
	 * @param string                $body    The request body.
	 *
	 * @return array{status: int, body: string}|WP_Error
	 */
	public function post( string $url, array $headers, string $body ): array|WP_Error {
		$response = wp_remote_post(
			$url,
			array(
				'headers' => $headers,
				'body'    => $body,
				'timeout' => 120,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'status' => (int) wp_remote_retrieve_response_code( $response ),
			'body'   => (string) wp_remote_retrieve_body( $response ),
		);
	}

	/**
	 * Send a streaming POST request using cURL.
	 *
	 * @param string                $url      The URL to send the request to.
	 * @param array<string, string> $headers  The request headers.
	 * @param string                $body     The request body.
	 * @param callable              $on_chunk Callback invoked with each chunk of data.
	 *
	 * @return ?WP_Error Returns WP_Error on failure, null on success.
	 */
	public function stream( string $url, array $headers, string $body, callable $on_chunk ): ?WP_Error {
		$curl_headers = array();
		foreach ( $headers as $key => $value ) {
			$curl_headers[] = $key . ': ' . $value;
		}

		$result = $this->run_curl( $url, $curl_headers, $body, $on_chunk );

		return $this->check_curl_response( $result['http_code'], $result['curl_error'], $result['raw_body'] );
	}

	/**
	 * Execute a cURL streaming request and return raw results.
	 *
	 * @param string        $url          The URL to send the request to.
	 * @param array<string> $curl_headers The formatted cURL headers.
	 * @param string        $body         The request body.
	 * @param callable      $on_chunk     Callback invoked with each chunk of data.
	 *
	 * @return array{http_code: int, curl_error: string, raw_body: string}
	 */
	protected function run_curl( string $url, array $curl_headers, string $body, callable $on_chunk ): array {
		$handler = curl_init( $url );
		if ( false === $handler ) {
			return array(
				'http_code'  => 500,
				'curl_error' => 'Failed to initialize cURL handler',
				'raw_body'   => '',
			);
		}

		$raw_body = '';

		curl_setopt_array(
			$handler,
			array(
				CURLOPT_POST           => true,
				CURLOPT_HTTPHEADER     => $curl_headers,
				CURLOPT_POSTFIELDS     => $body,
				CURLOPT_RETURNTRANSFER => false,
				CURLOPT_TIMEOUT        => 120,
				CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
				CURLOPT_WRITEFUNCTION  => function ( $curl, string $chunk ) use ( $on_chunk, &$raw_body ) {
					$raw_body .= $chunk;
					$on_chunk( $chunk );
					return strlen( $chunk );
				},
			)
		);

		curl_exec( $handler );

		return array(
			'http_code'  => (int) curl_getinfo( $handler, CURLINFO_HTTP_CODE ),
			'curl_error' => curl_error( $handler ),
			'raw_body'   => $raw_body,
		);
	}

	/**
	 * Check for cURL or HTTP errors from the raw response values.
	 *
	 * @param int    $http_code  The HTTP status code.
	 * @param string $curl_error The cURL error string (empty string if none).
	 * @param string $raw_body   The raw response body.
	 *
	 * @return ?WP_Error Returns WP_Error on failure, null on success.
	 */
	private function check_curl_response( int $http_code, string $curl_error, string $raw_body ): ?WP_Error {
		if ( '' !== $curl_error ) {
			return new WP_Error( 'curl_error', 'cURL error: ' . $curl_error, array( 'status' => 500 ) );
		}

		if ( $http_code >= 400 ) {
			$error_message = $this->get_error_message_from_raw_body( $raw_body );

			if ( ! empty( $error_message ) ) {
				$error_message = sprintf( 'Error: %s', $error_message );
			} else {
				$error_message = sprintf( 'HTTP error: %d', $http_code );
			}

			return new WP_Error(
				'etch_http_error',
				$error_message,
				array( 'status' => $http_code )
			);
		}

		return null;
	}

	/**
	 * Extract a human-readable error message from a raw JSON response body.
	 *
	 * @param string $raw_body The raw HTTP response body.
	 *
	 * @return ?string The extracted message, or null if none found.
	 */
	private function get_error_message_from_raw_body( string $raw_body ): ?string {
		$decoded = json_decode( $raw_body, true );

		if ( ! is_array( $decoded ) ) {
			return null;
		}

		if ( isset( $decoded['error'] ) && is_string( $decoded['error'] ) ) {
			return $decoded['error'];
		}

		if ( isset( $decoded['error']['message'] ) && is_string( $decoded['error']['message'] ) ) {
			return $decoded['error']['message'];
		}

		return null;
	}
}
