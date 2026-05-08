<?php
/**
 * OpenAiProviderTest.php
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Services\Ai\Tests;

use Etch\Services\Ai\AgentProviders\OpenAiProvider;
use Etch\Services\Ai\AiMode;
use WP_UnitTestCase;

/**
 * Class OpenAiProviderTest
 *
 * Tests the OpenAiProvider class through its public API.
 */
class OpenAiProviderTest extends WP_UnitTestCase {

	/**
	 * Test that deltas are streamed when stream receives sse chunks.
	 */
	public function test_deltas_are_streamed_when_stream_receives_sse_chunks(): void {
		$deltas = array();

		$this->stream_with_chunks(
			array(
				"data: {\"type\":\"response.output_text.delta\",\"delta\":\"Hello\"}\n\n",
				"data: {\"type\":\"response.output_text.delta\",\"delta\":\" world\"}\n\n",
			),
			function ( string $delta ) use ( &$deltas ) {
				$deltas[] = $delta;
			}
		);

		$this->assertSame( array( 'Hello', ' world' ), $deltas );
	}

	/**
	 * Test that missing api key error is returned when streaming with empty key.
	 */
	public function test_missing_api_key_error_is_returned_when_streaming_with_empty_key(): void {
		$provider = new OpenAiProvider( new FakeHttpTransport(), '' );

		$result = $provider->stream_ai_response( self::one_message(), AiMode::Ask, array( 'on_delta' => function () {} ) );

		$this->assertSame( 'etch_ai_missing_api_key', $result->get_error_code() );
	}

	/**
	 * Test that empty messages error is returned when streaming with no messages.
	 */
	public function test_empty_messages_error_is_returned_when_streaming_with_no_messages(): void {
		$provider = new OpenAiProvider( new FakeHttpTransport(), 'test-key' );

		$result = $provider->stream_ai_response( array(), AiMode::Ask, array( 'on_delta' => function () {} ) );

		$this->assertSame( 'etch_ai_invalid_messages', $result->get_error_code() );
	}

	/**
	 * Test that transport error is returned when stream fails.
	 */
	public function test_transport_error_is_returned_when_stream_fails(): void {
		$transport = new FakeHttpTransport(
			array(
				'status' => 200,
				'body'   => '',
			),
			array(),
			new \WP_Error( 'curl_error', 'Connection reset' )
		);
		$provider = new OpenAiProvider( $transport, 'test-key' );

		$result = $provider->stream_ai_response( self::one_message(), AiMode::Ask, array( 'on_delta' => function () {} ) );

		$this->assertSame( 'curl_error', $result->get_error_code() );
	}

	/**
	 * Test that null is returned when streaming succeeds.
	 */
	public function test_null_is_returned_when_streaming_succeeds(): void {
		$result = $this->stream_with_chunks(
			array( "data: {\"type\":\"response.output_text.delta\",\"delta\":\"Hi\"}\n\n" )
		);

		$this->assertNull( $result );
	}

	/**
	 * A single user message for tests that just need valid input.
	 *
	 * @return array<int, array{role: string, content: string}>
	 */
	private static function one_message(): array {
		return array(
			array(
				'role'    => 'user',
				'content' => 'Hello',
			),
		);
	}

	/**
	 * Test that rag tool replaces web search when rag is enabled.
	 */
	public function test_rag_replaces_web_search_when_rag_is_enabled(): void {
		$tools = $this->get_tools_from_payload( true );

		$this->assertSame( array( 'rag_retrieve' ), $tools );
	}

	/**
	 * Test that web search is used when rag is disabled.
	 */
	public function test_web_search_is_used_when_rag_is_disabled(): void {
		$tools = $this->get_tools_from_payload( false );

		$this->assertSame( array( 'web_search' ), $tools );
	}

