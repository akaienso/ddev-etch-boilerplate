<?php
/**
 * AiService.php
 *
 * Entry point for AI operations. Delegates to the configured provider.
 *
 * PHP version 8.2+
 *
 * @category  Plugin
 * @package   Etch\Services
 */

declare(strict_types=1);

namespace Etch\Services;

use Etch\Helpers\Flag;
use Etch\Services\Ai\AiProviderInterface;
use Etch\Services\Ai\AiMode;
use WP_Error;

/**
 * AiService
 *
 * @phpstan-import-type AiChatMessages from \Etch\Services\Ai\AiProviderInterface
 * @package Etch\Services
 */
class AiService {

	/**
	 * The allowed message roles.
	 *
	 * @var array<string>
	 */
	private const ALLOWED_ROLES = array( 'user', 'assistant' );

	/**
	 * The AI provider instance.
	 *
	 * @var AiProviderInterface
	 */
	private AiProviderInterface $ai_provider;

	/**
	 * Whether AI reasoning is enabled.
	 *
	 * @var bool
	 */
	private bool $reasoning_enabled;

	/**
	 * Constructor.
	 *
	 * @param AiProviderInterface $ai_provider       The AI provider instance.
	 * @param bool|null           $reasoning_enabled Whether reasoning is enabled (defaults to ENABLE_AI_REASONING flag).
	 */
	public function __construct( AiProviderInterface $ai_provider, ?bool $reasoning_enabled = null ) {
		$this->ai_provider       = $ai_provider;
		$this->reasoning_enabled = $reasoning_enabled ?? Flag::is_on( 'ENABLE_AI_REASONING' );
	}

	/**
	 * Stream an AI response.
	 *
	 * @param array                $messages  The messages to generate an AI response for.
	 * @param AiMode               $mode      The mode to use for the AI response.
	 * @param array                $callbacks Callbacks: on_delta (required), on_error, on_reasoning, on_reasoning_done, on_client_tool_calls.
	 * @param array<string, mixed> $tools Optional tools for middleware to negotiate (initial requests).
	 *
	 * @phpstan-param array<int, mixed> $messages
	 * @phpstan-param array{on_delta: callable, on_error?: callable|null, on_reasoning?: callable|null, on_reasoning_done?: callable|null, on_client_tool_calls?: callable|null} $callbacks
	 * @phpstan-param array<string, mixed> $tools
	 *
	 * @return ?WP_Error Returns WP_Error on failure, null on success.
	 */
	public function stream_ai_response( array $messages, AiMode $mode, array $callbacks, array $tools = array() ): ?WP_Error {
		$validated = $this->validate_messages( $messages );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		return $this->ai_provider->stream_ai_response(
			$validated,
			$mode,
			$this->build_provider_callbacks( $callbacks ),
			$tools
		);
	}

	/**
	 * Continue streaming after client-side tool execution.
	 *
	 * @param array<string, mixed> $body      Partial JSON from the REST client (mode, previous_response_id, function_call_outputs).
	 * @param array                $callbacks Callbacks: on_delta (required), on_error, on_reasoning, on_reasoning_done, on_client_tool_calls.
	 *
	 * @phpstan-param array{on_delta: callable, on_error?: callable|null, on_reasoning?: callable|null, on_reasoning_done?: callable|null, on_client_tool_calls?: callable|null} $callbacks
	 *
	 * @return ?WP_Error
	 */
	public function stream_ai_continue( array $body, array $callbacks ): ?WP_Error {
		return $this->ai_provider->stream_ai_continue(
			$body,
			$this->build_provider_callbacks( $callbacks )
		);
	}

