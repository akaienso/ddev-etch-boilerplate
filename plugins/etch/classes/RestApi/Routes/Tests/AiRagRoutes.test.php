<?php
/**
 * AiRagRoutesTest.php
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\RestApi\Routes\Tests;

use Etch\RestApi\Routes\AiRagRoutes;
use WP_UnitTestCase;

/**
 * Class AiRagRoutesTest
 *
 * Tests the AiRagRoutes class.
 */
class AiRagRoutesTest extends WP_UnitTestCase {

	/**
	 * Test that health uses default staging endpoint when none is provided.
	 */
	public function test_health_uses_default_staging_endpoint_when_none_is_provided(): void {
		$called_url = null;
		$http       = function ( string $url ) use ( &$called_url ) {
			$called_url = $url;
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => '{"status":"ok"}',
			);
		};

		$routes = new AiRagRoutes( null, $http );
		$routes->health();

		$this->assertSame( 'https://api.etchwp.com/health', $called_url );
	}

	/**
	 * Test that health proxies to middleware when endpoint is configured.
	 */
	public function test_health_proxies_to_middleware_when_endpoint_is_configured(): void {
		$called_url = null;
		$http       = function ( string $url ) use ( &$called_url ) {
			$called_url = $url;
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => '{"status":"ok"}',
			);
		};

		$routes = new AiRagRoutes( 'https://middleware.example.com', $http );
		$routes->health();

		$this->assertSame( 'https://middleware.example.com/health', $called_url );
	}

	/**
	 * Test that health returns ok when middleware responds successfully.
	 */
	public function test_health_returns_ok_when_middleware_responds_successfully(): void {
		$http = function () {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => '{"status":"ok"}',
			);
		};

		$routes = new AiRagRoutes( 'https://middleware.example.com', $http );
		$result = $routes->health();

		$this->assertSame( 200, $result->get_status() );
		$this->assertSame( array( 'status' => 'ok' ), $result->get_data() );
	}

	/**
	 * Test that health returns 502 when middleware returns error status.
	 */
	public function test_health_returns_502_when_middleware_returns_error_status(): void {
		$http = function () {
			return array(
				'response' => array( 'code' => 503 ),
				'body'     => '{"error":"SurrealDB connection failed"}',
			);
		};

		$routes = new AiRagRoutes( 'https://middleware.example.com', $http );
		$result = $routes->health();

		$this->assertSame( 502, $result->get_status() );
	}

	/**
	 * Test that health returns 502 when http request fails.
	 */
	public function test_health_returns_502_when_http_request_fails(): void {
		$http = function () {
			return new \WP_Error( 'http_request_failed', 'Connection refused' );
		};

		$routes = new AiRagRoutes( 'https://middleware.example.com', $http );
		$result = $routes->health();

		$this->assertSame( 502, $result->get_status() );
		$data = $result->get_data();
		$this->assertSame( 'Connection refused', $data['error'] );
	}

	/**
	 * Create a no-op fake HTTP callable.
	 *
	 * @return callable
	 */
	private static function fake_http(): callable {
		return function () {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => '{}',
			);
		};
	}
}
