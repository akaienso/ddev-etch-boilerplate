<?php
/**
 * AiRagRoutes.php
 *
 * REST API routes for AI RAG middleware integration.
 *
 * @package Etch\RestApi\Routes
 */

declare(strict_types=1);
namespace Etch\RestApi\Routes;

use WP_REST_Response;

/**
 * AiRagRoutes
 *
 * Proxies requests to the RAG middleware.
 *
 * @package Etch\RestApi\Routes
 */
class AiRagRoutes extends BaseRoute {

	/**
	 * The middleware endpoint URL.
	 *
	 * @var string|null
	 */
	private const DEFAULT_ENDPOINT = 'https://api.etchwp.com';

	/**
	 * The middleware endpoint URL.
	 *
	 * @var string
	 */
	private string $endpoint;

	/**
	 * HTTP GET callable — injectable for testing.
	 *
	 * @var callable
	 */
	private $http_get;

	/**
	 * Constructor.
	 *
	 * @param string|null   $endpoint Optional middleware endpoint URL.
	 * @param callable|null $http_get Optional HTTP GET callable (defaults to wp_remote_get).
	 */
	public function __construct( ?string $endpoint = null, ?callable $http_get = null ) {
		$this->endpoint = $endpoint ?? ( defined( 'ETCH_RAG_ENDPOINT' ) ? constant( 'ETCH_RAG_ENDPOINT' ) : self::DEFAULT_ENDPOINT );
		$this->http_get = $http_get ?? 'wp_remote_get';
	}

	/**
	 * Returns the route definitions for AI RAG endpoints.
	 *
	 * @return array<array{route: string, methods: string|array<string>, callback: callable, permission_callback?: callable}>
	 */
	protected function get_routes(): array {
		return array(
			array(
				'route'   => '/ai-rag/health',
				'methods' => 'GET',
				'callback' => array( $this, 'health' ),
				'permission_callback' => fn() => $this->has_etch_read_api_access(),
			),
		);
	}

	/**
	 * Proxy health check to the middleware.
	 *
	 * @return WP_REST_Response
	 */
	public function health(): WP_REST_Response {
		$url      = rtrim( $this->endpoint, '/' ) . '/health';
		$response = ( $this->http_get )( $url );

		if ( is_wp_error( $response ) ) {
			return new WP_REST_Response(
				array( 'error' => $response->get_error_message() ),
				502
			);
		}

		$status_code = $response['response']['code'] ?? 500;
		$body        = json_decode( $response['body'] ?? '{}', true );

		if ( $status_code >= 400 ) {
			return new WP_REST_Response(
				is_array( $body ) ? $body : array( 'error' => 'Middleware returned an error.' ),
				502
			);
		}

		return new WP_REST_Response( $body, 200 );
	}
}
