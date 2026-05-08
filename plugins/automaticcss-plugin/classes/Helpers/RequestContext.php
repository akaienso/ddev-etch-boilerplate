<?php
/**
 * Request context implementation.
 *
 * Default implementation that wraps PHP superglobals ($_GET, $_POST).
 * Provides sanitization via WordPress functions.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Helpers;

/**
 * Default request context implementation using PHP superglobals.
 */
class RequestContext implements RequestContextInterface {

	/**
	 * Check if a GET parameter exists.
	 *
	 * @param string $key The parameter key.
	 * @return bool True if the parameter exists, false otherwise.
	 */
	public function has_get( string $key ): bool {
		return isset( $_GET[ $key ] );
	}

	/**
	 * Get a GET parameter value.
	 *
	 * Returns the sanitized value using sanitize_text_field and wp_unslash.
	 *
	 * @param string $key The parameter key.
	 * @param mixed  $default The default value if the parameter doesn't exist.
	 * @return mixed The sanitized parameter value or default.
	 */
	public function get( string $key, $default = null ) {
		if ( ! $this->has_get( $key ) ) {
			return $default;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Validated by has_get() above.
		return sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
	}

	/**
	 * Check if a POST parameter exists.
	 *
	 * @param string $key The parameter key.
	 * @return bool True if the parameter exists, false otherwise.
	 */
	public function has_post( string $key ): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- This is a low-level accessor; nonce verification is the caller's responsibility.
		return isset( $_POST[ $key ] );
	}

	/**
	 * Get a POST parameter value.
	 *
	 * Returns the sanitized value using sanitize_text_field and wp_unslash.
	 *
	 * @param string $key The parameter key.
	 * @param mixed  $default The default value if the parameter doesn't exist.
	 * @return mixed The sanitized parameter value or default.
	 */
	public function post( string $key, $default = null ) {
		if ( ! $this->has_post( $key ) ) {
			return $default;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Low-level accessor; nonce verification is caller's responsibility; validated by has_post() above.
		return sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
	}
}
