<?php
/**
 * AiServiceTest.php
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Services\Tests;

use Etch\Services\AiService;
use Etch\Services\Ai\AiMode;
use WP_UnitTestCase;

/**
 * Class AiServiceTest
 *
 * Tests the AiService class.
 */
class AiServiceTest extends WP_UnitTestCase {

	/**
	 * Test that deltas are streamed when sending a message.
	 */
	public function test_deltas_are_streamed_when_sending_a_message(): void {
		$ai_service = new AiService( new FakeAiProvider( array( 'Hello', ', world!' ) ) );

		$deltas = array();
		$ai_service->stream_ai_response(
			self::one_message(),
			AiMode::Ask,
			array(
				'on_delta' => function ( string $delta ) use ( &$deltas ) {
					$deltas[] = $delta;
				},
			)
		);

		$this->assertSame( array( 'Hello', ', world!' ), $deltas );
	}

	/**
	 * Test that all messages are forwarded to provider when streaming.
	 */
	public function test_all_messages_are_forwarded_to_provider_when_streaming(): void {
		$provider   = new FakeAiProvider( array( 'ok' ) );
		$ai_service = new AiService( $provider );

		$messages = array(
			array(
				'role' => 'user',
				'content' => 'first question',
			),
			array(
				'role' => 'assistant',
				'content' => 'first answer',
			),
			array(
				'role' => 'user',
				'content' => '  second question  ',
			),
		);

		$ai_service->stream_ai_response( $messages, AiMode::Ask, array( 'on_delta' => function () {} ) );

		$this->assertSame(
			array(
				array(
					'role' => 'user',
					'content' => 'first question',
				),
				array(
					'role' => 'assistant',
					'content' => 'first answer',
				),
				array(
					'role' => 'user',
					'content' => 'second question',
				),
			),
			$provider->get_received_messages()
		);
	}

	/**
	 * Test that validation error is returned when content is whitespace only.
	 */
	public function test_validation_error_is_returned_when_content_is_whitespace_only(): void {
		$result = $this->stream_with_message( 'user', '   ' );

		$this->assertWPError( $result );
	}

	/**
	 * Test that provider is not called when validation fails.
	 */
	public function test_provider_is_not_called_when_validation_fails(): void {
		$provider   = new FakeAiProvider();
		$ai_service = new AiService( $provider );

		$ai_service->stream_ai_response(
			array(
				array(
					'role' => 'hacker',
					'content' => 'Hello',
				),
			),
			AiMode::Ask,
			array( 'on_delta' => function () {} ),
		);

		$this->assertNull( $provider->get_received_messages() );
	}

	/**
	 * Test that validation error is returned when message is invalid.
	 */
	public function test_validation_error_is_returned_when_message_is_invalid(): void {
		$result = $this->stream_with_message( 'hacker', 'Hello' );

		$this->assertWPError( $result );
		$this->assertSame( 'etch_ai_invalid_message', $result->get_error_code() );
	}

	/**
	 * Test that reasoning callback is null for provider when reasoning is disabled.
	 */
	public function test_reasoning_callback_is_null_for_provider_when_reasoning_is_disabled(): void {
		$provider   = new FakeAiProvider( array( 'ok' ) );
		$ai_service = new AiService( $provider, false );

		$ai_service->stream_ai_response(
			self::one_message(),
			AiMode::Ask,
			array(
				'on_delta'     => function () {},
				'on_error'     => function () {},
				'on_reasoning' => function () {},
			),
		);

		$this->assertFalse( $provider->received_reasoning_callback() );
	}

	/**
	 * Test that reasoning text reaches caller when reasoning is enabled.
	 */
	public function test_reasoning_text_reaches_caller_when_reasoning_is_enabled(): void {
		$result = $this->stream_with_reasoning( array( 'Thinking...' ) );

		$this->assertSame( array( 'Thinking...' ), $result['reasoning'] );
	}

	/**
	 * Test that reasoning done reaches caller when reasoning is enabled.
	 */
	public function test_reasoning_done_reaches_caller_when_reasoning_is_enabled(): void {
		$result = $this->stream_with_reasoning( array( null ) );

		$this->assertTrue( $result['done'] );
	}

