<?php
/**
 * MiddlewareProviderTest.php
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Services\Ai\AgentProviders\Tests;

use Etch\Services\Ai\AgentProviders\MiddlewareProvider;
use Etch\Services\Ai\AiMode;
use Etch\Services\Ai\Tests\FakeHttpTransport;
use DigitalGravy\FeatureFlag\FeatureFlag;
use DigitalGravy\FeatureFlag\FeatureFlagStore;

use WP_UnitTestCase;

/**
 * Class MiddlewareProviderTest
 *
 * Tests the MiddlewareProvider class through its public API.
 */
class MiddlewareProviderTest extends WP_UnitTestCase {

	/**
	 * Original flag store, saved before each test.
	 *
	 * @var mixed
	 */
	private $original_flag_store;

	/**
	 * Save the current flag store before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->original_flag_store = self::get_flag_store();
	}

	/**
	 * Restore the original flag store after each test.
	 */
	protected function tearDown(): void {
		self::set_flag_store( $this->original_flag_store );
		parent::tearDown();
	}

	/**
	 * Get the current flag store via reflection.
	 *
	 * @return mixed
	 */
	private static function get_flag_store() {
		$prop = new \ReflectionProperty( \Etch\Helpers\Flag::class, 'flag_store' );
		$prop->setAccessible( true );
		return $prop->getValue( null );
	}

	/**
	 * Set the flag store via reflection.
	 *
	 * @param mixed $store The flag store to set.
	 */
	private static function set_flag_store( $store ): void {
		$prop = new \ReflectionProperty( \Etch\Helpers\Flag::class, 'flag_store' );
		$prop->setAccessible( true );
		$prop->setValue( null, $store );
	}

	/**
	 * Set a single flag value while preserving other flags from the current store.
	 *
	 * @param string $flag_name The flag name.
	 * @param string $value     Flag value ('on' or 'off').
	 */
	private function set_flag_value( string $flag_name, string $value ): void {
		$flags = \Etch\Helpers\Flag::is_initialized() ? \Etch\Helpers\Flag::get_flags() : array();
		if ( ! is_array( $flags ) ) {
			$flags = array();
		}

		$flags[ $flag_name ] = $value;

		$flag_objs = array();
		foreach ( $flags as $name => $flag_val ) {
			if ( ! is_string( $name ) || ! is_string( $flag_val ) ) {
				continue;
			}
			$flag_objs[] = new FeatureFlag( $name, $flag_val );
		}

		self::set_flag_store( new FeatureFlagStore( $flag_objs ) );
	}

	/**
	 * Enable a flag via reflection.
	 *
	 * @param string $flag_name The name of the flag to enable.
	 */
	private function enable_flag( string $flag_name ): void {
		$this->set_flag_value( $flag_name, 'on' );
	}

	/**
	 * Test that deltas are streamed when stream receives sse chunks.
	 */
	public function test_deltas_are_streamed_when_stream_receives_sse_chunks(): void {
		$deltas = $this->collect_deltas_from_stream( self::hello_world_delta_chunks() );
		$this->assertSame( array( 'Hello', ' world' ), $deltas );
	}

	/**
	 * Test that null is returned when streaming succeeds.
	 */
	public function test_null_is_returned_when_streaming_succeeds(): void {
		$result = $this->stream_with_chunks(
			self::single_delta_chunk( 'Hi' )
		);

		$this->assertNull( $result );
	}

	/**
	 * Test that missing api key error is returned when streaming with empty key.
	 */
	public function test_missing_api_key_error_is_returned_when_streaming_with_empty_key(): void {
		$provider = new MiddlewareProvider( new FakeHttpTransport(), '' );

		$result = $provider->stream_ai_response( self::one_message(), AiMode::Ask, array( 'on_delta' => function () {} ) );

		$this->assertSame( 'etch_ai_missing_api_key', $result->get_error_code() );
	}

	/**
	 * Test that empty messages error is returned when streaming with no messages.
	 */
	public function test_empty_messages_error_is_returned_when_streaming_with_no_messages(): void {
		$provider = new MiddlewareProvider( new FakeHttpTransport(), 'test-key' );

		$result = $provider->stream_ai_response( array(), AiMode::Ask, array( 'on_delta' => function () {} ) );

		$this->assertSame( 'etch_ai_invalid_messages', $result->get_error_code() );
	}

