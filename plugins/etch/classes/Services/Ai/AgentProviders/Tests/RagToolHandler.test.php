<?php
/**
 * RagToolHandlerTest.php
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Services\Ai\AgentProviders\Tests;

use Etch\Services\Ai\AgentProviders\RagToolHandler;
use Etch\Services\Ai\Tests\FakeHttpTransport;
use WP_UnitTestCase;

/**
 * Class RagToolHandlerTest
 *
 * Tests the RagToolHandler class.
 */
class RagToolHandlerTest extends WP_UnitTestCase {

	/**
	 * Test that results are returned when middleware responds successfully.
	 */
	public function test_results_are_returned_when_middleware_responds_successfully(): void {
		$result = $this->handle_with_response(
			array(
				'status' => 200,
				'body'   => wp_json_encode(
					array(
						'results' => array(
							array(
								'title'      => 'Loops',
								'content'    => 'How to use loops.',
								'source_url' => 'https://docs.etchwp.com/loops',
							),
						),
					)
				),
			)
		);

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result['results'] );
		$this->assertSame( 'Loops', $result['results'][0]['title'] );
	}

	/**
	 * Test that empty results are returned when middleware returns no matches.
	 */
	public function test_empty_results_are_returned_when_middleware_returns_no_matches(): void {
		$result = $this->handle_with_response(
			array(
				'status' => 200,
				'body'   => wp_json_encode( array( 'results' => array() ) ),
			)
		);

		$this->assertIsArray( $result );
		$this->assertEmpty( $result['results'] );
	}

	/**
	 * Test that wp error is returned when network fails.
	 */
	public function test_wp_error_is_returned_when_network_fails(): void {
		$result = $this->handle_with_response(
			new \WP_Error( 'curl_error', 'Connection timed out' )
		);

		$this->assertWPError( $result );
		$this->assertSame( 'etch_rag_network_error', $result->get_error_code() );
	}

	/**
	 * Test that wp error is returned when middleware returns http error.
	 */
	public function test_wp_error_is_returned_when_middleware_returns_http_error(): void {
		$result = $this->handle_with_response(
			array(
				'status' => 500,
				'body'   => 'Internal Server Error',
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'etch_rag_http_error', $result->get_error_code() );
	}

	/**
	 * Test that wp error is returned when response body is malformed.
	 */
	public function test_wp_error_is_returned_when_response_body_is_malformed(): void {
		$result = $this->handle_with_response(
			array(
				'status' => 200,
				'body'   => 'not json',
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'etch_rag_malformed_response', $result->get_error_code() );
	}

	/**
	 * Test that wp error is returned when response is missing results key.
	 */
	public function test_wp_error_is_returned_when_response_is_missing_results_key(): void {
		$result = $this->handle_with_response(
			array(
				'status' => 200,
				'body'   => wp_json_encode( array( 'data' => 'something' ) ),
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'etch_rag_malformed_response', $result->get_error_code() );
	}

	/**
	 * Helper: create a handler with a canned response and call handle_call.
	 *
	 * @param array{status: int, body: string}|\WP_Error $post_response The canned transport response.
	 * @param string                                     $query         The query to send.
	 *
	 * @return array|\WP_Error
	 */
	private function handle_with_response( array|\WP_Error $post_response, string $query = 'test query' ) {
		$handler = new RagToolHandler( new FakeHttpTransport( $post_response ), 'https://middleware.test' );

		return $handler->handle_call( $query );
	}
}
