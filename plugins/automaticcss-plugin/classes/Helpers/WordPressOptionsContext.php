<?php
/**
 * Automatic.css WordPress Options Context file.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Helpers;

/**
 * Production implementation of OptionsContextInterface using WordPress functions.
 *
 * @since 4.0.0
 */
class WordPressOptionsContext implements OptionsContextInterface {

	/**
	 * Retrieves an option value based on an option name.
	 *
	 * @param string $name    Name of the option to retrieve.
	 * @param mixed  $default Default value to return if the option does not exist.
	 * @return mixed Value of the option, or $default if not found.
	 */
	public function get_option( string $name, $default = false ) {
		return get_option( $name, $default );
	}

	/**
	 * Updates the value of an option.
	 *
	 * @param string $name  Name of the option to update.
	 * @param mixed  $value Option value.
	 * @return bool True if the option was updated, false otherwise.
	 */
	public function update_option( string $name, $value ): bool {
		// Cast to bool because some object cache plugins return null instead of bool.
		return (bool) update_option( $name, $value );
	}

	/**
	 * Removes an option by name.
	 *
	 * @param string $name Name of the option to delete.
	 * @return bool True if the option was deleted, false otherwise.
	 */
	public function delete_option( string $name ): bool {
		// Cast to bool because some object cache plugins return null instead of bool.
		return (bool) delete_option( $name );
	}

	/**
	 * Retrieves the value of a transient.
	 *
	 * @param string $name Transient name.
	 * @return mixed Value of the transient, or false if not found/expired.
	 */
	public function get_transient( string $name ) {
		return get_transient( $name );
	}

	/**
	 * Sets/updates the value of a transient.
	 *
	 * @param string $name       Transient name.
	 * @param mixed  $value      Transient value.
	 * @param int    $expiration Time until expiration in seconds (0 means no expiration).
	 * @return bool True if the value was set, false otherwise.
	 */
	public function set_transient( string $name, $value, int $expiration = 0 ): bool {
		// Cast to bool because some object cache plugins return null instead of bool.
		return (bool) set_transient( $name, $value, $expiration );
	}

	/**
	 * Deletes a transient.
	 *
	 * @param string $name Transient name.
	 * @return bool True if the transient was deleted, false otherwise.
	 */
	public function delete_transient( string $name ): bool {
		// Cast to bool because some object cache plugins return null instead of bool.
		return (bool) delete_transient( $name );
	}
}
