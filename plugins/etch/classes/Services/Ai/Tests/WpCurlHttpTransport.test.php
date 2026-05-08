<?php
/**
 * WpCurlHttpTransport.test.php
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Services\Ai\Tests;

use Etch\Services\Ai\WpCurlHttpTransport;
use WP_UnitTestCase;

/**
 * Class WpCurlHttpTransportTest
 *
 * Tests the WpCurlHttpTransport class through its public API.
 * Uses a controlled subclass to avoid real HTTP/cURL calls.
 */
class WpCurlHttpTransportTest extends WP_UnitTestCase {

	/**
	 * Remove all pre_http_request filters after each test.
	 */
	protected function tearDown(): void {
		remove_all_filters( 'pre_http_request' );
		parent::tearDown();
	}

	/**
	 * Build a controlled WpCurlHttpTransport subclass that bypasses real cURL.
	 *
	 * @param int    $http_code  The HTTP status code to simulate.
	 * @param string $curl_error The cURL error string to simulate ('' = no error).
	 * @param string $raw_body   The raw response body to simulate.
	 *
	 * @return WpCurlHttpTransport
	 */
	private function make_transport(
		int $http_code = 200,
		string $curl_error = '',
		string $raw_body = ''
	): WpCurlHttpTransport {
		return new class($http_code, $curl_error, $raw_body) extends WpCurlHttpTransport {

			/**
			 * Constructor.
			 *
			 * @param int    $fake_http_code  Simulated HTTP status code.
			 * @param string $fake_curl_error Simulated cURL error string.
			 * @param string $fake_raw_body   Simulated raw response body.
			 */
			public function __construct(
				private int $fake_http_code,
				private string $fake_curl_error,
				private string $fake_raw_body
			) {}

			/**
			 * Return fake cURL results instead of making a real request.
			 *
			 * @param string        $url          Ignored.
			 * @param array<string> $curl_headers Ignored.
			 * @param string        $body         Ignored.
			 * @param callable      $on_chunk     Ignored.
			 *
			 * @return array{http_code: int, curl_error: string, raw_body: string}
			 */
			protected function run_curl(
				string $url,
				array $curl_headers,
				string $body,
				callable $on_chunk
			): array {
				return array(
					'http_code'  => $this->fake_http_code,
					'curl_error' => $this->fake_curl_error,
					'raw_body'   => $this->fake_raw_body,
				);
			}
		};
	}

	/**
	 * Test that WP_Error is returned when wp_remote_post fails.
	 */
	public function test_returns_wp_error_when_wp_remote_post_fails(): void {
		$wp_error = new \WP_Error( 'http_request_failed', 'cURL error 6: Could not resolve host' );

		add_filter(
			'pre_http_request',
			function () use ( $wp_error ) {
				return $wp_error;
			},
			10,
			3
		);

		$transport = new WpCurlHttpTransport();
		$result    = $transport->post( 'https://api.example.com', array(), '{}' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'http_request_failed', $result->get_error_code() );
		$this->assertSame( 'cURL error 6: Could not resolve host', $result->get_error_message() );
	}

	/**
	 * Test that status and body are returned on a valid response.
	 */
	public function test_returns_status_and_body_on_valid_response(): void {
		add_filter(
			'pre_http_request',
			function () {
				return array(
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'body'     => '{"ok":true}',
					'headers'  => array(),
					'cookies'  => array(),
					'filename' => null,
				);
			},
			10,
			3
		);

		$transport = new WpCurlHttpTransport();
		$result    = $transport->post( 'https://api.example.com', array(), '{}' );

		$this->assertSame( 200, $result['status'] );
		$this->assertSame( '{"ok":true}', $result['body'] );
	}

	/**
	 * Test that WP_Error with status 500 is returned on cURL transport error.
	 */
	public function test_returns_wp_error_with_500_on_curl_transport_error(): void {
		$transport = $this->make_transport( 500, 'Connection reset' );

		$result = $transport->stream( 'https://api.example.com', array(), '{}', function () {} );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'cURL error: Connection reset', $result->get_error_message() );
		$this->assertSame( 500, $result->get_error_data()['status'] );
	}

	/**
	 * Test that null is returned when there is no cURL error and status < 400.
	 */
	public function test_returns_null_when_no_curl_error_and_status_below_400(): void {
		$transport = $this->make_transport( 200 );

		$result = $transport->stream( 'https://api.example.com', array(), '{}', function () {} );

		$this->assertNull( $result );
	}

	/**
	 * Data provider for HTTP error message resolution cases.
	 *
	 * @return array<string, array{int, string, string}>
	 */
	public static function http_error_cases(): array {
		return array(
			'no error in body — uses default message'        => array( 503, '', 'HTTP error: 503' ),
			'error is string — used verbatim'                => array( 401, '{"error":"Unauthorized"}', 'Error: Unauthorized' ),
			'error.message is string — used verbatim'        => array( 429, '{"error":{"message":"Rate limit exceeded"}}', 'Error: Rate limit exceeded' ),
			'error is non-string — falls back to default'    => array( 400, '{"error":123}', 'HTTP error: 400' ),
			'error.message is non-string — falls back'       => array( 400, '{"error":{"message":123}}', 'HTTP error: 400' ),
		);
	}

	/**
	 * Test HTTP error message resolution and status code preservation.
	 *
	 * @dataProvider http_error_cases
	 *
	 * @param int    $http_code        The HTTP status code to simulate.
	 * @param string $raw_body         The raw response body to simulate.
	 * @param string $expected_message The expected WP_Error message.
	 */
	public function test_stream_http_error( int $http_code, string $raw_body, string $expected_message ): void {
		$transport = $this->make_transport( http_code: $http_code, raw_body: $raw_body );
		$result    = $transport->stream( 'https://api.example.com', array(), '{}', function () {} );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( $http_code, $result->get_error_data()['status'] );
		$this->assertSame( $expected_message, $result->get_error_message() );
	}
}
