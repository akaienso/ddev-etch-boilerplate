<?php
/**
 * AiRoutes.php
 *
 * This file contains the AiRoutes class which defines REST API routes for handling AI-related functionality.
 *
 * PHP version 8.2+
 *
 * @category  Plugin
 * @package   Etch\RestApi\Routes
 */

declare(strict_types=1);
namespace Etch\RestApi\Routes;

use Etch\RestApi\Routes\BaseRoute;
use Etch\RestApi\ServerSideEventEmitter;
use Etch\Services\Ai\AgentProviders\AiProviderFactory;
use Etch\Services\AiService;
use Etch\Services\Ai\AiMode;
use Etch\Helpers\WideEventLogger;
use WP_REST_Request;
use WP_Error;

/**
 * AiRoutes
 *
 * This class defines REST API endpoints for AI-related functionality.
 *
 * @package Etch\RestApi\Routes
 */
class AiRoutes extends BaseRoute {

	/**
	 * The AI service instance.
	 *
	 * @var AiService
	 */
	private AiService $ai_service;

	/**
	 * Constructor.
	 *
	 * @param AiService|null $ai_service Optional service instance for dependency injection.
	 */
	public function __construct( ?AiService $ai_service = null ) {
		$this->ai_service = $ai_service ?? new AiService( AiProviderFactory::default()->create() );
	}


	/**
	 * Returns the route definitions for AI endpoints.
	 *
	 * @return array<array{route: string, methods: string|array<string>, callback: callable, permission_callback?: callable, args?: array<string, mixed>}>
	 */
	protected function get_routes(): array {
		return array(
			array(
				'route' => '/ai-chat/stream',
				'methods' => 'POST',
				'callback' => array( $this, 'stream_handler' ),
				'permission_callback' => fn() => $this->has_etch_read_api_access(),
			),
		);
	}

	/**
	 * Handle streaming an AI response.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 *
	 * @return void
	 */
	public function stream_handler( WP_REST_Request $request ): void {
		$this->handle_unified_stream( $request, new ServerSideEventEmitter() );
		exit;
	}

	/**
	 * Unified stream handler for both initial and continue legs.
	 *
	 * @param WP_REST_Request        $request The REST request.
	 * @param ServerSideEventEmitter $emitter The SSE emitter.
	 *
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @phpstan-param ServerSideEventEmitter $emitter
	 *
	 * @return void
	 */
	public function handle_unified_stream( WP_REST_Request $request, ServerSideEventEmitter $emitter ): void {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$emitter->send_error( 'Invalid request body.', 400 );
			return;
		}

		$kind = isset( $body['kind'] ) && is_string( $body['kind'] ) ? trim( $body['kind'] ) : '';
		if ( 'continue' === $kind ) {
			$this->handle_continue_stream_body( $body, $emitter );
			return;
		}

		if ( 'initial' === $kind ) {
			$this->handle_initial_stream_body( $body, $request, $emitter );
			return;
		}

