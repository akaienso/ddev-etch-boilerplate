<?php
/**
 * MiddlewareProvider.php
 *
 * AI provider that delegates to the Etch middleware endpoint instead of calling
 * OpenAI directly.
 *
 * PHP version 8.2+
 *
 * @category  Plugin
 * @package   Etch\Services\Ai\AgentProviders
 */

declare(strict_types=1);

namespace Etch\Services\Ai\AgentProviders;

use Etch\Helpers\WideEventLogger;
use Etch\Services\Ai\AiProviderInterface;
use Etch\Services\Ai\AiMode;
use Etch\Services\Ai\HttpTransport;
use Etch\Services\Ai\ServerSideEventsFramesParser;
use Etch\Services\Ai\WpCurlHttpTransport;
use Etch\Services\SettingsService;
use Etch\Helpers\Flag;
use WP_Error;

/**
 * MiddlewareProvider
 *
 * Streams AI responses through the Etch middleware endpoint.
 *
 * @phpstan-import-type AiChatMessages from \Etch\Services\Ai\AiProviderInterface
 *
 * @package Etch\Services\Ai\AgentProviders
 */
class MiddlewareProvider implements AiProviderInterface {

	/**
	 * The middleware API URL.
	 *
	 * @var string
	 */
	private const API_URL = 'https://api.etchwp.com/assistant';

	/**
	 * The wp-config constant name for the API key.
	 *
	 * @var string
	 */
	private const WP_CONFIG_KEY_CONSTANT = 'ETCH_OPENAI_API_KEY';

	/**
	 * The HTTP transport.
	 *
	 * @var HttpTransport
	 */
	private HttpTransport $transport;

	/**
	 * The API key, or null to resolve at call time.
	 *
	 * @var string|null
	 */
	private ?string $api_key;

	/**
	 * Per-stream SSE buffer. Reset at the start of each do_stream call.
	 *
	 * @var string
	 */
	private string $sse_buffer = '';

	/**
	 * Constructor.
	 *
	 * @param HttpTransport|null $transport Optional HTTP transport (defaults to WpCurlHttpTransport).
	 * @param string|null        $api_key   Optional API key (defaults to settings/wp-config lookup).
	 */
	public function __construct( ?HttpTransport $transport = null, ?string $api_key = null ) {
		$this->transport = $transport ?? new WpCurlHttpTransport();
		$this->api_key   = $api_key;
	}

	/**
	 * Stream an AI response through the middleware endpoint.
	 *
	 * @param array                $messages  The messages to generate an AI response for.
	 * @param AiMode               $mode      The mode to use for the AI response.
	 * @param array                $callbacks Callbacks keyed on_delta (required), on_error, on_reasoning, on_client_tool_calls.
	 * @param array<string, mixed> $tools Optional tools for middleware to negotiate (initial requests).
	 *
	 * @phpstan-param AiChatMessages $messages
	 * @phpstan-param array{on_delta: callable, on_error?: callable|null, on_reasoning?: callable|null, on_client_tool_calls?: callable|null} $callbacks
	 * @phpstan-param array<string, mixed> $tools
	 *
	 * @return ?WP_Error Returns WP_Error on failure, null on success.
	 */
	public function stream_ai_response( array $messages, AiMode $mode, array $callbacks, array $tools = array() ): ?WP_Error {
		WideEventLogger::set( 'ai.provider', 'middleware' );
		return $this->execute_stream( self::API_URL, $this->get_encoded_payload( $messages, $mode, $tools ), $callbacks );
	}

	/**
	 * Stream the second OpenAI leg after browser-executed tool outputs (middleware).
	 *
	 * @param array $body      Request body; apiKey is injected here.
	 * @param array $callbacks SSE callbacks.
	 *
	 * @phpstan-param array<string, mixed> $body
	 * @phpstan-param array{on_delta: callable, on_error?: callable|null, on_reasoning?: callable|null, on_client_tool_calls?: callable|null} $callbacks
	 *
	 * @return ?WP_Error
	 */
	public function stream_ai_continue( array $body, array $callbacks ): ?WP_Error {
		WideEventLogger::set( 'ai.provider', 'middleware' );
		return $this->execute_stream( self::API_URL, $this->get_encoded_continue_payload( $body ), $callbacks );
	}

