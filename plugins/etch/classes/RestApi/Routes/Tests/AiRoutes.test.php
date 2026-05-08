<?php
/**
 * AiRoutesTest.php
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\RestApi\Routes\Tests;

use Etch\RestApi\Routes\AiRoutes;
use Etch\Services\AiService;
use Etch\Services\Tests\FakeAiProvider;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Class AiRoutesTest
 *
 * Tests the AiRoutes class through its public endpoint methods.
 */
class AiRoutesTest extends WP_UnitTestCase {

	/**
	 * Test that stream sends json error when messages key is missing.
	 */
	public function test_stream_sends_json_error_when_messages_key_is_missing(): void {
		$emitter = $this->stream_with_body( array() );

		$this->assertSame( 400, $emitter->error_response['status'] );
	}

	/**
	 * Test that emitter is not started when stream validation fails.
	 */
	public function test_emitter_is_not_started_when_stream_validation_fails(): void {
		$emitter = $this->stream_with_body( array() );

		$this->assertFalse( $emitter->started );
	}

	/**
	 * Test that stream emits deltas when provider succeeds.
	 */
	public function test_stream_emits_deltas_when_provider_succeeds(): void {
		$emitter = $this->stream_with_body(
			self::valid_body(),
			new FakeAiProvider( array( 'Hello', ' world' ) )
		);

		$delta_texts = array_map(
			fn( $e ) => $e['data']['text'],
			array_values(
				array_filter( $emitter->events, fn( $e ) => 'delta' === $e['event'] )
			)
		);

		$this->assertSame( array( 'Hello', ' world' ), $delta_texts );
	}

	/**
	 * Test that stream emits error when provider fails.
	 */
	public function test_stream_emits_error_when_provider_fails(): void {
		$provider = new FakeAiProvider( array(), new \WP_Error( 'fail', 'Boom' ) );
		$emitter  = $this->stream_with_body( self::valid_body(), $provider );

		$error_events = array_values(
			array_filter( $emitter->events, fn( $e ) => 'error' === $e['event'] )
		);

		$this->assertSame( 'Boom', $error_events[0]['data']['message'] );
	}

	/**
	 * Test that done event is always last when stream completes.
	 */
	public function test_done_event_is_always_last_when_stream_completes(): void {
		$emitter = $this->stream_with_body(
			self::valid_body(),
			new FakeAiProvider( array( 'Hi' ) )
		);

		$last = end( $emitter->events );
		$this->assertSame( 'done', $last['event'] );
	}

	/**
	 * Test that initial stream passes supported_client_tools to the service when present in the request body.
	 */
	public function test_initial_stream_passes_supported_client_tools_to_service_when_present_in_request_body(): void {
		$provider = new FakeAiProvider( array( 'ok' ) );
		$body       = self::valid_body();
		$body['supported_client_tools'] = array( 'tool_one', 'tool_two' );

		$this->stream_with_body( $body, $provider );

		$tools = $provider->get_received_tools();
		$this->assertSame( array( 'tool_one', 'tool_two' ), $tools['supported_client_tools'] );
	}

	/**
	 * Test that initial stream passes empty tools when supported_client_tools is absent from the body.
	 */
	public function test_initial_stream_passes_empty_tools_when_supported_client_tools_absent(): void {
		$provider = new FakeAiProvider( array( 'ok' ) );

		$this->stream_with_body( self::valid_body(), $provider );

		$this->assertSame( array(), $provider->get_received_tools() );
	}

	/**
	 * Test that continue stream sends error when body is not valid JSON.
	 */
	public function test_continue_stream_sends_error_when_body_is_invalid(): void {
		$emitter = $this->continue_stream_with_invalid_body();

		$this->assertSame( 400, $emitter->error_response['status'] );
	}

	/**
	 * Test that continue stream emitter is not started when body is invalid.
	 */
	public function test_continue_stream_emitter_is_not_started_when_body_is_invalid(): void {
		$emitter = $this->continue_stream_with_invalid_body();

		$this->assertFalse( $emitter->started );
	}

	/**
	 * Test that continue stream emits deltas when provider succeeds.
	 */
	public function test_continue_stream_emits_deltas_when_provider_succeeds(): void {
		$provider = ( new FakeAiProvider() )->with_continue( array( 'Hello', ' world' ) );
		$emitter  = $this->continue_stream_with_body( array(), $provider );

		$delta_texts = array_map(
			fn( $e ) => $e['data']['text'],
			array_values(
				array_filter( $emitter->events, fn( $e ) => 'delta' === $e['event'] )
			)
		);

		$this->assertSame( array( 'Hello', ' world' ), $delta_texts );
	}

