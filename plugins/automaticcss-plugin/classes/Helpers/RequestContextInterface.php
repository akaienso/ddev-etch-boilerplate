<?php
/**
 * Request context interface.
 *
 * Provides an abstraction over HTTP request parameters ($_GET, $_POST, etc.)
 * to improve testability by allowing dependency injection of request data.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Helpers;

/**
 * Interface for accessing HTTP request parameters.
 */
interface RequestContextInterface {

	/**
	 * Check if a GET parameter exists.
	 *
	 * @param string $key The parameter key.
	 * @return bool True if the parameter exists, false otherwise.
	 */
	public function has_get( string $key ): bool;

	/**
	 * Get a GET parameter value.
	 *
	 * @param string $key The parameter key.
	 * @param mixed  $default The default value if the parameter doesn't exist.
	 * @return mixed The parameter value or default.
	 */
	public function get( string $key, $default = null );

	/**
	 * Check if a POST parameter exists.
	 *
	 * @param string $key The parameter key.
	 * @return bool True if the parameter exists, false otherwise.
	 */
	public function has_post( string $key ): bool;

	/**
	 * Get a POST parameter value.
	 *
	 * @param string $key The parameter key.
	 * @param mixed  $default The default value if the parameter doesn't exist.
	 * @return mixed The parameter value or default.
	 */
	public function post( string $key, $default = null );
}