	/**
	 * Build the provider callbacks array from the public callbacks, wrapping reasoning if enabled.
	 *
	 * @param array $callbacks Callbacks: on_delta (required), on_error, on_reasoning, on_reasoning_done, on_client_tool_calls.
	 *
	 * @phpstan-param array{on_delta: callable, on_error?: callable|null, on_reasoning?: callable|null, on_reasoning_done?: callable|null, on_client_tool_calls?: callable|null} $callbacks
	 * @return array{on_delta: callable, on_error: callable|null, on_reasoning: callable|null, on_client_tool_calls: callable|null}
	 */
	private function build_provider_callbacks( array $callbacks ): array {
		return array(
			'on_delta'             => $callbacks['on_delta'],
			'on_error'             => $callbacks['on_error'] ?? null,
			'on_reasoning'         => $this->build_reasoning_callback(
				$callbacks['on_reasoning'] ?? null,
				$callbacks['on_reasoning_done'] ?? null
			),
			'on_client_tool_calls' => $callbacks['on_client_tool_calls'] ?? null,
		);
	}

	/**
	 * Build the provider-facing reasoning callback, or return null when reasoning is disabled or no handlers are set.
	 *
	 * @param callable|null $on_reasoning      Called with the reasoning text chunk.
	 * @param callable|null $on_reasoning_done Called with no arguments when reasoning finishes.
	 * @return callable|null
	 */
	private function build_reasoning_callback( ?callable $on_reasoning, ?callable $on_reasoning_done ): ?callable {
		if ( ! $this->reasoning_enabled ) {
			return null;
		}

		if ( null === $on_reasoning && null === $on_reasoning_done ) {
			return null;
		}

		return static function ( ?string $reasoning ) use ( $on_reasoning, $on_reasoning_done ): void {
			if ( null === $reasoning ) {
				if ( null !== $on_reasoning_done ) {
					$on_reasoning_done();
				}
			} elseif ( null !== $on_reasoning ) {
				$on_reasoning( $reasoning );
			}
		};
	}

	/**
	 * Validate the messages array.
	 *
	 * @param array<int, mixed> $messages The messages to validate.
	 *
	 * @phpstan-param array<int, mixed> $messages
	 * @phpstan-return AiChatMessages|WP_Error
	 *
	 * @return array<int, array{role: string, content: string|list<array<string, mixed>>}>|WP_Error The validated messages or an error.
	 */
	private function validate_messages( array $messages ): array|WP_Error {
		if ( count( $messages ) === 0 ) {
			return new WP_Error( 'etch_ai_invalid_messages', 'At least one message is required.', array( 'status' => 400 ) );
		}

		$validated = array();
		foreach ( $messages as $message ) {
			$result = $this->validate_message( $message );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$validated[] = $result;
		}

		return $validated;
	}

	/**
	 * Validate a single message and return its normalized form.
	 *
	 * @param mixed $message The message to validate.
	 * @return array{role: string, content: string|list<array<string, mixed>>}|WP_Error The validated message or an error.
	 */
	private function validate_message( mixed $message ): array|WP_Error {
		if ( ! is_array( $message ) ) {
			return new WP_Error( 'etch_ai_invalid_message', 'Message must be an array.', array( 'status' => 400 ) );
		}

		$role = trim( (string) ( $message['role'] ?? '' ) );
		if ( ! in_array( $role, self::ALLOWED_ROLES, true ) ) {
			return new WP_Error( 'etch_ai_invalid_message', 'Message role must be user or assistant.', array( 'status' => 400 ) );
		}

		$content = $message['content'] ?? null;

		if ( is_string( $content ) ) {
			$content = trim( $content );
			if ( '' === $content ) {
				return new WP_Error( 'etch_ai_invalid_message', 'Message must have content.', array( 'status' => 400 ) );
			}

			return array(
				'role'    => $role,
				'content' => $content,
			);
		}

		if ( is_array( $content ) && ! empty( $content ) ) {
			return array(
				'role'    => $role,
				'content' => $content,
			);
		}

		return new WP_Error( 'etch_ai_invalid_message', 'Message must have content.', array( 'status' => 400 ) );
	}
}