	/**
	 * Test that continue stream emits error event when provider fails.
	 */
	public function test_continue_stream_emits_error_when_provider_fails(): void {
		$provider = ( new FakeAiProvider() )->with_continue( array(), new \WP_Error( 'fail', 'Continue fail' ) );
		$emitter  = $this->continue_stream_with_body( array(), $provider );

		$error_events = array_values(
			array_filter( $emitter->events, fn( $e ) => 'error' === $e['event'] )
		);

		$this->assertSame( 'Continue fail', $error_events[0]['data']['message'] );
	}

	/**
	 * Test that done event is always last when continue stream completes.
	 */
	public function test_done_event_is_always_last_when_continue_stream_completes(): void {
		$emitter = $this->continue_stream_with_body( array() );

		$last = end( $emitter->events );
		$this->assertSame( 'done', $last['event'] );
	}

	/**
	 * Test that continue stream sends error when previous_response_id is missing.
	 */
	public function test_continue_stream_sends_error_when_previous_response_id_is_missing(): void {
		$emitter = $this->continue_stream_with_body( array( 'previous_response_id' => '' ) );

		$this->assertSame( 400, $emitter->error_response['status'] );
	}

	/**
	 * Test that continue stream sends error when function_call_outputs is missing.
	 */
	public function test_continue_stream_sends_error_when_function_call_outputs_is_missing(): void {
		$body = self::valid_continue_body();
		unset( $body['function_call_outputs'] );
		$emitter = $this->stream_with_body( $body );

		$this->assertSame( 400, $emitter->error_response['status'] );
	}

	/**
	 * Helper: run handle_continue_stream and return the fake emitter.
	 *
	 * @param array<string, mixed> $body     The request body.
	 * @param FakeAiProvider|null  $provider Optional fake provider.
	 *
	 * @return FakeServerSideEventEmitter
	 */
	private function continue_stream_with_body( array $body, ?FakeAiProvider $provider = null ): FakeServerSideEventEmitter {
		$routes  = $this->create_routes( $provider );
		$emitter = new FakeServerSideEventEmitter();
		$body    = array_merge( self::valid_continue_body(), $body );

		$routes->handle_unified_stream( self::json_request( $body ), $emitter );

		return $emitter;
	}

	/**
	 * Helper: run handle_continue_stream with an unparseable body.
	 *
	 * @return FakeServerSideEventEmitter
	 */
	private function continue_stream_with_invalid_body(): FakeServerSideEventEmitter {
		$routes  = $this->create_routes();
		$emitter = new FakeServerSideEventEmitter();

		$request = new WP_REST_Request( 'POST' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( 'not-json' );

		$routes->handle_unified_stream( $request, $emitter );

		return $emitter;
	}

	/**
	 * Create an AiRoutes instance with an optional FakeAiProvider.
	 *
	 * @param FakeAiProvider|null $provider Optional fake provider.
	 *
	 * @return AiRoutes
	 */
	private function create_routes( ?FakeAiProvider $provider = null ): AiRoutes {
		$provider = $provider ?? new FakeAiProvider();

		return new AiRoutes( new AiService( $provider ) );
	}

	/**
	 * Create a WP_REST_Request with a JSON body.
	 *
	 * @param array<string, mixed> $body The body data.
	 *
	 * @return WP_REST_Request
	 */
	private static function json_request( array $body ): WP_REST_Request {
		$request = new WP_REST_Request( 'POST' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) );

		return $request;
	}

	/**
	 * Helper: stream a request and return the fake emitter for assertions.
	 *
	 * @param array<string, mixed> $body     The request body.
	 * @param FakeAiProvider|null  $provider Optional fake provider.
	 *
	 * @return FakeServerSideEventEmitter
	 */
	private function stream_with_body( array $body, ?FakeAiProvider $provider = null ): FakeServerSideEventEmitter {
		$routes  = $this->create_routes( $provider );
		$emitter = new FakeServerSideEventEmitter();

		$routes->handle_unified_stream( self::json_request( $body ), $emitter );

		return $emitter;
	}

	/**
	 * A valid request body with one user message.
	 *
	 * @return array<string, mixed>
	 */
	private static function valid_body(): array {
		return array(
			'kind' => 'initial',
			'mode' => 'ask',
			'messages' => array(
				array(
					'role'    => 'user',
					'content' => 'Hello',
				),
			),
		);
	}

	/**
	 * A valid continue request body.
	 *
	 * @return array<string, mixed>
	 */
	private static function valid_continue_body(): array {
		return array(
			'kind' => 'continue',
			'mode' => 'ask',
			'previous_response_id' => 'resp_123',
			'function_call_outputs' => array(
				array(
					'type' => 'function_call_output',
					'call_id' => 'call_123',
					'output' => '{}',
				),
			),
		);
	}
}