	/**
	 * Test that transport error is returned when stream fails.
	 */
	public function test_transport_error_is_returned_when_stream_fails(): void {
		[ $provider ] = $this->make_provider( array(), new \WP_Error( 'curl_error', 'Connection reset' ) );

		$result = $provider->stream_ai_response( self::one_message(), AiMode::Ask, array( 'on_delta' => function () {} ) );

		$this->assertSame( 'curl_error', $result->get_error_code() );
	}

	/**
	 * Test that initial payload contains api key.
	 */
	public function test_initial_payload_contains_api_key(): void {
		$payload = $this->initial_payload();
		$this->assertSame( 'test-key', $payload['apiKey'] );
	}

	/**
	 * Test that initial payload contains messages.
	 */
	public function test_initial_payload_contains_messages(): void {
		$payload = $this->initial_payload();
		$this->assertSame( self::one_message(), $payload['messages'] );
	}

	/**
	 * Test that initial payload contains initial kind.
	 */
	public function test_initial_payload_contains_initial_kind(): void {
		$payload = $this->initial_payload();
		$this->assertSame( 'initial', $payload['kind'] );
	}

	/**
	 * Test that initial payload contains mode value.
	 */
	public function test_initial_payload_contains_mode_value(): void {
		$payload = $this->initial_payload();
		$this->assertSame( 'ask', $payload['mode'] );
	}

	/**
	 * Test that initial payload contains reasoning flag.
	 */
	public function test_initial_payload_contains_reasoning_flag(): void {
		$payload = $this->initial_payload();
		$this->assertArrayHasKey( 'reasoning', $payload );
		$this->assertIsBool( $payload['reasoning'] );
	}

	/**
	 * Test that deltas are streamed when continue receives SSE chunks.
	 */
	public function test_deltas_are_streamed_when_continue_receives_sse_chunks(): void {
		$deltas = $this->collect_deltas_from_continue( self::hello_world_delta_chunks() );
		$this->assertSame( array( 'Hello', ' world' ), $deltas );
	}

	/**
	 * Test that null is returned when continue succeeds.
	 */
	public function test_null_is_returned_when_continue_succeeds(): void {
		$result = $this->continue_with_chunks(
			self::single_delta_chunk( 'ok' )
		);

		$this->assertNull( $result );
	}

	/**
	 * Test that missing api key error is returned when continuing with empty key.
	 */
	public function test_missing_api_key_error_is_returned_when_continuing_with_empty_key(): void {
		$provider = new MiddlewareProvider( new FakeHttpTransport(), '' );

		$result = $provider->stream_ai_continue( self::valid_continue_body(), array( 'on_delta' => function () {} ) );

		$this->assertSame( 'etch_ai_missing_api_key', $result->get_error_code() );
	}

	/**
	 * Test that missing previous_response_id returns an error.
	 */
	public function test_missing_previous_response_id_error_is_returned_when_continuing(): void {
		$provider = new MiddlewareProvider( new FakeHttpTransport(), 'test-key' );

		$body   = self::valid_continue_body();
		unset( $body['previous_response_id'] );
		$result = $provider->stream_ai_continue( $body, array( 'on_delta' => function () {} ) );

		$this->assertSame( 'etch_ai_invalid_continue', $result->get_error_code() );
	}

	/**
	 * Test that empty function_call_outputs returns an error.
	 */
	public function test_empty_function_call_outputs_error_is_returned_when_continuing(): void {
		$provider = new MiddlewareProvider( new FakeHttpTransport(), 'test-key' );

		$body                          = self::valid_continue_body();
		$body['function_call_outputs'] = array();
		$result                        = $provider->stream_ai_continue( $body, array( 'on_delta' => function () {} ) );

		$this->assertSame( 'etch_ai_invalid_continue', $result->get_error_code() );
	}

	/**
	 * Test that missing function_call_outputs returns an error.
	 */
	public function test_missing_function_call_outputs_error_is_returned_when_continuing(): void {
		$provider = new MiddlewareProvider( new FakeHttpTransport(), 'test-key' );

		$body = self::valid_continue_body();
		unset( $body['function_call_outputs'] );
		$result = $provider->stream_ai_continue( $body, array( 'on_delta' => function () {} ) );

		$this->assertSame( 'etch_ai_invalid_continue', $result->get_error_code() );
	}