		$emitter->send_error(
			'Invalid request: kind must be "initial" or "continue".',
			400
		);
	}

	/**
	 * Handle continue stream body.
	 *
	 * @param array<string, mixed>   $body The request body.
	 * @param ServerSideEventEmitter $emitter The SSE emitter.
	 * @return void
	 */
	private function handle_continue_stream_body( array $body, ServerSideEventEmitter $emitter ): void {
		$previous = isset( $body['previous_response_id'] ) && is_string( $body['previous_response_id'] ) ? trim( $body['previous_response_id'] ) : '';
		$outputs  = $body['function_call_outputs'] ?? null;

		if ( '' === $previous ) {
			$emitter->send_error( 'previous_response_id is required for kind=continue.', 400 );
			return;
		}

		if ( ! is_array( $outputs ) || array() === $outputs ) {
			$emitter->send_error( 'function_call_outputs is required for kind=continue.', 400 );
			return;
		}

		$this->log_undeclared_client_tool_outputs( $outputs, $previous );

		$emitter->start();
		$error = $this->ai_service->stream_ai_continue( $body, $this->build_sse_callbacks( $emitter ) );

		$this->finish_stream( $error, $emitter );
	}

	/**
	 * Record synthetic client tool errors in the activity log.
	 *
	 * @param array<int, mixed> $outputs Raw function_call_outputs from the continue body.
	 * @param string            $previous_response_id Correlation id for the upstream response.
	 * @return void
	 */
	private function log_undeclared_client_tool_outputs( array $outputs, string $previous_response_id ): void {
		foreach ( $outputs as $entry ) {
			$report = $this->parse_undeclared_tool_report_from_function_call_output_entry( $entry );
			if ( null === $report ) {
				continue;
			}

			WideEventLogger::failure(
				'ai.client_tools',
				'Client reported undeclared AI tool call (UNDECLARED_TOOL_CALLED).',
				array(
					'error_code'           => 'UNDECLARED_TOOL_CALLED',
					'call_id'              => $report['call_id'],
					'previous_response_id' => $previous_response_id,
				)
			);
		}
	}

	/**
	 * When a continue payload entry is a function_call_output whose output JSON
	 * has error.code UNDECLARED_TOOL_CALLED, return call_id for logging; else null.
	 *
	 * @param mixed $entry One element of function_call_outputs.
	 * @return array{call_id: string}|null
	 */
	private function parse_undeclared_tool_report_from_function_call_output_entry( mixed $entry ): ?array {
		if ( ! is_array( $entry ) ) {
			return null;
		}

		$output_string = $entry['output'] ?? null;
		if ( ! is_string( $output_string ) || '' === $output_string ) {
			return null;
		}

		$decoded = json_decode( $output_string, true );
		if ( ! is_array( $decoded ) ) {
			return null;
		}

		$error = $decoded['error'] ?? null;
		if ( ! is_array( $error ) ) {
			return null;
		}

		$code = $error['code'] ?? null;
		if ( 'UNDECLARED_TOOL_CALLED' !== $code ) {
			return null;
		}

		$call_id = isset( $entry['call_id'] ) && is_string( $entry['call_id'] ) ? $entry['call_id'] : '';

		return array( 'call_id' => $call_id );
	}

	/**
	 * Handle initial stream body.
	 *
	 * @param array<string, mixed>   $body The request body.
	 * @param WP_REST_Request        $request The REST request.
	 * @param ServerSideEventEmitter $emitter The SSE emitter.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return void
	 */
	private function handle_initial_stream_body( array $body, WP_REST_Request $request, ServerSideEventEmitter $emitter ): void {
		$messages = $this->get_messages_from_request( $request );
		$mode     = AiMode::from_string( $body['mode'] ?? AiMode::Ask );
		$supported = $body['supported_client_tools'] ?? null;

		$tools = array();
		if ( is_array( $supported ) ) {
			$tools['supported_client_tools'] = $supported;
		}

		if ( is_wp_error( $messages ) ) {
			$error_data  = $messages->get_error_data();
			$status_code = ( is_array( $error_data ) && isset( $error_data['status'] ) ) ? (int) $error_data['status'] : 400;
			$emitter->send_error( $messages->get_error_message(), $status_code );
			return;
		}

		$emitter->start();
		$error = $this->ai_service->stream_ai_response( $messages, $mode, $this->build_sse_callbacks( $emitter ), $tools );

		$this->finish_stream( $error, $emitter );
	}

	/**
	 * Build the standard SSE callback array for both stream handlers.
	 *
	 * @param ServerSideEventEmitter $emitter The SSE emitter.
	 * @phpstan-return array{on_delta: callable, on_error: callable, on_reasoning: callable, on_reasoning_done: callable, on_client_tool_calls: callable}
	 * @return array
	 */
	private function build_sse_callbacks( ServerSideEventEmitter $emitter ): array {
		return array(
			'on_delta'             => function ( string $delta ) use ( $emitter ) {
				$emitter->emit( 'delta', array( 'text' => $delta ) );
			},
			'on_error'             => function ( array $evt ) use ( $emitter ) {
				$emitter->emit( 'error', $this->normalize_provider_error_payload( $evt ) );
			},
			'on_reasoning'         => function ( string $reasoning ) use ( $emitter ) {
				$emitter->emit( 'reasoning', array( 'text' => $reasoning ) );
			},
			'on_reasoning_done'    => function () use ( $emitter ) {
				$emitter->emit( 'reasoning_done', array() );
			},
			'on_client_tool_calls' => function ( array $evt ) use ( $emitter ) {
				$emitter->emit( 'client_tool_calls', $this->sanitize_client_tool_calls_payload( $evt ) );
			},
		);
	}

	/**
	 * Emit a service-level error (if any) then the done event.
	 *
	 * @param WP_Error|null          $error   Error returned by the AI service, or null on success.
	 * @param ServerSideEventEmitter $emitter The SSE emitter.
	 * @return void
	 */
	private function finish_stream( ?WP_Error $error, ServerSideEventEmitter $emitter ): void {
		if ( is_wp_error( $error ) ) {
			$emitter->emit( 'error', array( 'message' => $error->get_error_message() ) );
		}

		$emitter->emit( 'done', array() );
	}

	/**
	 * Extract the messages array from the request body.
	 *
	 * Only checks request structure — domain validation is handled by AiService.
	 *
	 * @param WP_REST_Request $request The request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return array<int, mixed>|WP_Error The messages array or an error if the request structure is invalid.
	 */
	private function get_messages_from_request( WP_REST_Request $request ): array|WP_Error {
		$body = $request->get_json_params();

		if ( ! is_array( $body ) ) {
			return new WP_Error( 'etch_ai_invalid_body', 'Invalid request body.', array( 'status' => 400 ) );
		}

		$messages = $body['messages'] ?? null;
		if ( ! is_array( $messages ) ) {
			return new WP_Error( 'etch_ai_invalid_messages', 'Messages are required.', array( 'status' => 400 ) );
		}

		return $messages;
	}

	/**
	 * Normalize client tool event payload for the browser schema.
	 *
	 * @param array<string, mixed> $evt Raw decoded SSE data object.
	 * @return array{previous_response_id: string, tool_call_ids: array<int, string>, server_outputs: array<int, array<string, mixed>>, client_calls: array<int, array<string, mixed>>}
	 */
	private function sanitize_client_tool_calls_payload( array $evt ): array {
		$previous = $evt['previous_response_id'] ?? null;
		$tool_call_ids = $evt['tool_call_ids'] ?? null;
		$sanitized_tool_call_ids = array();

		if ( is_array( $tool_call_ids ) ) {
			foreach ( $tool_call_ids as $tool_call_id ) {
				if ( is_string( $tool_call_id ) ) {
					$sanitized_tool_call_ids[] = $tool_call_id;
				}
			}
		}

		return array(
			'previous_response_id' => is_string( $previous ) ? $previous : '',
			'tool_call_ids'        => $sanitized_tool_call_ids,
			'server_outputs'       => isset( $evt['server_outputs'] ) && is_array( $evt['server_outputs'] ) ? $evt['server_outputs'] : array(),
			'client_calls'         => isset( $evt['client_calls'] ) && is_array( $evt['client_calls'] ) ? $evt['client_calls'] : array(),
		);
	}

	/**
	 * Normalize the provider error payload.
	 *
	 * @param array<string, mixed> $evt The provider error event.
	 * @return array<string, mixed> The normalized payload.
	 */
	private function normalize_provider_error_payload( array $evt ): array {
		$message = '';

		if ( isset( $evt['error'] ) && is_array( $evt['error'] ) ) {
			$error_message = $evt['error']['message'] ?? null;
			if ( is_string( $error_message ) && '' !== $error_message ) {
				$message = $error_message;
			}
		}

		$payload = array( 'provider' => $evt );
		if ( '' !== $message ) {
			$payload['message'] = $message;
		}

		return $payload;
	}
}
