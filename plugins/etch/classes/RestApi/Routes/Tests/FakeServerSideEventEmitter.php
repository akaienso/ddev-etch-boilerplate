<?php
/**
 * FakeServerSideEventEmitter.php
 *
 * A fake SSE emitter that captures events for testing.
 *
 * @package Etch\RestApi\Routes\Tests
 */

declare(strict_types=1);

namespace Etch\RestApi\Routes\Tests;

use Etch\RestApi\ServerSideEventEmitter;

/**
 * FakeServerSideEventEmitter
 *
 * Captures emitted events instead of writing to output.
 *
 * @package Etch\RestApi\Routes\Tests
 */
class FakeServerSideEventEmitter extends ServerSideEventEmitter {

	/**
	 * Whether start() was called.
	 *
	 * @var bool
	 */
	public bool $started = false;

	/**
	 * Captured events from emit() calls.
	 *
	 * @var array<int, array{event: string, data: array<string, mixed>}>
	 */
	public array $events = array();

	/**
	 * Captured error from send_error(), if any.
	 *
	 * @var array{message: string, status: int}|null
	 */
	public ?array $error_response = null;

	/**
	 * Record that SSE streaming was started.
	 *
	 * @return void
	 */
	public function start(): void {
		$this->started = true;
	}

	/**
	 * Capture the event instead of writing to output.
	 *
	 * @param string               $event The event name.
	 * @param array<string, mixed> $data  The event data.
	 *
	 * @return void
	 */
	public function emit( string $event, array $data = array() ): void {
		$this->events[] = array(
			'event' => $event,
			'data'  => $data,
		);
	}

	/**
	 * Capture the error instead of writing to output.
	 *
	 * @param string $message The error message.
	 * @param int    $status  The HTTP status code.
	 *
	 * @return void
	 */
	public function send_error( string $message, int $status ): void {
		$this->error_response = array(
			'message' => $message,
			'status'  => $status,
		);
	}
}
