<?php
/**
 * AiProviderInterface.php
 *
 * This file contains the AiProviderInterface interface which defines the methods for an AI provider.
 *
 * @package Etch\Services\Ai
 */

declare(strict_types=1);
namespace Etch\Services\Ai;

use WP_Error;

/**
 * Contract for an AI provider.
 *
 * This interface defines the methods for an AI provider contract.
 *
 * @phpstan-type AiChatMessage array{role: string, content: string|list<array<string, mixed>>}
 * @phpstan-type AiChatMessages array<AiChatMessage>
 *
 * @package Etch\Services\Ai
 */
interface AiProviderInterface {

	/**
	 * Stream an AI response.
	 *
	 * @param array<int, array{role: string, content: string|list<array<string, mixed>>}>                                             $messages The messages to generate an AI response for.
	 * @param AiMode                                                                                                                  $mode The mode to use for the AI response.
	 * @param array{on_delta: callable, on_error?: callable|null, on_reasoning?: callable|null, on_client_tool_calls?: callable|null} $callbacks Callbacks keyed 'on_delta' (required); 'on_error', 'on_reasoning', and 'on_client_tool_calls' (optional).
	 * @param array<string, mixed>                                                                                                    $tools Optional tools for middleware to negotiate (initial requests).
	 *
	 * @phpstan-param AiChatMessages $messages
	 *
	 * @return ?WP_Error Returns WP_Error on failure, null on success.
	 */
	public function stream_ai_response( array $messages, AiMode $mode, array $callbacks, array $tools = array() ): ?WP_Error;

	/**
	 * Continue streaming after client-side tool execution.
	 *
	 * @param array<string, mixed>                                                                                                    $body      Continuation context (mode, previous_response_id, function_call_outputs).
	 * @param array{on_delta: callable, on_error?: callable|null, on_reasoning?: callable|null, on_client_tool_calls?: callable|null} $callbacks Callbacks keyed 'on_delta' (required); 'on_error', 'on_reasoning', and 'on_client_tool_calls' (optional).
	 *
	 * @return ?WP_Error Returns WP_Error on failure, null on success.
	 */
	public function stream_ai_continue( array $body, array $callbacks ): ?WP_Error;
}
