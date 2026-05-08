<?php
/**
 * OpenAiStreamEventProcessor.php
 *
 * Routes decoded OpenAI streaming events to the appropriate callbacks.
 *
 * @package Etch\Services\Ai\AgentProviders
 */

declare(strict_types=1);

namespace Etch\Services\Ai\AgentProviders;

use Etch\Helpers\WideEventLogger;

/**
 * OpenAiStreamEventProcessor
 *
 * @package Etch\Services\Ai\AgentProviders
 */
class OpenAiStreamEventProcessor {

	/**
	 * Process a streaming event from OpenAI.
	 *
	 * @param array<string, mixed> $event     The decoded event.
	 * @param array                $callbacks Callbacks keyed on_delta (required), on_error, on_reasoning, on_response_completed, on_client_tool_calls.
	 *
	 * @phpstan-param array{on_delta: callable, on_error?: callable|null, on_reasoning?: callable|null, on_response_completed?: callable|null, on_client_tool_calls?: callable|null} $callbacks
	 *
	 * @return void
	 */
	public function process( array $event, array $callbacks ): void {
		$callbacks = $this->normalize_callbacks( $callbacks );
		$type      = $event['type'] ?? '';

		// TODO: remove this conditional when we finish the tests for middleware.
		if ( 'response.meta' === $type ) {
			WideEventLogger::set( 'ai.response.source', 'middleware' );
		}

		switch ( $type ) {
			case 'response.output_text.delta':
				$this->handle_delta( $event, $callbacks['on_delta'] );
				return;

			case 'response.reasoning_summary_text.delta':
				$this->handle_reasoning_delta( $event, $callbacks['on_reasoning'] );
				return;

			case 'response.reasoning_summary_text.done':
				$this->handle_reasoning_done( $callbacks['on_reasoning'] );
				return;

			case 'response.completed':
				$this->handle_completed( $event, $callbacks['on_response_completed'] );
				return;

			case 'etch.client_tool_calls':
				$this->handle_client_tool_calls( $event, $callbacks['on_client_tool_calls'] );
				return;

			case 'response.error':
			case 'error':
				$this->handle_error( $event, $callbacks['on_error'] );
				return;
		}
	}

	/**
	 * Normalize a callbacks array, returning null for absent or unset optional entries.
	 *
	 * @param array $callbacks Incoming callbacks array.
	 *
	 * @phpstan-param array{on_delta: callable, on_error?: callable|null, on_reasoning?: callable|null, on_response_completed?: callable|null, on_client_tool_calls?: callable|null} $callbacks
	 * @phpstan-return array{on_delta: callable, on_error: callable|null, on_reasoning: callable|null, on_response_completed: callable|null, on_client_tool_calls: callable|null}
	 *
	 * @return array
	 */
	private function normalize_callbacks( array $callbacks ): array {
		return array(
			'on_delta'              => $callbacks['on_delta'],
			'on_error'              => $callbacks['on_error'] ?? null,
			'on_reasoning'          => $callbacks['on_reasoning'] ?? null,
			'on_response_completed' => $callbacks['on_response_completed'] ?? null,
			'on_client_tool_calls'  => $callbacks['on_client_tool_calls'] ?? null,
		);
	}

	/**
	 * Handle a text output delta event.
	 *
	 * @param array<string, mixed> $event    The decoded event.
	 * @param callable             $on_delta Callback for text deltas.
	 *
	 * @return void
	 */
	private function handle_delta( array $event, callable $on_delta ): void {
		$delta = $this->extract_delta( $event );
		if ( null !== $delta ) {
			$on_delta( $delta );
		}
	}

	/**
	 * Handle a reasoning summary delta event.
	 *
	 * @param array<string, mixed> $event        The decoded event.
	 * @param callable|null        $on_reasoning Callback for reasoning deltas.
	 *
	 * @return void
	 */
	private function handle_reasoning_delta( array $event, ?callable $on_reasoning ): void {
		if ( null === $on_reasoning ) {
			return;
		}

		$delta = $this->extract_delta( $event );
		if ( null !== $delta ) {
			$on_reasoning( $delta );
		}
	}

	/**
	 * Handle a reasoning summary done event.
	 *
	 * @param callable|null $on_reasoning Callback for reasoning completion.
	 *
	 * @return void
	 */
	private function handle_reasoning_done( ?callable $on_reasoning ): void {
		if ( null !== $on_reasoning ) {
			$on_reasoning( null );
		}
	}

	/**
	 * Handle a response completed event.
	 *
	 * @param array<string, mixed> $event                 The decoded event.
	 * @param callable|null        $on_response_completed Callback for response completion.
	 *
	 * @return void
	 */
	private function handle_completed( array $event, ?callable $on_response_completed ): void {
		if ( null === $on_response_completed ) {
			return;
		}

		$response = $event['response'] ?? null;
		if ( is_array( $response ) ) {
			$on_response_completed( $response );
		}
	}

	/**
	 * Forward etch.client_tool_calls events to the consumer.
	 *
	 * @param array<string, mixed> $event                Decoded SSE data.
	 * @param callable|null        $on_client_tool_calls Optional callback.
	 *
	 * @return void
	 */
	private function handle_client_tool_calls( array $event, ?callable $on_client_tool_calls ): void {
		if ( null === $on_client_tool_calls ) {
			return;
		}

		$on_client_tool_calls( $event );
	}

	/**
	 * Handle an error event.
	 *
	 * @param array<string, mixed> $event    The decoded event.
	 * @param callable|null        $on_error Callback for errors.
	 *
	 * @return void
	 */
	private function handle_error( array $event, ?callable $on_error ): void {
		if ( null !== $on_error ) {
			$on_error( $event );
		}
	}

	/**
	 * Extract a non-empty string delta from an event, or return null.
	 *
	 * @param array<string, mixed> $event The decoded event.
	 *
	 * @return string|null The delta string, or null if absent or empty.
	 */
	private function extract_delta( array $event ): ?string {
		$delta = $event['delta'] ?? null;

		if ( is_string( $delta ) && '' !== $delta ) {
			return $delta;
		}

		return null;
	}
}
