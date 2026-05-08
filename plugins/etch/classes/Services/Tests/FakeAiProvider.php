<?php
/**
 * FakeAiProvider.php
 *
 * A fake AI provider for testing AiService behavior.
 *
 * @package Etch\Services\Tests
 */

declare(strict_types=1);

namespace Etch\Services\Tests;

use Etch\Services\Ai\AiProviderInterface;
use Etch\Services\Ai\AiMode;
use WP_Error;

/**
 * FakeAiProvider
 *
 * Implements AiProviderInterface with configurable behavior for testing.
 * Configured with deltas/errors to emit, then actually calls the callbacks.
 *
 * @phpstan-import-type AiChatMessages from AiProviderInterface
 */
class FakeAiProvider implements AiProviderInterface {

	/**
	 * The deltas to stream.
	 *
	 * @var array<string>
	 */
	private array $deltas;

	/**
	 * The error to return, if any.
	 *
	 * @var WP_Error|null
	 */
	private ?WP_Error $error;

	/**
	 * Reasoning events to emit (string for text, null for "done").
	 *
	 * @var array<string|null>
	 */
	private array $reasoning_events;

	/**
	 * The messages that were received (for assertion).
	 *
	 * @phpstan-var AiChatMessages|null
	 * @var array<int, array{role: string, content: string|list<array<string, mixed>>}>|null
	 */
	private ?array $received_messages = null;

	/**
	 * The reasoning callback received by the provider (for assertion).
	 *
	 * @var callable|null|false False means not yet called.
	 */
	private mixed $received_reasoning_callback = false;

	/**
	 * Deltas to emit during stream_ai_continue.
	 *
	 * @var array<string>
	 */
	private array $continue_deltas = array();

	/**
	 * Error to return from stream_ai_continue, if any.
	 *
	 * @var WP_Error|null
	 */
	private ?WP_Error $continue_error = null;

	/**
	 * The body received by stream_ai_continue (for assertion).
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $received_continue_body = null;

	/**
	 * Tools received by stream_ai_response (for assertion).
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $received_tools = null;

	/**
	 * The on_reasoning callback received by stream_ai_continue (for assertion).
	 *
	 * @var callable|null|false False means stream_ai_continue was not called.
	 */
	private mixed $received_continue_reasoning_callback = false;

	/**
	 * Constructor.
	 *
	 * @param array<string>      $deltas           The deltas to emit when streaming.
	 * @param WP_Error|null      $error            Optional error to return instead of streaming.
	 * @param array<string|null> $reasoning_events Reasoning events to emit (string = text, null = done).
	 */
	public function __construct( array $deltas = array(), ?WP_Error $error = null, array $reasoning_events = array() ) {
		$this->deltas           = $deltas;
		$this->error            = $error;
		$this->reasoning_events = $reasoning_events;
	}

	/**
	 * Configure continue-stream behavior and return $this for chaining.
	 *
	 * @param array<string> $deltas Deltas to emit during stream_ai_continue.
	 * @param WP_Error|null $error  Optional error to return from stream_ai_continue.
	 * @return static
	 */
	public function with_continue( array $deltas = array(), ?WP_Error $error = null ): static {
		$this->continue_deltas = $deltas;
		$this->continue_error  = $error;
		return $this;
	}

	/**
	 * Stream an AI response.
	 *
	 * @param array<int, array{role: string, content: string|list<array<string, mixed>>}>       $messages The messages.
	 * @param AiMode                                                                            $mode         The mode to use for the AI response.
	 * @param array{on_delta: callable, on_error?: callable|null, on_reasoning?: callable|null} $callbacks Callbacks keyed 'on_delta' (required), 'on_error' and 'on_reasoning' (optional).
	 * @param array<string, mixed>                                                              $tools Optional tools (ignored by fake).
	 *
	 * @phpstan-param AiChatMessages $messages
	 *
	 * @return ?WP_Error
	 */
	public function stream_ai_response( array $messages, AiMode $mode, array $callbacks, array $tools = array() ): ?WP_Error {
		$on_delta     = $callbacks['on_delta'];
		$on_reasoning = $callbacks['on_reasoning'] ?? null;

		$this->received_messages           = $messages;
		$this->received_reasoning_callback = $on_reasoning;
		$this->received_tools       = $tools;

		if ( null !== $this->error ) {
			return $this->error;
		}

		foreach ( $this->deltas as $delta ) {
			$on_delta( $delta );
		}

		if ( null !== $on_reasoning ) {
			foreach ( $this->reasoning_events as $event ) {
				$on_reasoning( $event );
			}
		}

		return null;
	}

	/**
	 * Continue streaming after client-side tool execution.
	 *
	 * @param array<string, mixed>                                                                                                    $body      Continuation context.
	 * @param array{on_delta: callable, on_error?: callable|null, on_reasoning?: callable|null, on_client_tool_calls?: callable|null} $callbacks Callbacks.
	 */
	public function stream_ai_continue( array $body, array $callbacks ): ?WP_Error {
		$on_delta     = $callbacks['on_delta'];
		$on_reasoning = $callbacks['on_reasoning'] ?? null;

		$this->received_continue_body               = $body;
		$this->received_continue_reasoning_callback = $on_reasoning;

		if ( null !== $this->continue_error ) {
			return $this->continue_error;
		}

		foreach ( $this->continue_deltas as $delta ) {
			$on_delta( $delta );
		}

		return null;
	}

	/**
	 * Get the messages that were received by the provider.
	 *
	 * @phpstan-return AiChatMessages|null
	 * @return array<int, array{role: string, content: string|list<array<string, mixed>>}>|null
	 */
	public function get_received_messages(): ?array {
		return $this->received_messages;
	}

	/**
	 * Whether the provider received a non-null reasoning callback.
	 *
	 * @return bool
	 */
	public function received_reasoning_callback(): bool {
		return null !== $this->received_reasoning_callback && false !== $this->received_reasoning_callback;
	}

	/**
	 * Get the body received by stream_ai_continue.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get_received_continue_body(): ?array {
		return $this->received_continue_body;
	}

	/**
	 * Tools passed to the last stream_ai_response call.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get_received_tools(): ?array {
		return $this->received_tools;
	}

	/**
	 * Whether stream_ai_continue received a non-null on_reasoning callback.
	 *
	 * @return bool
	 */
	public function received_continue_reasoning_callback(): bool {
		return null !== $this->received_continue_reasoning_callback && false !== $this->received_continue_reasoning_callback;
	}
}
