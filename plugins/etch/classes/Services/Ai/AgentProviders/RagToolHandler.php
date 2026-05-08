<?php
/**
 * RagToolHandler.php
 *
 * Provides the RAG retrieval tool definition and handles tool call execution.
 *
 * @package Etch\Services\Ai\AgentProviders
 */

declare(strict_types=1);

namespace Etch\Services\Ai\AgentProviders;

use Etch\Helpers\WideEventLogger;
use Etch\Services\Ai\HttpTransport;
use WP_Error;

/**
 * RagToolHandler
 *
 * Defines the RAG retrieval function tool for OpenAI and executes tool calls
 * by contacting the RAG middleware's /retrieve endpoint.
 *
 * @package Etch\Services\Ai\AgentProviders
 */
class RagToolHandler {

	/**
	 * The HTTP transport for middleware requests.
	 *
	 * @var HttpTransport
	 */
	private HttpTransport $transport;

	/**
	 * The middleware base URL.
	 *
	 * @var string
	 */
	private string $middleware_url;

	/**
	 * Constructor.
	 *
	 * @param HttpTransport $transport      The HTTP transport.
	 * @param string        $middleware_url The middleware base URL.
	 */
	public function __construct( HttpTransport $transport, string $middleware_url ) {
		$this->transport      = $transport;
		$this->middleware_url  = rtrim( $middleware_url, '/' );
	}

	/**
	 * Get the OpenAI function tool definition for RAG retrieval.
	 *
	 * @return array<string, mixed> The tool definition.
	 */
	public static function get_tool_definition(): array {
		return array(
			'type'        => 'function',
			'name'        => 'rag_retrieve',
			'description' => 'Search the Etch documentation for relevant information to answer the user\'s question about the Etch WordPress plugin.',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'query' => array(
						'type'        => 'string',
						'description' => 'The search query to find relevant documentation.',
					),
				),
				'required'   => array( 'query' ),
			),
		);
	}

	/**
	 * Handle a RAG retrieval tool call by contacting the middleware.
	 *
	 * @param string $query The search query from the AI model.
	 *
	 * @return array{results: array<int, array{title: string, content: string, source_url: string}>}|WP_Error
	 */
	public function handle_call( string $query ): array|WP_Error {
		$body = wp_json_encode(
			array(
				'query'       => $query,
				'license_key' => '',
			)
		);

		if ( false === $body ) {
			return new WP_Error( 'etch_rag_encode_error', 'Failed to encode RAG request.' );
		}

		$url = $this->middleware_url . '/retrieve';
		WideEventLogger::set( 'ai.rag.middleware_url', $url );

		$response = $this->transport->post(
			$url,
			array( 'Content-Type' => 'application/json' ),
			$body
		);

		if ( is_wp_error( $response ) ) {
			WideEventLogger::failure( 'ai.rag.middleware', $response->get_error_message() );
			return new WP_Error( 'etch_rag_network_error', $response->get_error_message() );
		}

		WideEventLogger::set( 'ai.rag.middleware_status', $response['status'] );
		WideEventLogger::set( 'ai.rag.middleware_body', substr( $response['body'], 0, 200 ) );

		if ( $response['status'] >= 400 ) {
			WideEventLogger::failure( 'ai.rag.middleware', sprintf( 'HTTP %d', $response['status'] ) );
			return new WP_Error(
				'etch_rag_http_error',
				sprintf( 'Middleware returned HTTP %d', $response['status'] ),
				array( 'status' => $response['status'] )
			);
		}

		$decoded = json_decode( $response['body'], true );
		$results = is_array( $decoded ) ? ( $decoded['results'] ?? null ) : null;

		if ( ! is_array( $results ) ) {
			WideEventLogger::failure( 'ai.rag.middleware', 'Malformed response' );
			return new WP_Error( 'etch_rag_malformed_response', 'Middleware returned an unexpected response format.' );
		}

		return array( 'results' => $results );
	}
}
