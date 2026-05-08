<?php
/**
 * Automatic.css In-Memory Options Context file.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Helpers;

/**
 * Test double implementation of OptionsContextInterface using in-memory storage.
 *
 * This class is used for unit testing without requiring a database connection.
 *
 * @since 4.0.0
 */
class InMemoryOptionsContext implements OptionsContextInterface {

	/**
	 * In-memory storage for options.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = array();

	/**
	 * In-memory storage for transients (value and expiration).
	 *
	 * @var array<string, array{value: mixed, expiration: int}>
	 */
	private array $transients = array();

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $initial_options   Initial options to populate.
	 * @param array<string, mixed> $initial_transients Initial transients to populate (values only, no expiration).
	 */
	public function __construct( array $initial_options = array(), array $initial_transients = array() ) {
		$this->options = $initial_options;
		foreach ( $initial_transients as $name => $value ) {
			$this->transients[ $name ] = array(
				'value'      => $value,
				'expiration' => 0, // No expiration.
			);
		}
	}

	/**
	 * Retrieves an option value based on an option name.
	 *
	 * @param string $name    Name of the option to retrieve.
	 * @param mixed  $default Default value to return if the option does not exist.
	 * @return mixed Value of the option, or $default if not found.
	 */
	public function get_option( string $name, $default = false ) {
		return $this->options[ $name ] ?? $default;
	}

	/**
	 * Updates the value of an option.
	 *
	 * @param string $name  Name of the option to update.
	 * @param mixed  $value Option value.
	 * @return bool True if the option was updated, false otherwise.
	 */
	public function update_option( string $name, $value ): bool {
		$this->options[ $name ] = $value;
		return true;
	}

	/**
	 * Removes an option by name.
	 *
	 * @param string $name Name of the option to delete.
	 * @return bool True if the option was deleted, false otherwise.
	 */
	public function delete_option( string $name ): bool {
		if ( isset( $this->options[ $name ] ) ) {
			unset( $this->options[ $name ] );
			return true;
		}
		return false;
	}

	/**
	 * Retrieves the value of a transient.
	 *
	 * Note: This implementation does not simulate time-based expiration.
	 * Transients are considered expired only if explicitly deleted.
	 *
	 * @param string $name Transient name.
	 * @return mixed Value of the transient, or false if not found/expired.
	 */
	public function get_transient( string $name ) {
		if ( ! isset( $this->transients[ $name ] ) ) {
			return false;
		}
		return $this->transients[ $name ]['value'];
	}

	/**
	 * Sets/updates the value of a transient.
	 *
	 * @param string $name       Transient name.
	 * @param mixed  $value      Transient value.
	 * @param int    $expiration Time until expiration in seconds (stored but not enforced).
	 * @return bool True if the value was set, false otherwise.
	 */
	public function set_transient( string $name, $value, int $expiration = 0 ): bool {
		$this->transients[ $name ] = array(
			'value'      => $value,
			'expiration' => $expiration,
		);
		return true;
	}

	/**
	 * Deletes a transient.
	 *
	 * @param string $name Transient name.
	 * @return bool True if the transient was deleted, false otherwise.
	 */
	public function delete_transient( string $name ): bool {
		if ( isset( $this->transients[ $name ] ) ) {
			unset( $this->transients[ $name ] );
			return true;
		}
		return false;
	}

	/**
	 * Get all stored options (for testing assertions).
	 *
	 * @return array<string, mixed>
	 */
	public function get_all_options(): array {
		return $this->options;
	}

	/**
	 * Get all stored transients (for testing assertions).
	 *
	 * @return array<string, array{value: mixed, expiration: int}>
	 */
	public function get_all_transients(): array {
		return $this->transients;
	}

	/**
	 * Clear all stored options and transients (for test cleanup).
	 *
	 * @return void
	 */
	public function clear(): void {
		$this->options    = array();
		$this->transients = array();
	}
}
