<?php
/**
 * OpenAiPayloadBuilder.php
 *
 * Builds payloads for the OpenAI Responses API.
 *
 * @package Etch\Services\Ai\AgentProviders
 */

declare(strict_types=1);

namespace Etch\Services\Ai\AgentProviders;

use Etch\Helpers\Flag;

/**
 * OpenAiPayloadBuilder
 *
 * @phpstan-import-type AiChatMessages from \Etch\Services\Ai\AiProviderInterface
 *
 * @package Etch\Services\Ai\AgentProviders
 */
class OpenAiPayloadBuilder {

	/**
	 * Build the payload for the OpenAI API.
	 *
	 * @param array<int, array{role: string, content: string|list<array<string, mixed>>}> $messages The messages to build the payload from.
	 * @param string                                                                      $model         The model to use.
	 * @param string                                                                      $system_prompt The system prompt.
	 * @param array                                                                       $options       Optional flags: 'stream' (bool), 'reasoning_enabled' (bool).
	 *
	 * @phpstan-param AiChatMessages $messages
	 * @phpstan-param array{stream?: bool, reasoning_enabled?: bool, rag_enabled?: bool, web_search_enabled?: bool} $options
	 *
	 * @return array<string, mixed> The payload.
	 */
	public function build( array $messages, string $model, string $system_prompt, array $options = array() ): array {
		$stream             = ! empty( $options['stream'] );
		$reasoning_enabled  = ! empty( $options['reasoning_enabled'] );
		$rag_enabled        = ! empty( $options['rag_enabled'] );
		$web_search_enabled = ! empty( $options['web_search_enabled'] );

		$tools = array();

		if ( $rag_enabled ) {
			$tools[] = RagToolHandler::get_tool_definition();
		}

		if ( $web_search_enabled ) {
			$tools[] = self::get_web_search_tool_definition();
		}

		$payload = array(
			'model'        => $model,
			'instructions' => $system_prompt,
			'stream'       => $stream,
			'tools'        => $tools,
		);

		$payload = array_merge( $payload, $this->get_model_params( $reasoning_enabled ) );
		$payload['input'] = $this->build_input_from_messages( $messages );

		return $payload;
	}

	/**
	 * Get the web search tool definition.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_web_search_tool_definition(): array {
		return array(
			'type'    => 'web_search',
			'filters' => array(
				'allowed_domains' => array( 'docs.etchwp.com' ),
			),
		);
	}

	/**
	 * Build a follow-up payload for continuing a conversation after function calls.
	 *
	 * @param string               $previous_response_id The response ID from the initial request.
	 * @param array<int, mixed>    $input                The function call outputs.
	 * @param array<string, mixed> $options           Optional flags: 'model' (string), 'instructions' (string), 'web_search_fallback' (bool), 'reasoning_enabled' (bool).
	 *
	 * @phpstan-param array{model?: string, instructions?: string, web_search_fallback?: bool, reasoning_enabled?: bool} $options
	 *
	 * @return array<string, mixed> The follow-up payload.
	 */
	public function build_follow_up( string $previous_response_id, array $input, array $options = array() ): array {
		$reasoning_enabled = ! empty( $options['reasoning_enabled'] );

		$payload = array(
			'model'                => $options['model'] ?? '',
			'instructions'         => $options['instructions'] ?? '',
			'stream'               => true,
			'previous_response_id' => $previous_response_id,
			'input'                => $input,
		);

		if ( Flag::is_on( 'ENABLE_AI_KEEP_REASONING_EFFORT' ) && $reasoning_enabled ) {
			$payload = array_merge( $payload, $this->get_model_params( $reasoning_enabled ) );
		}

		if ( ! empty( $options['web_search_fallback'] ) ) {
			$payload['tools'] = array( self::get_web_search_tool_definition() );
		}

		return $payload;
	}

	/**
	 * Build the input array from messages.
	 *
	 * @param array<int, array{role: string, content: string|list<array<string, mixed>>}> $messages The messages to build input from.
	 *
	 * @phpstan-param AiChatMessages $messages
	 * @phpstan-return list<array<string, mixed>>
	 * @return array The input.
	 */
	public function build_input_from_messages( array $messages ): array {
		$out = array();

		foreach ( $messages as $m ) {
			$item = $this->create_message_input_item( $m );
			if ( null !== $item ) {
				$out[] = $item;
			}
		}

		return $out;
	}

