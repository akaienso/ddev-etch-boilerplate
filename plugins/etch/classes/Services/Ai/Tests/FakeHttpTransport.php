<?php
/**
 * FakeHttpTransport.php
 *
 * Fake HTTP transport for testing AI providers without real HTTP calls.
 *
 * @package Etch\Services\Ai\Tests
 */

declare(strict_types=1);

namespace Etch\Services\Ai\Tests;

use Etch\Services\Ai\HttpTransport;
use WP_Error;

/**
 * FakeHttpTransport class.
 *
 * @package Etch\Services\Ai\Tests
 */
class FakeHttpTransport implements HttpTransport {

	/**
	 * The canned response for post().
	 *
	 * @var array{status: int, body: string}|WP_Error
	 */
	private array|WP_Error $post_response;

	/**
	 * The canned chunks for stream().
	 *
	 * @var array<string>
	 */
	private array $stream_chunks;

	/**
	 * The canned error for stream(), if any.
	 *
	 * @var WP_Error|null
	 */
	private ?WP_Error $stream_error;

	/**
	 * Queue of stream responses for multi-turn flows.
	 * Each entry is [chunks => string[], error => ?WP_Error].
	 *
	 * @var array<array{chunks: array<string>, error: ?WP_Error}>
	 */
	private array $stream_queue = array();

	/**
	 * Bodies received by stream() calls, for assertions.
	 *
	 * @var array<string>
	 */
	private array $stream_bodies = array();

	/**
	 * Constructor.
	 *
	 * @param array{status: int, body: string}|WP_Error $post_response The canned response for post().
	 * @param array<string>                             $stream_chunks The canned chunks for stream().
	 * @param WP_Error|null                             $stream_error  Optional error to return from stream().
	 */
	public function __construct(
		array|WP_Error $post_response = array(
			'status' => 200,
			'body'   => '{}',
		),
		array $stream_chunks = array(),
		?WP_Error $stream_error = null
	) {
		$this->post_response = $post_response;
		$this->stream_chunks = $stream_chunks;
		$this->stream_error  = $stream_error;
	}

	/**
	 * Return the canned response.
	 *
	 * @param string                $url     The URL (ignored).
	 * @param array<string, string> $headers The headers (ignored).
	 * @param string                $body    The body (ignored).
	 *
	 * @return array{status: int, body: string}|WP_Error
	 */
	public function post( string $url, array $headers, string $body ): array|WP_Error {
		return $this->post_response;
	}

	/**
	 * Feed canned chunks to the callback, then return the canned error.
	 *
	 * @param string                $url      The URL (ignored).
	 * @param array<string, string> $headers  The headers (ignored).
	 * @param string                $body     The body (ignored).
	 * @param callable              $on_chunk Callback invoked with each chunk.
	 *
	 * @return ?WP_Error
	 */
	public function stream( string $url, array $headers, string $body, callable $on_chunk ): ?WP_Error {
		$this->stream_bodies[] = $body;

		if ( ! empty( $this->stream_queue ) ) {
			$response = array_shift( $this->stream_queue );
			foreach ( $response['chunks'] as $chunk ) {
				$on_chunk( $chunk );
			}
			return $response['error'];
		}

		foreach ( $this->stream_chunks as $chunk ) {
			$on_chunk( $chunk );
		}

		return $this->stream_error;
	}

	/**
	 * Enqueue a stream response for multi-turn testing.
	 *
	 * @param array<string> $chunks The chunks to emit.
	 * @param WP_Error|null $error  Optional error to return.
	 *
	 * @return self
	 */
	public function enqueue_stream_response( array $chunks, ?WP_Error $error = null ): self {
		$this->stream_queue[] = array(
			'chunks' => $chunks,
			'error'  => $error,
		);
		return $this;
	}

	/**
	 * Get the bodies received by stream() calls.
	 *
	 * @return array<string>
	 */
	public function get_stream_bodies(): array {
		return $this->stream_bodies;
	}
}