	/**
	 * Test that transport error is returned when continue stream fails.
	 */
	public function test_transport_error_is_returned_when_continue_stream_fails(): void {
		[ $provider ] = $this->make_provider( array(), new \WP_Error( 'curl_error', 'Connection reset' ) );

		$result = $provider->stream_ai_continue( self::valid_continue_body(), array( 'on_delta' => function () {} ) );

		$this->assertSame( 'curl_error', $result->get_error_code() );
	}

	/**
	 * Test that the continue payload contains the required fields.
	 */
	public function test_continue_payload_contains_required_fields(): void {
		$payload = $this->continue_payload();

		$this->assertSame( 'test-key', $payload['apiKey'] );
		$this->assertSame( 'resp_123', $payload['previous_response_id'] );
		$this->assertSame( self::valid_continue_body()['function_call_outputs'], $payload['function_call_outputs'] );
		$this->assertSame( 'continue', $payload['kind'] );
		$this->assertSame( 'ask', $payload['mode'] );
		$this->assertArrayHasKey( 'reasoning', $payload );
		$this->assertIsBool( $payload['reasoning'] );
	}

	/**
	 * Test that continue payload uses provided mode when mode is included.
	 */
	public function test_continue_payload_uses_provided_mode_when_mode_is_included(): void {
		$payload = $this->continue_payload( array( 'mode' => 'build' ) );
		$this->assertSame( 'build', $payload['mode'] );
	}

	/**
	 * Test that split SSE frames are parsed when chunk boundaries split JSON.
	 */
	public function test_split_sse_frames_are_parsed_when_chunk_boundaries_split_json(): void {
		$deltas = $this->collect_deltas_from_stream(
			array(
				'data: {"type":"response.output_text.delta"',
				",\"delta\":\"Hello\"}\n\n",
			)
		);

		$this->assertSame( array( 'Hello' ), $deltas );
	}

	/**
	 * Test that [DONE] frame is ignored when processing stream chunks.
	 */
	public function test_done_frame_is_ignored_when_processing_stream_chunks(): void {
		$deltas = $this->collect_deltas_from_stream(
			array(
				"data: [DONE]\n\n",
				"data: {\"type\":\"response.output_text.delta\",\"delta\":\"after\"}\n\n",
			)
		);

		$this->assertSame( array( 'after' ), $deltas );
	}

	/**
	 * Test that invalid JSON frame is ignored when processing stream chunks.
	 */
	public function test_invalid_json_frame_is_ignored_when_processing_stream_chunks(): void {
		$deltas = $this->collect_deltas_from_stream(
			array(
				"data: not-json\n\n",
				"data: {\"type\":\"response.output_text.delta\",\"delta\":\"still works\"}\n\n",
			)
		);

		$this->assertSame( array( 'still works' ), $deltas );
	}

	/**
	 * Test that reasoning callback receives delta and completion marker.
	 */
	public function test_reasoning_callback_receives_delta_and_completion_marker(): void {
		$reasoning = array();
		[ $provider ] = $this->make_provider(
			array(
				"data: {\"type\":\"response.reasoning_summary_text.delta\",\"delta\":\"thinking\"}\n\n",
				"data: {\"type\":\"response.reasoning_summary_text.done\"}\n\n",
			)
		);

		$provider->stream_ai_response(
			self::one_message(),
			AiMode::Ask,
			array(
				'on_delta' => function () {},
				'on_reasoning' => function ( ?string $delta ) use ( &$reasoning ) {
					$reasoning[] = $delta;
				},
			),
			array()
		);

		$this->assertSame( array( 'thinking', null ), $reasoning );
	}

	/**
	 * Test that client tool calls callback receives tool call event payload.
	 */
	public function test_client_tool_calls_callback_receives_tool_call_event_payload(): void {
		$events = array();
		[ $provider ] = $this->make_provider(
			array(
				"data: {\"type\":\"etch.client_tool_calls\",\"previous_response_id\":\"resp_1\",\"tool_call_ids\":[\"call_1\"]}\n\n",
			)
		);

		$provider->stream_ai_response(
			self::one_message(),
			AiMode::Ask,
			array(
				'on_delta' => function () {},
				'on_client_tool_calls' => function ( array $event ) use ( &$events ) {
					$events[] = $event;
				},
			),
			array()
		);

		$this->assertCount( 1, $events );
		$this->assertSame( 'etch.client_tool_calls', $events[0]['type'] );
		$this->assertSame( 'resp_1', $events[0]['previous_response_id'] );
	}

