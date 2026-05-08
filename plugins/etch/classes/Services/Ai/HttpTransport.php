<?php
/**
 * HttpTransport.php
 *
 * Interface for HTTP transport used by AI providers.
 *
 * @package Etch\Services\Ai
 */

declare(strict_types=1);

namespace Etch\Services\Ai;

use WP_Error;

/**
 * HttpTransport interface.
 *
 * @package Etch\Services\Ai
 */
interface HttpTransport {

	/**
	 * Send a POST request.
	 *
	 * @param string                $url     The URL to send the request to.
	 * @param array<string, string> $headers The request headers.
	 * @param string                $body    The request body.
	 *
	 * @return array{status: int, body: string}|WP_Error
	 */
	public function post( string $url, array $headers, string $body ): array|WP_Error;

	/**
	 * Send a streaming POST request.
	 *
	 * @param string                $url      The URL to send the request to.
	 * @param array<string, string> $headers  The request headers.
	 * @param string                $body     The request body.
	 * @param callable              $on_chunk Callback invoked with each chunk of data.
	 *
	 * @return ?WP_Error Returns WP_Error on failure, null on success.
	 */
	public function stream( string $url, array $headers, string $body, callable $on_chunk ): ?WP_Error;
}
