<?php
/**
 * ServerSideEventEmitter.php
 *
 * Handles SSE output: headers, event framing, and flushing.
 *
 * @package Etch\RestApi
 */

declare(strict_types=1);

namespace Etch\RestApi;

use Etch\Helpers\Flag;

/**
 * ServerSideEventEmitter
 *
 * Encapsulates SSE I/O so that route handlers can be tested
 * with a fake emitter that captures events instead of writing to output.
 *
 * @package Etch\RestApi
 */
class ServerSideEventEmitter {

	/**
	 * The allowed SSE event names.
	 *
	 * @var array<string>
	 */
	private const ALLOWED_EVENTS = array( 'delta', 'error', 'reasoning_done', 'reasoning', 'done', 'client_tool_calls' );

	/**
	 * Send SSE-specific response headers and flush output buffers.
	 *
	 * @return void
	 */
	public function start(): void {
		if ( Flag::is_on( 'ENABLE_CLEAR_BUFFER_IN_STREAM_REQUEST' ) ) {
			if ( function_exists( 'apache_setenv' ) ) {
				@apache_setenv( 'no-gzip', '1' );
			}

			@ini_set( 'zlib.output_compression', '0' );
			@ini_set( 'output_buffering', '0' );
			@ini_set( 'implicit_flush', '1' );

			while ( ob_get_level() > 0 ) {
				ob_end_clean();
			}

			ob_implicit_flush( true );

			header( 'Content-Type: text/event-stream; charset=utf-8' );
			header( 'Cache-Control: no-cache, no-transform' );
			header( 'X-Accel-Buffering: no' );

			flush();

			return;
		}

		header( 'Content-Type: text/event-stream; charset=utf-8' );
		header( 'Cache-Control: no-cache, no-transform' );
		header( 'X-Accel-Buffering: no' );

		while ( ob_get_level() > 0 ) {
			ob_end_flush();
		}
		flush();
	}

	/**
	 * Emit an SSE event.
	 *
	 * @param string               $event The event name.
	 * @param array<string, mixed> $data  The event data.
	 *
	 * @return void
	 */
	public function emit( string $event, array $data = array() ): void {
		if ( ! in_array( $event, self::ALLOWED_EVENTS, true ) ) {
			$event = 'error';
			$data  = array( 'message' => 'Invalid event type' );
		}

		echo 'event: ' . esc_html( $event ) . "\n";
		echo 'data: ' . wp_json_encode( $data ) . "\n\n";
		flush();
	}

	/**
	 * Send a JSON error response (for pre-SSE validation failures).
	 *
	 * @param string $message The error message.
	 * @param int    $status  The HTTP status code.
	 *
	 * @return void
	 */
	public function send_error( string $message, int $status ): void {
		status_header( $status );
		header( 'Content-Type: application/json; charset=utf-8' );
		echo wp_json_encode(
			array(
				'success' => false,
				'data'    => $message,
			)
		);
	}
}
