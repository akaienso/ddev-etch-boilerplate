<?php
/**
 * Automatic.css Flags CLI Command.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\CLI;

use Automatic_CSS\Helpers\Flag;
use Automatic_CSS\Plugin;
use WP_CLI;
use WP_CLI\Utils;

/**
 * Manage Automatic.css feature flags.
 *
 * ## EXAMPLES
 *
 *     # List all flags
 *     $ wp acss flags list
 *
 *     # Get a specific flag value
 *     $ wp acss flags get ENABLE_DEBUG_LOG
 *
 *     # Set a flag in flags.user.json
 *     $ wp acss flags set ENABLE_DEBUG_LOG on
 */
class Flags_Command {

	/**
	 * List all feature flags and their current values.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format. Options: table, json, yaml. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     # List all flags
	 *     $ wp acss flags list
	 *
	 *     # Get JSON output
	 *     $ wp acss flags list --format=json
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function list( $args, $assoc_args ): void {
		$format = Utils\get_flag_value( $assoc_args, 'format', 'table' );

		if ( ! Flag::is_initialized() ) {
			WP_CLI::error( 'Flag system not initialized.' );
		}

		$all_flags    = Flag::get_flags();
		$ignored      = Flag::get_ignored_flags();
		$prod_flags   = $this->get_prod_flags();
		$dev_flags    = $this->get_dev_flags();
		$user_flags   = $this->get_user_flags();

		$items = array();
		foreach ( $all_flags as $flag => $value ) {
			$source = $this->determine_flag_source( $flag, $prod_flags, $dev_flags, $user_flags );

			$items[] = array(
				'flag'   => $flag,
				'value'  => $value,
				'source' => $source,
			);
		}

		if ( 'json' === $format ) {
			WP_CLI::log( wp_json_encode( $items, JSON_PRETTY_PRINT ) );
			return;
		}

		if ( 'yaml' === $format ) {
			WP_CLI::print_value( $items, array( 'format' => 'yaml' ) );
			return;
		}

		// Table format.
		Utils\format_items( 'table', $items, array( 'flag', 'value', 'source' ) );

		// Show ignored flags if any.
		if ( ! empty( $ignored ) ) {
			WP_CLI::log( '' );
			WP_CLI::warning( 'Some flags were ignored (not in flags.json):' );
			foreach ( $ignored as $source => $flags ) {
				WP_CLI::log( sprintf( '  %s: %s', $source, implode( ', ', $flags ) ) );
			}
		}
	}

	/**
	 * Get a specific flag value.
	 *
	 * ## OPTIONS
	 *
	 * <flag>
	 * : The flag name to get.
	 *
	 * ## EXAMPLES
	 *
	 *     # Get a flag value
	 *     $ wp acss flags get ENABLE_DEBUG_LOG
	 *     ENABLE_DEBUG_LOG: off
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function get( $args, $assoc_args ): void {
		if ( empty( $args[0] ) ) {
			WP_CLI::error( 'Please provide a flag name.' );
		}

		$flag_input = $args[0];

		if ( ! Flag::is_initialized() ) {
			WP_CLI::error( 'Flag system not initialized.' );
		}

		$all_flags  = Flag::get_flags();
		$flag_upper = strtoupper( $flag_input );
		$flag_lower = strtolower( $flag_input );

		// Find the actual key used in the flags array.
		$flag_name = null;
		if ( isset( $all_flags[ $flag_lower ] ) ) {
			$flag_name = $flag_lower;
		} elseif ( isset( $all_flags[ $flag_upper ] ) ) {
			$flag_name = $flag_upper;
		}

		if ( null === $flag_name ) {
			WP_CLI::error( sprintf( 'Unknown flag: %s', $flag_input ) );
		}

		WP_CLI::log( sprintf( '%s: %s', $flag_name, $all_flags[ $flag_name ] ) );
	}

	/**
	 * Set a flag value in flags.user.json.
	 *
	 * Creates or updates the flags.user.json file in the uploads directory
	 * to override flag values without modifying the plugin files.
	 *
	 * ## OPTIONS
	 *
	 * <flag>
	 * : The flag name to set.
	 *
	 * <value>
	 * : The value to set (on or off).
	 *
	 * ## EXAMPLES
	 *
	 *     # Enable debug logging
	 *     $ wp acss flags set ENABLE_DEBUG_LOG on
	 *
	 *     # Disable a flag
	 *     $ wp acss flags set ENABLE_DEBUG_LOG off
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function set( $args, $assoc_args ): void {
		if ( count( $args ) < 2 ) {
			WP_CLI::error( 'Please provide a flag name and value (on/off).' );
		}

		$flag_input = $args[0];
		$value      = strtolower( $args[1] );

		if ( ! in_array( $value, array( 'on', 'off' ), true ) ) {
			WP_CLI::error( 'Value must be "on" or "off".' );
		}

		if ( ! Flag::is_initialized() ) {
			WP_CLI::error( 'Flag system not initialized.' );
		}

		// Verify flag exists in main flags.json (check both cases).
		$all_flags  = Flag::get_flags();
		$flag_upper = strtoupper( $flag_input );
		$flag_lower = strtolower( $flag_input );

		if ( ! isset( $all_flags[ $flag_upper ] ) && ! isset( $all_flags[ $flag_lower ] ) ) {
			WP_CLI::error( sprintf( 'Unknown flag: %s. Flag must exist in flags.json.', $flag_input ) );
		}

		// Use uppercase for storage (matches flags.json convention).
		$flag_name = $flag_upper;

		// Load or create user flags file.
		$user_flags_path = Plugin::get_dynamic_css_dir() . '/flags.user.json';
		$user_flags      = $this->get_user_flags();

		// Update the flag.
		$user_flags[ $flag_name ] = $value;

		// Save to file.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$result = file_put_contents(
			$user_flags_path,
			wp_json_encode( $user_flags, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
		);

		if ( false === $result ) {
			WP_CLI::error( sprintf( 'Failed to write to %s', $user_flags_path ) );
		}

		WP_CLI::success( sprintf( 'Set %s to "%s" in flags.user.json', $flag_name, $value ) );
		WP_CLI::log( sprintf( 'Note: Reload the page or run "wp acss css regenerate" to apply changes.' ) );
	}

	/**
	 * Remove a flag override from flags.user.json.
	 *
	 * ## OPTIONS
	 *
	 * <flag>
	 * : The flag name to remove.
	 *
	 * ## EXAMPLES
	 *
	 *     # Remove a flag override
	 *     $ wp acss flags unset ENABLE_DEBUG_LOG
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function unset( $args, $assoc_args ): void {
		if ( empty( $args[0] ) ) {
			WP_CLI::error( 'Please provide a flag name.' );
		}

		$flag_input = $args[0];
		$flag_upper = strtoupper( $flag_input );
		$flag_lower = strtolower( $flag_input );

		$user_flags_path = Plugin::get_dynamic_css_dir() . '/flags.user.json';
		$user_flags      = $this->get_user_flags();

		// Find the actual key used in the user flags (check both cases).
		$flag_name = null;
		if ( isset( $user_flags[ $flag_upper ] ) ) {
			$flag_name = $flag_upper;
		} elseif ( isset( $user_flags[ $flag_lower ] ) ) {
			$flag_name = $flag_lower;
		}

		if ( null === $flag_name ) {
			WP_CLI::warning( sprintf( 'Flag %s is not set in flags.user.json.', $flag_input ) );
			return;
		}

		unset( $user_flags[ $flag_name ] );

		if ( empty( $user_flags ) ) {
			// Delete the file if no more overrides.
			if ( file_exists( $user_flags_path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				unlink( $user_flags_path );
				WP_CLI::success( sprintf( 'Removed %s and deleted empty flags.user.json', $flag_name ) );
				return;
			}
		}

		// Save remaining flags.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$result = file_put_contents(
			$user_flags_path,
			wp_json_encode( $user_flags, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
		);

		if ( false === $result ) {
			WP_CLI::error( sprintf( 'Failed to write to %s', $user_flags_path ) );
		}

		WP_CLI::success( sprintf( 'Removed %s from flags.user.json', $flag_name ) );
	}

	/**
	 * Show the path to flags files.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format. Options: table, json. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     # Show flag file paths
	 *     $ wp acss flags path
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function path( $args, $assoc_args ): void {
		$format = Utils\get_flag_value( $assoc_args, 'format', 'table' );

		$paths = array(
			array(
				'file'   => 'flags.json',
				'path'   => ACSS_PLUGIN_DIR . 'config/flags.json',
				'exists' => file_exists( ACSS_PLUGIN_DIR . 'config/flags.json' ) ? 'yes' : 'no',
			),
			array(
				'file'   => 'flags.dev.json',
				'path'   => ACSS_PLUGIN_DIR . 'config/flags.dev.json',
				'exists' => file_exists( ACSS_PLUGIN_DIR . 'config/flags.dev.json' ) ? 'yes' : 'no',
			),
			array(
				'file'   => 'flags.user.json',
				'path'   => Plugin::get_dynamic_css_dir() . '/flags.user.json',
				'exists' => file_exists( Plugin::get_dynamic_css_dir() . '/flags.user.json' ) ? 'yes' : 'no',
			),
		);

		if ( 'json' === $format ) {
			WP_CLI::log( wp_json_encode( $paths, JSON_PRETTY_PRINT ) );
			return;
		}

		Utils\format_items( 'table', $paths, array( 'file', 'path', 'exists' ) );
	}

	/**
	 * Get user flags from flags.user.json.
	 *
	 * @return array
	 */
	private function get_user_flags(): array {
		$user_flags_path = Plugin::get_dynamic_css_dir() . '/flags.user.json';
		return $this->load_json_flags( $user_flags_path );
	}

