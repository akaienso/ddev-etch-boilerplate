<?php
/**
 * WordPress options context implementation.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Lifecycle;

/**
 * WordPress implementation of OptionsContextInterface.
 *
 * Wraps WordPress options and transient functions for use in production.
 */
class WpOptionsContext implements OptionsContextInterface {

	/**
	 * Get an option value.
	 *
	 * @param string $option  Name of the option to retrieve.
	 * @param mixed  $default Default value to return if option doesn't exist.
	 * @return mixed Option value or default.
	 */
	public function get_option( string $option, $default = false ) {
		return get_option( $option, $default );
	}

	/**
	 * Update an option value.
	 *
	 * @param string $option   Name of the option to update.
	 * @param mixed  $value    Option value.
	 * @param bool   $autoload Whether to autoload the option.
	 * @return bool True if the option was updated, false otherwise.
	 */
	public function update_option( string $option, $value, bool $autoload = true ): bool {
		// Cast to bool because some object cache plugins return null instead of bool.
		return (bool) update_option( $option, $value, $autoload );
	}

	/**
	 * Delete an option.
	 *
	 * @param string $option Name of the option to delete.
	 * @return bool True if the option was deleted, false otherwise.
	 */
	public function delete_option( string $option ): bool {
		// Cast to bool because some object cache plugins return null instead of bool.
		return (bool) delete_option( $option );
	}

	/**
	 * Get a transient value.
	 *
	 * @param string $transient Transient name.
	 * @return mixed Transient value or false if not set.
	 */
	public function get_transient( string $transient ) {
		return get_transient( $transient );
	}

	/**
	 * Set a transient value.
	 *
	 * @param string $transient  Transient name.
	 * @param mixed  $value      Transient value.
	 * @param int    $expiration Time until expiration in seconds.
	 * @return bool True if the transient was set, false otherwise.
	 */
	public function set_transient( string $transient, $value, int $expiration = 0 ): bool {
		// Cast to bool because some object cache plugins return null instead of bool.
		return (bool) set_transient( $transient, $value, $expiration );
	}

	/**
	 * Delete a transient.
	 *
	 * @param string $transient Transient name.
	 * @return bool True if the transient was deleted, false otherwise.
	 */
	public function delete_transient( string $transient ): bool {
		// Cast to bool because some object cache plugins return null instead of bool.
		return (bool) delete_transient( $transient );
	}
}