	/**
	 * Test that deltas are streamed when continuing after client-side tool execution.
	 */
	public function test_deltas_are_streamed_when_continuing(): void {
		$provider   = ( new FakeAiProvider() )->with_continue( array( 'part one', ' part two' ) );
		$ai_service = new AiService( $provider );

		$deltas = array();
		$ai_service->stream_ai_continue(
			array( 'previous_response_id' => 'resp_123' ),
			array(
				'on_delta' => function ( string $delta ) use ( &$deltas ) {
					$deltas[] = $delta;
				},
			)
		);

		$this->assertSame( array( 'part one', ' part two' ), $deltas );
	}

	/**
	 * Test that provider error is returned when continue fails.
	 */
	public function test_provider_error_is_returned_when_continue_fails(): void {
		$provider   = ( new FakeAiProvider() )->with_continue( array(), new \WP_Error( 'continue_fail', 'Continue failed' ) );
		$ai_service = new AiService( $provider );

		$result = $ai_service->stream_ai_continue(
			array( 'previous_response_id' => 'resp_123' ),
			array( 'on_delta' => function () {} )
		);

		$this->assertWPError( $result );
		$this->assertSame( 'Continue failed', $result->get_error_message() );
	}

	/**
	 * Test that reasoning callback is wrapped for provider when continuing with reasoning enabled.
	 */
	public function test_reasoning_callback_is_wrapped_when_continuing_with_reasoning_enabled(): void {
		$this->assertTrue( $this->continue_with_reasoning_setting( true ) );
	}

	/**
	 * Test that reasoning callback is null for provider when continuing with reasoning disabled.
	 */
	public function test_reasoning_callback_is_null_for_provider_when_continuing_with_reasoning_disabled(): void {
		$this->assertFalse( $this->continue_with_reasoning_setting( false ) );
	}

	/**
	 * Helper: run stream_ai_continue with reasoning on/off and return whether the provider received a reasoning callback.
	 *
	 * @param bool $reasoning_enabled Whether reasoning is enabled on the AiService.
	 * @return bool
	 */
	private function continue_with_reasoning_setting( bool $reasoning_enabled ): bool {
		$provider   = new FakeAiProvider();
		$ai_service = new AiService( $provider, $reasoning_enabled );

		$ai_service->stream_ai_continue(
			array( 'previous_response_id' => 'resp_123' ),
			array(
				'on_delta'     => function () {},
				'on_reasoning' => function () {},
			)
		);

		return $provider->received_continue_reasoning_callback();
	}

	/**
	 * Test that provider error is returned when streaming fails.
	 */
	public function test_provider_error_is_returned_when_streaming_fails(): void {
		$result = $this->stream_with_message(
			'user',
			'Hello',
			new \WP_Error( 'provider_fail', 'API rate limit exceeded' ),
		);

		$this->assertWPError( $result );
		$this->assertSame( 'API rate limit exceeded', $result->get_error_message() );
	}

	/**
	 * A single valid user message.
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
	 * Helper: stream with reasoning enabled, return captured reasoning text and done flag.
	 *
	 * @param array<string|null> $reasoning_events Events the fake provider should emit.
	 * @param AiMode             $mode           The mode to use for the AI response.
	 *
	 * @return array{reasoning: array<string>, done: bool}
	 */
	private function stream_with_reasoning( array $reasoning_events, AiMode $mode = AiMode::Ask ): array {
		$ai_service = new AiService( new FakeAiProvider( array( 'ok' ), null, $reasoning_events ), true );

		$reasoning = array();
		$done      = false;

		$ai_service->stream_ai_response(
			self::one_message(),
			$mode,
			array(
				'on_delta'          => function () {},
				'on_reasoning'      => function ( string $text ) use ( &$reasoning ) {
					$reasoning[] = $text;
				},
				'on_reasoning_done' => function () use ( &$done ) {
					$done = true;
				},
			),
		);

		return array(
			'reasoning' => $reasoning,
			'done'      => $done,
		);
	}

	/**
	 * Helper: stream a single message with a given role/content, return result.
	 *
	 * @param string         $role           Message role.
	 * @param string         $content        Message content.
	 * @param \WP_Error|null $provider_error Optional error the provider should return.
	 * @param AiMode         $mode           The mode to use for the AI response.
	 * @return mixed The result from stream_ai_response.
	 */
	private function stream_with_message( string $role, string $content, ?\WP_Error $provider_error = null, AiMode $mode = AiMode::Ask ) {
		$ai_service = new AiService( new FakeAiProvider( array(), $provider_error ) );

		return $ai_service->stream_ai_response(
			array(
				array(
					'role' => $role,
					'content' => $content,
				),
			),
			$mode,
			array( 'on_delta' => function () {} ),
		);
	}
}