	/**
	 * Test that deltas are received after function call when rag is enabled.
	 */
	public function test_deltas_are_received_after_function_call_when_rag_is_enabled(): void {
		$transport = new FakeHttpTransport(
			array(
				'status' => 200,
				'body'   => wp_json_encode(
					array(
						'results' => array(
							array(
								'title' => 'Loops',
								'content' => 'How to use loops.',
								'source_url' => 'https://docs.etchwp.com/loops',
							),
						),
					)
				),
			)
		);

		$transport->enqueue_stream_response(
			array(
				"data: {\"type\":\"response.completed\",\"response\":{\"id\":\"resp_123\",\"output\":[{\"type\":\"function_call\",\"call_id\":\"call_abc\",\"name\":\"rag_retrieve\",\"arguments\":\"{\\\"query\\\":\\\"loops\\\"}\"}]}}\n\n",
			)
		);

		$transport->enqueue_stream_response(
			array(
				"data: {\"type\":\"response.output_text.delta\",\"delta\":\"Here is the answer\"}\n\n",
			)
		);

		$deltas   = array();
		$provider = new OpenAiProvider( $transport, 'test-key', true );

		$provider->stream_ai_response(
			self::one_message(),
			AiMode::Ask,
			array(
				'on_delta' => function ( string $delta ) use ( &$deltas ) {
					$deltas[] = $delta;
				},
			)
		);

		$this->assertSame( array( 'Here is the answer' ), $deltas );
	}

	/**
	 * Test that chat continues with empty results when middleware fails.
	 */
	public function test_chat_continues_with_empty_results_when_middleware_fails(): void {
		$transport = new FakeHttpTransport(
			new \WP_Error( 'curl_error', 'Connection refused' )
		);

		$transport->enqueue_stream_response(
			array(
				"data: {\"type\":\"response.completed\",\"response\":{\"id\":\"resp_123\",\"output\":[{\"type\":\"function_call\",\"call_id\":\"call_abc\",\"name\":\"rag_retrieve\",\"arguments\":\"{\\\"query\\\":\\\"loops\\\"}\"}]}}\n\n",
			)
		);

		$transport->enqueue_stream_response(
			array(
				"data: {\"type\":\"response.output_text.delta\",\"delta\":\"I can still help\"}\n\n",
			)
		);

		$deltas   = array();
		$provider = new OpenAiProvider( $transport, 'test-key', true );

		$provider->stream_ai_response(
			self::one_message(),
			AiMode::Ask,
			array(
				'on_delta' => function ( string $delta ) use ( &$deltas ) {
					$deltas[] = $delta;
				},
			)
		);

		$this->assertSame( array( 'I can still help' ), $deltas );
	}

	/**
	 * Test that web search tool is NOT included in follow-up when RAG returns empty results.
	 */
	public function test_web_search_tool_is_not_in_follow_up_when_rag_returns_empty_results(): void {
		$follow_up_payload = $this->follow_up_payload_after_rag_complete(
			new FakeHttpTransport(
				array(
					'status' => 200,
					'body'   => wp_json_encode( array( 'results' => array() ) ),
				)
			),
			array(
				"data: {\"type\":\"response.output_text.delta\",\"delta\":\"Answer without web search\"}\n\n",
			)
		);

		$this->assertArrayNotHasKey( 'tools', $follow_up_payload );
	}

	/**
	 * Test that web search tool is included in follow-up when RAG is unavailable.
	 */
	public function test_web_search_tool_is_in_follow_up_when_rag_is_unavailable(): void {
		$transport = new FakeHttpTransport(
			new \WP_Error( 'curl_error', 'Connection refused' )
		);

		$transport->enqueue_stream_response(
			array(
				"data: {\"type\":\"response.completed\",\"response\":{\"id\":\"resp_123\",\"output\":[{\"type\":\"function_call\",\"call_id\":\"call_abc\",\"name\":\"rag_retrieve\",\"arguments\":\"{\\\"query\\\":\\\"loops\\\"}\"}]}}\n\n",
			)
		);

		$transport->enqueue_stream_response(
			array(
				"data: {\"type\":\"response.output_text.delta\",\"delta\":\"Answer via web search\"}\n\n",
			)
		);

		$provider = new OpenAiProvider( $transport, 'test-key', true );
		$provider->stream_ai_response( self::one_message(), AiMode::Ask, array( 'on_delta' => function () {} ) );

		$follow_up_payload = json_decode( $transport->get_stream_bodies()[1], true );
		$tool_types = array_map(
			function ( array $tools ) {
				return $tools['type'] ?? '';
			},
			$follow_up_payload['tools'] ?? array()
		);

		$this->assertContains( 'web_search', $tool_types );
	}

