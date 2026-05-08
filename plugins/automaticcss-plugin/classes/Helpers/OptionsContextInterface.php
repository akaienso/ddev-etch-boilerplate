<?php
/**
 * Automatic.css Options Context Interface file.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Helpers;

/**
 * Interface for WordPress options and transients operations.
 *
 * This interface abstracts WordPress's options and transients API to enable
 * unit testing without requiring a database connection.
 *
 * @since 4.0.0
 */
interface OptionsContextInterface {

	/**
	 * Retrieves an option value based on an option name.
	 *
	 * @param string $name    Name of the option to retrieve.
	 * @param mixed  $default Default value to return if the option does not exist.
	 * @return mixed Value of the option, or $default if not found.
	 */
	public function get_option( string $name, $default = false );

	/**
	 * Updates the value of an option.
	 *
	 * @param string $name  Name of the option to update.
	 * @param mixed  $value Option value.
	 * @return bool True if the option was updated, false otherwise.
	 */
	public function update_option( string $name, $value ): bool;

	/**
	 * Removes an option by name.
	 *
	 * @param string $name Name of the option to delete.
	 * @return bool True if the option was deleted, false otherwise.
	 */
	public function delete_option( string $name ): bool;

	/**
	 * Retrieves the value of a transient.
	 *
	 * @param string $name Transient name.
	 * @return mixed Value of the transient, or false if not found/expired.
	 */
	public function get_transient( string $name );

	/**
	 * Sets/updates the value of a transient.
	 *
	 * @param string $name       Transient name.
	 * @param mixed  $value      Transient value.
	 * @param int    $expiration Time until expiration in seconds (0 means no expiration).
	 * @return bool True if the value was set, false otherwise.
	 */
	public function set_transient( string $name, $value, int $expiration = 0 ): bool;

	/**
	 * Deletes a transient.
	 *
	 * @param string $name Transient name.
	 * @return bool True if the transient was deleted, false otherwise.
	 */
	public function delete_transient( string $name ): bool;
}
