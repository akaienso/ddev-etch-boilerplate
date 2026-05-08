<?php
/**
 * Automatic.css Settings CLI Command.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\CLI;

use Automatic_CSS\API;
use Automatic_CSS\Exceptions\Insufficient_Permissions;
use Automatic_CSS\Exceptions\Invalid_Form_Values;
use Automatic_CSS\Model\Config\UI;
use Automatic_CSS\Model\Database_Settings;
use WP_CLI;
use WP_CLI\Utils;

/**
 * Manage Automatic.css settings.
 *
 * ## EXAMPLES
 *
 *     # Get all settings as JSON
 *     $ wp acss settings get --format=json
 *
 *     # Get a specific setting
 *     $ wp acss settings get color-primary
 *
 *     # Set a setting value
 *     $ wp acss settings set color-primary "#ff0000"
 *
 *     # List all settings matching a pattern
 *     $ wp acss settings list --search=color
 *
 *     # Export settings to a file
 *     $ wp acss settings export --file=/tmp/acss-settings.json
 *
 *     # Import settings from a file
 *     $ wp acss settings import /tmp/acss-settings.json
 *
 *     # Reset all settings to defaults
 *     $ wp acss settings reset --yes
 */
class Settings_Command {

	/**
	 * Get one or all settings.
	 *
	 * ## OPTIONS
	 *
	 * [<key>]
	 * : The setting key to retrieve. If omitted, returns all settings.
	 *
	 * [--format=<format>]
	 * : Output format. Options: json, yaml, var_export. Default: json for all, value for single.
	 *
	 * ## EXAMPLES
	 *
	 *     # Get all settings as JSON
	 *     $ wp acss settings get
	 *
	 *     # Get a specific setting
	 *     $ wp acss settings get color-primary
	 *     #3366cc
	 *
	 *     # Get all settings as YAML
	 *     $ wp acss settings get --format=yaml
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function get( $args, $assoc_args ) {
		$settings = Database_Settings::get_instance()->get_vars();

		// Single key requested.
		if ( ! empty( $args[0] ) ) {
			$key = $args[0];
			if ( ! array_key_exists( $key, $settings ) ) {
				WP_CLI::error( sprintf( 'Setting "%s" not found.', $key ) );
			}

			$value  = $settings[ $key ];
			$format = Utils\get_flag_value( $assoc_args, 'format', '' );

			if ( '' === $format ) {
				// Default: just output the value.
				WP_CLI::log( is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) );
			} else {
				WP_CLI::print_value( $value, array( 'format' => $format ) );
			}
			return;
		}

		// All settings.
		$format = Utils\get_flag_value( $assoc_args, 'format', 'json' );
		WP_CLI::print_value( $settings, array( 'format' => $format ) );
	}

	/**
	 * Set a setting value.
	 *
	 * ## OPTIONS
	 *
	 * <key>
	 * : The setting key to update.
	 *
	 * <value>
	 * : The value to set.
	 *
	 * [--force]
	 * : Skip validation and set the value directly.
	 *
	 * [--skip-css]
	 * : Skip CSS regeneration after saving.
	 *
	 * ## EXAMPLES
	 *
	 *     # Set a color value
	 *     $ wp acss settings set color-primary "#ff0000"
	 *     Success: Setting "color-primary" updated. 4 CSS files regenerated.
	 *
	 *     # Set without regenerating CSS
	 *     $ wp acss settings set color-primary "#ff0000" --skip-css
	 *     Success: Setting "color-primary" updated.
	 *
	 *     # Force set an invalid value (for testing)
	 *     $ wp acss settings set color-primary "invalid" --force
	 *     Success: Setting "color-primary" updated (validation skipped).
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function set( $args, $assoc_args ) {
		if ( count( $args ) < 2 ) {
			WP_CLI::error( 'Usage: wp acss settings set <key> <value>' );
		}

		$key      = $args[0];
		$value    = $args[1];
		$force    = Utils\get_flag_value( $assoc_args, 'force', false );
		$skip_css = Utils\get_flag_value( $assoc_args, 'skip-css', false );

		// Check if setting exists in schema (unless forcing).
		if ( ! $force ) {
			$ui              = new UI();
			$all_settings    = $ui->get_all_settings();
			if ( ! isset( $all_settings[ $key ] ) ) {
				WP_CLI::error( sprintf( 'Unknown setting "%s". Use --force to set anyway.', $key ) );
			}
		}

		try {
			if ( $force ) {
				// Direct database update, bypassing validation.
				$settings         = Database_Settings::get_instance();
				$current          = $settings->get_vars();
				$current[ $key ]  = $value;
				$result           = $settings->save_settings( $current, ! $skip_css );

				$message = sprintf( 'Setting "%s" updated (validation skipped).', $key );
			} else {
				// Use API for proper merge and validation.
				$result = API::update_settings(
					array( $key => $value ),
					array( 'regenerate_css' => ! $skip_css )
				);

				$message = sprintf( 'Setting "%s" updated.', $key );
			}

			if ( $result && isset( $result['generated_files_number'] ) && $result['generated_files_number'] > 0 ) {
				$message .= sprintf( ' %d CSS files regenerated.', $result['generated_files_number'] );
			}

			WP_CLI::success( $message );
		} catch ( Invalid_Form_Values $e ) {
			WP_CLI::error( sprintf( 'Validation failed: %s', $e->getMessage() ) );
		} catch ( Insufficient_Permissions $e ) {
			WP_CLI::error( sprintf( 'Permission denied: %s', $e->getMessage() ) );
		} catch ( \Exception $e ) {
			WP_CLI::error( sprintf( 'Error: %s', $e->getMessage() ) );
		}
	}

	/**
	 * List all settings.
	 *
	 * ## OPTIONS
	 *
	 * [--search=<pattern>]
	 * : Filter settings by key pattern (substring match).
	 *
	 * [--format=<format>]
	 * : Output format. Options: table, json, csv, yaml, count. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     # List all settings in a table
	 *     $ wp acss settings list
	 *
	 *     # List settings matching "color"
	 *     $ wp acss settings list --search=color
	 *
	 *     # Get count of settings
	 *     $ wp acss settings list --format=count
	 *
	 * @subcommand list
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function list_( $args, $assoc_args ) {
		$settings = Database_Settings::get_instance()->get_vars();
		$search   = Utils\get_flag_value( $assoc_args, 'search', '' );
		$format   = Utils\get_flag_value( $assoc_args, 'format', 'table' );

		// Filter by search pattern.
		if ( '' !== $search ) {
			$settings = array_filter(
				$settings,
				function ( $key ) use ( $search ) {
					return false !== strpos( $key, $search );
				},
				ARRAY_FILTER_USE_KEY
			);
		}

		if ( empty( $settings ) ) {
			WP_CLI::warning( 'No settings found.' );
			return;
		}

		// Build items array for format_items.
		$items = array();
		foreach ( $settings as $key => $value ) {
			$items[] = array(
				'key'   => $key,
				'value' => is_scalar( $value ) ? (string) $value : wp_json_encode( $value ),
			);
		}

		Utils\format_items( $format, $items, array( 'key', 'value' ) );
	}

	/**
	 * Export settings to a JSON file.
	 *
	 * ## OPTIONS
	 *
	 * [--file=<path>]
	 * : Path to export file. If omitted, outputs to stdout.
	 *
	 * ## EXAMPLES
	 *
	 *     # Export to stdout
	 *     $ wp acss settings export
	 *
	 *     # Export to a file
	 *     $ wp acss settings export --file=/tmp/acss-settings.json
	 *     Success: Settings exported to /tmp/acss-settings.json
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function export( $args, $assoc_args ) {
		$settings = Database_Settings::get_instance()->get_vars();
		$json     = wp_json_encode( $settings, JSON_PRETTY_PRINT );
		$file     = Utils\get_flag_value( $assoc_args, 'file', '' );

		if ( '' === $file ) {
			WP_CLI::log( $json );
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$result = file_put_contents( $file, $json );
		if ( false === $result ) {
			WP_CLI::error( sprintf( 'Failed to write to file: %s', $file ) );
		}

		WP_CLI::success( sprintf( 'Settings exported to %s (%d settings)', $file, count( $settings ) ) );
	}

	/**
	 * Import settings from a JSON file.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to the JSON file to import.
	 *
	 * [--force]
	 * : Skip validation during import.
	 *
	 * [--skip-css]
	 * : Skip CSS regeneration after import.
	 *
	 * [--yes]
	 * : Skip confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     # Import settings from a file
	 *     $ wp acss settings import /tmp/acss-settings.json
	 *     Are you sure you want to replace all settings? [y/n] y
	 *     Success: Settings imported. 4 CSS files regenerated.
	 *
	 *     # Import without confirmation
	 *     $ wp acss settings import /tmp/acss-settings.json --yes
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function import( $args, $assoc_args ) {
		if ( empty( $args[0] ) ) {
			WP_CLI::error( 'Usage: wp acss settings import <file>' );
		}

		$file = $args[0];
		if ( ! file_exists( $file ) ) {
			WP_CLI::error( sprintf( 'File not found: %s', $file ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$json = file_get_contents( $file );
		if ( false === $json ) {
			WP_CLI::error( sprintf( 'Failed to read file: %s', $file ) );
		}

		$settings = json_decode( $json, true );
		if ( null === $settings || ! is_array( $settings ) ) {
			WP_CLI::error( 'Invalid JSON file. Expected an object/array of settings.' );
		}

		// Confirmation.
		WP_CLI::confirm( 'This will replace all current settings. Continue?', $assoc_args );

		$force  = Utils\get_flag_value( $assoc_args, 'force', false );
		$skip_css = Utils\get_flag_value( $assoc_args, 'skip-css', false );

		try {
			$db = Database_Settings::get_instance();

			if ( $force ) {
				// Direct save, bypassing validation.
				$result = $db->save_settings( $settings, ! $skip_css );
			} else {
				// Use save_settings which includes validation.
				$result = $db->save_settings( $settings, ! $skip_css );
			}

			$message = sprintf( 'Settings imported (%d settings).', count( $settings ) );
			if ( $result && isset( $result['generated_files_number'] ) && $result['generated_files_number'] > 0 ) {
				$message .= sprintf( ' %d CSS files regenerated.', $result['generated_files_number'] );
			}

			WP_CLI::success( $message );
		} catch ( Invalid_Form_Values $e ) {
			WP_CLI::error( sprintf( 'Validation failed: %s', $e->getMessage() ) );
		} catch ( Insufficient_Permissions $e ) {
			WP_CLI::error( sprintf( 'Permission denied: %s', $e->getMessage() ) );
		} catch ( \Exception $e ) {
			WP_CLI::error( sprintf( 'Error: %s', $e->getMessage() ) );
		}
	}

	/**
	 * Reset all settings to defaults.
	 *
	 * ## OPTIONS
	 *
	 * [--skip-css]
	 * : Skip CSS regeneration after reset.
	 *
	 * [--yes]
	 * : Skip confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     # Reset with confirmation
	 *     $ wp acss settings reset
	 *     Are you sure you want to reset all settings to defaults? [y/n] y
	 *     Success: Settings reset to defaults. 4 CSS files regenerated.
	 *
	 *     # Reset without confirmation
	 *     $ wp acss settings reset --yes
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function reset( $args, $assoc_args ) {
		WP_CLI::confirm( 'This will reset ALL settings to defaults. Continue?', $assoc_args );

		$skip_css = Utils\get_flag_value( $assoc_args, 'skip-css', false );

		try {
			$ui       = new UI();
			$defaults = $ui->get_default_settings();
			$db       = Database_Settings::get_instance();
			$result   = $db->save_settings( $defaults, ! $skip_css );

			$message = sprintf( 'Settings reset to defaults (%d settings).', count( $defaults ) );
			if ( $result && isset( $result['generated_files_number'] ) && $result['generated_files_number'] > 0 ) {
				$message .= sprintf( ' %d CSS files regenerated.', $result['generated_files_number'] );
			}

			WP_CLI::success( $message );
		} catch ( Invalid_Form_Values $e ) {
			WP_CLI::error( sprintf( 'Validation failed during reset: %s', $e->getMessage() ) );
		} catch ( Insufficient_Permissions $e ) {
			WP_CLI::error( sprintf( 'Permission denied: %s', $e->getMessage() ) );
		} catch ( \Exception $e ) {
			WP_CLI::error( sprintf( 'Error: %s', $e->getMessage() ) );
		}
	}
}