	/**
	 * Test that initial payload includes supported client tools when flag is on and provided.
	 */
	public function test_initial_payload_includes_supported_client_tools_when_flag_on_and_provided(): void {
		$this->enable_flag( 'ENABLE_AI_TOOLS_NEGOTIATION' );

		$supported = array( 'tool_one' );

		$payload = $this->initial_payload_with_tools(
			array(
				'supported_client_tools' => $supported,
			)
		);

		$this->assertArrayHasKey( 'supported_client_tools', $payload );
		$this->assertSame( $supported, $payload['supported_client_tools'] );
	}

	/**
	 * Test that initial payload includes empty supported tools when flag is on and empty array is provided.
	 */
	public function test_initial_payload_includes_empty_supported_tools_when_flag_on_and_empty_array_provided(): void {
		$this->enable_flag( 'ENABLE_AI_TOOLS_NEGOTIATION' );

		$supported = array();

		$payload = $this->initial_payload_with_tools(
			array(
				'supported_client_tools' => $supported,
			)
		);

		$this->assertArrayHasKey( 'supported_client_tools', $payload );
		$this->assertSame( $supported, $payload['supported_client_tools'] );
	}

	/**
	 * Test that continue payload includes supported tools when flag is on and provided.
	 */
	public function test_continue_payload_includes_supported_tools_when_flag_on_and_provided(): void {
		$this->enable_flag( 'ENABLE_AI_TOOLS_NEGOTIATION' );

		$supported = array( 'tool_one' );

		$payload = $this->continue_payload(
			array(
				'supported_client_tools' => $supported,
			)
		);

		$this->assertArrayHasKey( 'supported_client_tools', $payload );
		$this->assertSame( $supported, $payload['supported_client_tools'] );
	}

	/**
	 * Test that initial payload omits supported tools when negotiation flag is off even if tools are passed.
	 */
	public function test_initial_payload_omits_supported_tools_when_negotiation_flag_off_even_if_tools_provided(): void {
		$this->set_flag_value( 'ENABLE_AI_TOOLS_NEGOTIATION', 'off' );

		$payload = $this->initial_payload_with_tools(
			array(
				'supported_client_tools' => array( 'tool_one', 'tool_two' ),
			)
		);

		$this->assertArrayNotHasKey( 'supported_client_tools', $payload );
	}


	/**
	 * A valid body for stream_ai_continue.
	 *
	 * @return array<string, mixed>
	 */
	private static function valid_continue_body(): array {
		return array(
			'previous_response_id'  => 'resp_123',
			'function_call_outputs' => array(
				array(
					'call_id' => 'call_abc',
					'output' => 'result',
				),
			),
		);
	}

	/**
	 * Build a single SSE delta chunk.
	 *
	 * @param string $delta Delta content.
	 * @return array<string>
	 */
	private static function single_delta_chunk( string $delta ): array {
		return array( "data: {\"type\":\"response.output_text.delta\",\"delta\":\"{$delta}\"}\n\n" );
	}

	/**
	 * Build the standard two-delta hello world chunk set.
	 *
	 * @return array<string>
	 */
	private static function hello_world_delta_chunks(): array {
		return array(
			"data: {\"type\":\"response.output_text.delta\",\"delta\":\"Hello\"}\n\n",
			"data: {\"type\":\"response.output_text.delta\",\"delta\":\" world\"}\n\n",
		);
	}

	/**
	 * Create a MiddlewareProvider backed by a FakeHttpTransport with a 'test-key' API key.
	 *
	 * @param array<string>  $chunks       SSE chunks to emit.
	 * @param \WP_Error|null $stream_error Optional transport error.
	 * @return array{0: MiddlewareProvider, 1: FakeHttpTransport}
	 */
	private function make_provider( array $chunks = array(), ?\WP_Error $stream_error = null ): array {
		$transport = new FakeHttpTransport(
			array(
				'status' => 200,
				'body' => '',
			),
			$chunks,
			$stream_error
		);
		return array( new MiddlewareProvider( $transport, 'test-key' ), $transport );
	}