	/**
	 * Build the input for the ask mode.
	 *
	 * @param array<int, array{role: string, content: string|list<array<string, mixed>>}> $messages The messages to build input from.
	 *
	 * @phpstan-param AiChatMessages $messages
	 * @phpstan-return list<array<string, mixed>>
	 * @return array The input.
	 */
	public function build_input_for_ask_mode( array $messages ): array {
		$out = array();

		foreach ( $messages as $m ) {
			$role = $this->extract_role( $m );
			if ( null === $role ) {
				continue;
			}

			$content = $m['content'];
			if ( ! is_string( $content ) ) {
				continue;
			}

			$item = $this->message_item_from_text( $role, $content );
			if ( null === $item ) {
				continue;
			}

			$out[] = $item;
		}

		return $out;
	}

	/**
	 * Create a message input item for the build mode.
	 *
	 * @param mixed $message The message to convert.
	 *
	 * @return ?array<string, mixed> The message item.
	 */
	public function create_message_input_item( mixed $message ): ?array {
		$role = $this->extract_role( $message );
		if ( null === $role ) {
			return null;
		}

		$content = is_array( $message ) ? ( $message['content'] ?? null ) : null;

		if ( is_string( $content ) ) {
			return $this->message_item_from_text( $role, $content );
		}

		if ( is_array( $content ) && ! empty( $content ) ) {
			return $this->message_item_from_parts( $role, $content );
		}

		return null;
	}



	/**
	 * Extract the role from a message.
	 *
	 * @param mixed $message The message to extract the role from.
	 *
	 * @phpstan-return 'user'|'assistant'|null
	 * @return ?string The role.
	 */
	public function extract_role( mixed $message ): ?string {
		if ( ! is_array( $message ) ) {
			return null;
		}

		$role = $message['role'] ?? null;
		if ( ! is_string( $role ) ) {
			return null;
		}

		$role = trim( $role );

		if ( ! in_array( $role, array( 'user', 'assistant' ), true ) ) {
			return null;
		}

		return $role;
	}

	/**
	 * Convert a plain text message into a Responses API message item.
	 *
	 * - user => input_text
	 * - assistant => output_text
	 *
	 * @param string $role The role of the message.
	 * @param string $text The text of the message.
	 *
	 * @phpstan-param 'user' | 'assistant' $role
	 *
	 * @return array<string, mixed>|null
	 */
	public function message_item_from_text( string $role, string $text ): ?array {
		$text = trim( $text );
		if ( '' === $text ) {
			return null;
		}
		$part_type = ( 'assistant' === $role ) ? 'output_text' : 'input_text';
		return array(
			'type'    => 'message',
			'role'    => $role,
			'content' => array(
				array(
					'type' => $part_type,
					'text' => $text,
				),
			),
		);
	}

	/**
	 * Convert a list of content parts into a Responses API message item.
	 *
	 * @param string $role The role of the message.
	 * @param array  $parts The parts of the message.
	 *
	 * @phpstan-param 'user' | 'assistant' $role
	 * @phpstan-param list<array{type: string, text: string}> $parts
	 *
	 * @return array<string, mixed>
	 */
	public function message_item_from_parts( string $role, array $parts ): array {
		return array(
			'type'    => 'message',
			'role'    => $role,
			'content' => $parts,
		);
	}

	/**
	 * Get model-specific parameters.
	 *
	 * @param bool $reasoning_enabled Whether reasoning summary is enabled.
	 *
	 * @return array<string, mixed> The model parameters.
	 */
	private function get_model_params( bool $reasoning_enabled ): array {
			return array(
				'text'      => array(
					'verbosity' => 'low',
				),
				'reasoning' => $reasoning_enabled
					? array(
						'effort'  => 'low',
						'summary' => 'detailed',
					)
					: array(
						'effort' => 'low',
					),
			);
	}
}