	/**
	 * Test that stream_ai_continue returns a not-supported error.
	 */
	public function test_continue_returns_not_supported_error(): void {
		$provider = new OpenAiProvider( new FakeHttpTransport(), 'test-key' );

		$result = $provider->stream_ai_continue( array(), array( 'on_delta' => function () {} ) );

		$this->assertWPError( $result );
		$this->assertSame( 'etch_ai_continue_unsupported', $result->get_error_code() );
		$this->assertSame( 501, $result->get_error_data()['status'] );
	}

	/**
	 * Test that the payload input items are message with typed content parts.
	 */
	public function test_payload_input_items_are_message_with_typed_content_parts(): void {
		$transport = new FakeHttpTransport();
		$transport->enqueue_stream_response(
			array(
				"data: {\"type\":\"response.output_text.delta\",\"delta\":\"ok\"}\n\n",
			)
		);

		$provider = new OpenAiProvider( $transport, 'test-key', false );

		$provider->stream_ai_response(
			array(
				array(
					'role' => 'user',
					'content' => array(
						array(
							'type' => 'input_image',
							'image_url' => 'data:image/png;base64,AAAA',
						),
						array(
							'type' => 'input_text',
							'text' => 'Build this section',
						),
					),
				),
			),
			AiMode::Ask,
			array( 'on_delta' => function () {} )
		);

		$payload = json_decode( $transport->get_stream_bodies()[0], true );

		$this->assertContains( 'input_image', $payload['input'][0]['content'][0]['type'] );
		$this->assertContains( 'input_text', $payload['input'][0]['content'][1]['type'] );
	}

	/**
	 * Helper: extract tool identifiers from the payload sent when streaming.
	 *
	 * @param bool $rag_enabled Whether RAG is enabled.
	 *
	 * @return array<string> Tool identifiers (name or type).
	 */
	private function get_tools_from_payload( bool $rag_enabled ): array {
		$transport = new FakeHttpTransport();

		$transport->enqueue_stream_response(
			array(
				"data: {\"type\":\"response.output_text.delta\",\"delta\":\"Hi\"}\n\n",
			)
		);

		$provider = new OpenAiProvider( $transport, 'test-key', $rag_enabled );
		$provider->stream_ai_response( self::one_message(), AiMode::Ask, array( 'on_delta' => function () {} ) );

		$payload = json_decode( $transport->get_stream_bodies()[0], true );

		return array_map(
			function ( array $tool ): string {
				return $tool['name'] ?? $tool['type'] ?? '';
			},
			$payload['tools']
		);
	}

	/**
	 * Helper: call stream_ai_response with canned SSE chunks.
	 *
	 * @param array<string> $chunks   The SSE chunks to feed.
	 * @param callable|null $on_delta Optional delta callback.
	 *
	 * @return ?WP_Error
	 */
	private function stream_with_chunks( array $chunks, ?callable $on_delta = null ): ?\WP_Error {
		$transport = new FakeHttpTransport(
			array(
				'status' => 200,
				'body'   => '',
			),
			$chunks
		);
		$provider = new OpenAiProvider( $transport, 'test-key' );

		return $provider->stream_ai_response( self::one_message(), AiMode::Ask, array( 'on_delta' => $on_delta ?? function () {} ) );
	}

	/**
	 * Helper: get the follow-up payload after RAG completes.
	 *
	 * @param FakeHttpTransport $transport The transport to use.
	 * @param array<string>     $follow_up_chunks The follow-up chunks to enqueue.
	 * @return array<string, mixed> The follow-up payload.
	 */
	private function follow_up_payload_after_rag_complete(
		FakeHttpTransport $transport,
		array $follow_up_chunks
	): array {
		$transport->enqueue_stream_response(
			array(
				"data: {\"type\":\"response.completed\",\"response\":{\"id\":\"resp_123\",\"output\":[{\"type\":\"function_call\",\"call_id\":\"call_abc\",\"name\":\"rag_retrieve\",\"arguments\":\"{\\\"query\\\":\\\"loops\\\"}\"}]}}\n\n",
			)
		);

		$transport->enqueue_stream_response( $follow_up_chunks );

		$provider = new OpenAiProvider( $transport, 'test-key', true );
		$provider->stream_ai_response( self::one_message(), AiMode::Ask, array( 'on_delta' => function () {} ) );

		return json_decode( $transport->get_stream_bodies()[1], true );
	}
}