	/**
	 * Helper: call stream_ai_continue with canned SSE chunks.
	 *
	 * @param array<string> $chunks   The SSE chunks to feed.
	 * @param callable|null $on_delta Optional delta callback.
	 *
	 * @return ?WP_Error
	 */
	private function continue_with_chunks( array $chunks, ?callable $on_delta = null ): ?\WP_Error {
		[ $provider ] = $this->make_provider( $chunks );
		return $provider->stream_ai_continue( self::valid_continue_body(), array( 'on_delta' => $on_delta ?? function () {} ) );
	}

	/**
	 * Collect deltas from a stream_ai_response call.
	 *
	 * @param array<string> $chunks SSE chunks.
	 * @return array<int, string>
	 */
	private function collect_deltas_from_stream( array $chunks ): array {
		return $this->collect_deltas_for_chunks(
			$chunks,
			function ( array $chunks, callable $on_delta ): void {
				$this->stream_with_chunks( $chunks, $on_delta );
			}
		);
	}

	/**
	 * Collect deltas from a stream_ai_continue call.
	 *
	 * @param array<string> $chunks SSE chunks.
	 * @return array<int, string>
	 */
	private function collect_deltas_from_continue( array $chunks ): array {
		return $this->collect_deltas_for_chunks(
			$chunks,
			function ( array $chunks, callable $on_delta ): void {
				$this->continue_with_chunks( $chunks, $on_delta );
			}
		);
	}

	/**
	 * Shared helper: run a stream with canned chunks and collect delta strings.
	 *
	 * @param array<string> $chunks SSE chunks.
	 * @param callable      $runner Invoked as ( chunks, on_delta ); typically stream_with_chunks or continue_with_chunks.
	 *
	 * @phpstan-param callable(array<string>, callable(string): void): void $runner
	 *
	 * @return array<int, string>
	 */
	private function collect_deltas_for_chunks( array $chunks, callable $runner ): array {
		$deltas = array();
		$runner(
			$chunks,
			function ( string $delta ) use ( &$deltas ): void {
				$deltas[] = $delta;
			}
		);

		return $deltas;
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
	 * Helper: call stream_ai_response with canned SSE chunks.
	 *
	 * @param array<string> $chunks   The SSE chunks to feed.
	 * @param callable|null $on_delta Optional delta callback.
	 *
	 * @return ?WP_Error
	 */
	private function stream_with_chunks( array $chunks, ?callable $on_delta = null ): ?\WP_Error {
		[ $provider ] = $this->make_provider( $chunks );
		return $provider->stream_ai_response( self::one_message(), AiMode::Ask, array( 'on_delta' => $on_delta ?? function () {} ) );
	}

	/**
	 * Build and decode the initial payload sent to middleware.
	 *
	 * @return array<string, mixed>
	 */
	private function initial_payload(): array {
		return $this->initial_payload_with_tools( array() );
	}

	/**
	 * Build and decode the continue payload sent to middleware.
	 *
	 * @param array<string, mixed> $overrides Continue body overrides.
	 * @return array<string, mixed>
	 */
	private function continue_payload( array $overrides = array() ): array {
		[ $provider, $transport ] = $this->make_provider( self::single_delta_chunk( 'ok' ) );
		$provider->stream_ai_continue( array_merge( self::valid_continue_body(), $overrides ), array( 'on_delta' => function () {} ) );
		return json_decode( $transport->get_stream_bodies()[0], true );
	}

	/**
	 * Build and decode the initial payload sent to middleware, passing optional tools.
	 *
	 * @param array<string, mixed> $tools Initial tools (e.g. supported_client_tools).
	 * @return array<string, mixed>
	 */
	private function initial_payload_with_tools( array $tools ): array {
		[ $provider, $transport ] = $this->make_provider( self::single_delta_chunk( 'ok' ) );

		$provider->stream_ai_response(
			self::one_message(),
			AiMode::Ask,
			array( 'on_delta' => function () {} ),
			$tools
		);

		return json_decode( $transport->get_stream_bodies()[0], true );
	}
}