	/**
	 * Log the URL, validate the payload, normalize callbacks, run the stream, and log the outcome.
	 *
	 * @param string          $url             Middleware URL.
	 * @param string|WP_Error $encoded_payload Pre-encoded JSON payload or a WP_Error from the builder.
	 * @param array           $callbacks       Public-facing callbacks array.
	 *
	 * @phpstan-param array{on_delta: callable, on_error?: callable|null, on_reasoning?: callable|null, on_client_tool_calls?: callable|null} $callbacks
	 *
	 * @return ?WP_Error
	 */
	private function execute_stream( string $url, string|WP_Error $encoded_payload, array $callbacks ): ?WP_Error {
		WideEventLogger::set( 'ai.middleware.url', $url );

		if ( is_wp_error( $encoded_payload ) ) {
			WideEventLogger::failure( 'ai', $encoded_payload->get_error_message() );
			return $encoded_payload;
		}

		$result = $this->do_stream( $url, $encoded_payload, $this->normalize_callbacks( $callbacks ) );

		if ( is_wp_error( $result ) ) {
			WideEventLogger::failure( 'ai', $result->get_error_message() );
			return $result;
		}

		WideEventLogger::set( 'ai.outcome', 'success' );

		return null;
	}

	/**
	 * Build JSON body for POST /assistant (apiKey + reasoning from server).
	 *
	 * @param array<string, mixed> $body Partial body from the REST client.
	 * @return string|WP_Error
	 */
	private function get_encoded_continue_payload( array $body ): string|WP_Error {
		$api_key = $this->resolve_api_key();
		if ( '' === $api_key ) {
			return new WP_Error( 'etch_ai_missing_api_key', 'API key is missing.', array( 'status' => 500 ) );
		}

		$previous = isset( $body['previous_response_id'] ) && is_string( $body['previous_response_id'] ) ? trim( $body['previous_response_id'] ) : '';
		if ( '' === $previous ) {
			return new WP_Error( 'etch_ai_invalid_continue', 'previous_response_id is required.', array( 'status' => 400 ) );
		}

		$outputs = $body['function_call_outputs'] ?? null;
		if ( ! is_array( $outputs ) || array() === $outputs ) {
			return new WP_Error( 'etch_ai_invalid_continue', 'function_call_outputs is required.', array( 'status' => 400 ) );
		}

		$mode = AiMode::from_string( $body['mode'] ?? null );

		$settings            = SettingsService::get_instance();
		$reasoning_setting   = $settings->get_setting( 'ai_show_reasoning' );
		$reasoning_enabled   = true === $reasoning_setting;

		$payload = array(
			'kind'                   => 'continue', // TODO: should we create a constant?.
			'mode'                   => $mode->value,
			'apiKey'                 => $api_key,
			'reasoning'              => $reasoning_enabled,
			'previous_response_id'   => $previous,
			'function_call_outputs'  => $outputs,
		);

		$payload = $this->supported_tools_payload( $payload, $body );

		$encoded_payload = wp_json_encode( $payload );

		if ( false === $encoded_payload ) {
			return new WP_Error( 'json_encode_failed', 'Failed to encode continue payload', array( 'status' => 500 ) );
		}

		return $encoded_payload;
	}

	/**
	 * Validate inputs and prepare the encoded payload for streaming.
	 *
	 * @param array<int, array{role: string, content: string|list<array<string, mixed>>}> $messages The messages to send.
	 * @param AiMode                                                                      $mode The mode to use for the AI response.
	 * @param array<string, mixed>                                                        $tools The tools for middleware to negotiate.
	 *
	 * @phpstan-param AiChatMessages $messages
	 *
	 * @return string|WP_Error The JSON-encoded payload, or WP_Error on failure.
	 */
	private function get_encoded_payload( array $messages, AiMode $mode, array $tools ): string|WP_Error {
		if ( count( $messages ) === 0 ) {
			return new WP_Error( 'etch_ai_invalid_messages', 'At least one message is required', array( 'status' => 400 ) );
		}

		$api_key = $this->resolve_api_key();
		if ( '' === $api_key ) {
			return new WP_Error( 'etch_ai_missing_api_key', 'API key is missing.', array( 'status' => 500 ) );
		}

		$settings = SettingsService::get_instance();
		$reasoning_setting = $settings->get_setting( 'ai_show_reasoning' );
		$reasoning_enabled = true === $reasoning_setting;

		$payload = array(
			'kind'     => 'initial', // TODO: should we create a constant?.
			'mode'     => $mode->value,
			'apiKey'   => $api_key,
			'messages' => $messages,
			'reasoning' => $reasoning_enabled,
		);

		$payload = $this->supported_tools_payload( $payload, $tools );

		$encoded_payload = wp_json_encode( $payload );

		if ( false === $encoded_payload ) {
			return new WP_Error( 'json_encode_failed', 'Failed to encode payload', array( 'status' => 500 ) );
		}

		return $encoded_payload;
	}

	/**
	 * Resolve and sanitize supported tools for the middleware payload when negotiation is enabled.
	 *
	 * @param array<string, mixed> $tools Request tools from the REST layer.
	 * @return array<int, string>|null Non-null when the key must be sent (may be empty); null when it must be omitted.
	 */
	private function resolve_supported_tools( array $tools ): ?array {

		if ( ! array_key_exists( 'supported_client_tools', $tools ) ) {
			return null;
		}

		$raw = $tools['supported_client_tools'] ?? null;
		if ( ! is_array( $raw ) ) {
			return null;
		}

		$sanitized = array();
		foreach ( $raw as $tool ) {
			if ( is_string( $tool ) ) {
				$sanitized[] = $tool;
			}
		}

		return $sanitized;
	}

