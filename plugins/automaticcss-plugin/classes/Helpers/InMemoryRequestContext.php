<?php
/**
 * In-memory request context implementation.
 *
 * Test double for RequestContextInterface that uses in-memory arrays
 * instead of PHP superglobals. Useful for unit testing.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Helpers;

/**
 * In-memory request context for testing.
 */
class InMemoryRequestContext implements RequestContextInterface {

	/**
	 * GET parameters.
	 *
	 * @var array<string, mixed>
	 */
	private $get_params;

	/**
	 * POST parameters.
	 *
	 * @var array<string, mixed>
	 */
	private $post_params;

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $get_params  GET parameters.
	 * @param array<string, mixed> $post_params POST parameters.
	 */
	public function __construct( array $get_params = array(), array $post_params = array() ) {
		$this->get_params = $get_params;
		$this->post_params = $post_params;
	}

	/**
	 * Check if a GET parameter exists.
	 *
	 * @param string $key The parameter key.
	 * @return bool True if the parameter exists, false otherwise.
	 */
	public function has_get( string $key ): bool {
		return array_key_exists( $key, $this->get_params );
	}

	/**
	 * Get a GET parameter value.
	 *
	 * @param string $key The parameter key.
	 * @param mixed  $default The default value if the parameter doesn't exist.
	 * @return mixed The parameter value or default.
	 */
	public function get( string $key, $default = null ) {
		if ( ! $this->has_get( $key ) ) {
			return $default;
		}
		return $this->get_params[ $key ];
	}

	/**
	 * Check if a POST parameter exists.
	 *
	 * @param string $key The parameter key.
	 * @return bool True if the parameter exists, false otherwise.
	 */
	public function has_post( string $key ): bool {
		return array_key_exists( $key, $this->post_params );
	}

	/**
	 * Get a POST parameter value.
	 *
	 * @param string $key The parameter key.
	 * @param mixed  $default The default value if the parameter doesn't exist.
	 * @return mixed The parameter value or default.
	 */
	public function post( string $key, $default = null ) {
		if ( ! $this->has_post( $key ) ) {
			return $default;
		}
		return $this->post_params[ $key ];
	}

	/**
	 * Set a GET parameter (for test setup).
	 *
	 * @param string $key   The parameter key.
	 * @param mixed  $value The parameter value.
	 * @return self For method chaining.
	 */
	public function with_get( string $key, $value ): self {
		$this->get_params[ $key ] = $value;
		return $this;
	}

	/**
	 * Set a POST parameter (for test setup).
	 *
	 * @param string $key   The parameter key.
	 * @param mixed  $value The parameter value.
	 * @return self For method chaining.
	 */
	public function with_post( string $key, $value ): self {
		$this->post_params[ $key ] = $value;
		return $this;
	}
}