	/**
	 * Get production flags from flags.json.
	 *
	 * @return array
	 */
	private function get_prod_flags(): array {
		$prod_flags_path = ACSS_PLUGIN_DIR . 'config/flags.json';
		return $this->load_json_flags( $prod_flags_path );
	}

	/**
	 * Get development flags from flags.dev.json.
	 *
	 * @return array
	 */
	private function get_dev_flags(): array {
		$dev_flags_path = ACSS_PLUGIN_DIR . 'config/flags.dev.json';
		return $this->load_json_flags( $dev_flags_path );
	}

	/**
	 * Load flags from a JSON file.
	 *
	 * @param string $file_path Path to the JSON file.
	 * @return array
	 */
	private function load_json_flags( string $file_path ): array {
		if ( ! file_exists( $file_path ) ) {
			return array();
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = file_get_contents( $file_path );
		$flags   = json_decode( $content, true );

		return is_array( $flags ) ? $flags : array();
	}

	/**
	 * Determine the source of a flag value.
	 *
	 * Priority: flags.user.json > flags.dev.json > flags.json
	 *
	 * @param string $flag       The flag name (lowercase).
	 * @param array  $prod_flags Production flags.
	 * @param array  $dev_flags  Development flags.
	 * @param array  $user_flags User flags.
	 * @return string
	 */
	private function determine_flag_source( string $flag, array $prod_flags, array $dev_flags, array $user_flags ): string {
		// Normalize to uppercase for comparison since files use uppercase keys.
		$flag_upper = strtoupper( $flag );

		// Check in priority order: user > dev > prod.
		if ( isset( $user_flags[ $flag_upper ] ) || isset( $user_flags[ $flag ] ) ) {
			return 'flags.user.json';
		}

		if ( isset( $dev_flags[ $flag_upper ] ) || isset( $dev_flags[ $flag ] ) ) {
			return 'flags.dev.json';
		}

		return 'flags.json';
	}
}