	/**
	 * Resolve and sanitize supported tools for the middleware payload when negotiation is enabled.
	 *
	 * @param array<string, mixed> $payload The payload to add the supported tools to.
	 * @param array<string, mixed> $tools The tools to resolve.
	 * @return array<string, mixed> The payload with the supported tools added.
	 */
	private function supported_tools_payload( array $payload, array $tools ): array {
		if ( ! Flag::is_on( 'ENABLE_AI_TOOLS_NEGOTIATION' ) ) {
			return $payload;
		}

		$supported_tools = $this->resolve_supported_tools( $tools );
		if ( null !== $supported_tools ) {
			$payload['supported_client_tools'] = $supported_tools;
		}

		return $payload;
	}

	/**
	 * Execute a streaming request and process SSE events.
	 *
	 * @param string $url             Middleware URL (/assistant).
	 * @param string $encoded_payload The JSON-encoded payload.
	 * @param array  $callbacks       Normalized callbacks array.
	 *
	 * @phpstan-param array{on_delta: callable, on_error: callable|null, on_reasoning: callable|null, on_client_tool_calls: callable|null} $callbacks
	 *
	 * @return ?WP_Error
	 */
	private function do_stream( string $url, string $encoded_payload, array $callbacks ): ?WP_Error {
		$this->sse_buffer = '';
		$parser           = new ServerSideEventsFramesParser();
		$event_processor  = new OpenAiStreamEventProcessor();

		return $this->transport->stream(
			$url,
			array(
				'Content-Type' => 'application/json',
				'Accept'       => 'text/event-stream',
			),
			$encoded_payload,
			function ( string $chunk ) use ( $parser, $event_processor, $callbacks ) {
				$this->handle_sse_chunk( $parser, $event_processor, $chunk, $callbacks );
			}
		);
	}

	/**
	 * Handle an SSE chunk and process the streaming events.
	 *
	 * @param ServerSideEventsFramesParser $parser          The SSE frames parser.
	 * @param OpenAiStreamEventProcessor   $event_processor The event processor.
	 * @param string                       $chunk           The chunk to process.
	 * @param array                        $callbacks       Callbacks for SSE events.
	 *
	 * @phpstan-param array{on_delta: callable, on_error?: callable|null, on_reasoning?: callable|null, on_client_tool_calls?: callable|null} $callbacks
	 *
	 * @return void
	 */
	private function handle_sse_chunk( ServerSideEventsFramesParser $parser, OpenAiStreamEventProcessor $event_processor, string $chunk, array $callbacks ): void {
		$this->sse_buffer .= $chunk;
		$frames = $parser->extract_frames( $this->sse_buffer );
		foreach ( $frames as $frame ) {
			if ( '[DONE]' === $frame ) {
				continue;
			}

			$event = json_decode( $frame, true );
			if ( ! is_array( $event ) ) {
				continue;
			}

			$event_processor->process( $event, $callbacks );
		}
	}

	/**
	 * Normalize a public-facing callbacks array into the canonical internal shape.
	 *
	 * @param array $callbacks Incoming callbacks.
	 *
	 * @phpstan-param array{on_delta: callable, on_error?: callable|null, on_reasoning?: callable|null, on_client_tool_calls?: callable|null} $callbacks
	 * @phpstan-return array{on_delta: callable, on_error: callable|null, on_reasoning: callable|null, on_client_tool_calls: callable|null}
	 *
	 * @return array
	 */
	private function normalize_callbacks( array $callbacks ): array {
		return array(
			'on_delta'             => $callbacks['on_delta'],
			'on_error'             => $callbacks['on_error'] ?? null,
			'on_reasoning'         => $callbacks['on_reasoning'] ?? null,
			'on_client_tool_calls' => $callbacks['on_client_tool_calls'] ?? null,
		);
	}

	/**
	 * Resolve the API key from the injected value, settings, or wp-config.
	 *
	 * @return string
	 */
	private function resolve_api_key(): string {
		if ( null !== $this->api_key ) {
			return $this->api_key;
		}

		$settings = SettingsService::get_instance();
		$api_key  = $settings->get_decrypted_setting( 'ai_api_key' );

		if ( ! empty( $api_key ) && is_string( $api_key ) ) {
			return $api_key;
		}

		$config_key = defined( self::WP_CONFIG_KEY_CONSTANT ) ? constant( self::WP_CONFIG_KEY_CONSTANT ) : null;
		if ( is_string( $config_key ) && '' !== trim( $config_key ) ) {
			return trim( $config_key );
		}

		return '';
	}
}
