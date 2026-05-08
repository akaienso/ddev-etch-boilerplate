<?php
/**
 * Automatic.css Status CLI Command.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\CLI;

use Automatic_CSS\Framework\Core\Core;
use Automatic_CSS\Framework\Integrations\Bricks;
use Automatic_CSS\Framework\Integrations\Gutenberg;
use Automatic_CSS\Helpers\Flag;
use Automatic_CSS\Model\Database_Settings;
use Automatic_CSS\Plugin;
use WP_CLI;
use WP_CLI\Utils;

/**
 * Display Automatic.css plugin status.
 *
 * ## EXAMPLES
 *
 *     # Show all status info
 *     $ wp acss status
 *
 *     # Get JSON output for scripting
 *     $ wp acss status --format=json
 *
 *     # Show only CSS file status
 *     $ wp acss status --section=css
 */
class Status_Command {

	/**
	 * Display plugin status information.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format. Options: text, json, yaml. Default: text.
	 *
	 * [--section=<section>]
	 * : Show only specific section. Options: plugin, integrations, css, settings, flags.
	 *
	 * ## EXAMPLES
	 *
	 *     # Show all status info
	 *     $ wp acss status
	 *
	 *     # Get JSON output
	 *     $ wp acss status --format=json
	 *
	 *     # Show only CSS files
	 *     $ wp acss status --section=css
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function __invoke( $args, $assoc_args ): void {
		$format  = Utils\get_flag_value( $assoc_args, 'format', 'text' );
		$section = Utils\get_flag_value( $assoc_args, 'section', '' );

		$status = $this->collect_status();

		if ( '' !== $section ) {
			if ( ! isset( $status[ $section ] ) ) {
				WP_CLI::error( sprintf( 'Unknown section "%s". Available: plugin, integrations, css, settings, flags.', $section ) );
			}
			$status = array( $section => $status[ $section ] );
		}

		if ( 'json' === $format ) {
			WP_CLI::log( wp_json_encode( $status, JSON_PRETTY_PRINT ) );
			return;
		}

		if ( 'yaml' === $format ) {
			WP_CLI::print_value( $status, array( 'format' => 'yaml' ) );
			return;
		}

		// Text format (default).
		$this->output_text( $status );
	}

	/**
	 * Collect all status information.
	 *
	 * @return array
	 */
	private function collect_status(): array {
		return array(
			'plugin'       => $this->get_plugin_info(),
			'integrations' => $this->get_integrations_info(),
			'css'          => $this->get_css_files_info(),
			'settings'     => $this->get_settings_info(),
			'flags'        => $this->get_flags_info(),
		);
	}

	/**
	 * Get plugin information.
	 *
	 * @return array
	 */
	private function get_plugin_info(): array {
		return array(
			'version'       => Plugin::get_plugin_version(),
			'db_version'    => get_option( Plugin::ACSS_DB_VERSION, 'not set' ),
			'css_directory' => Plugin::get_dynamic_css_dir(),
			'css_url'       => Plugin::get_dynamic_css_url(),
		);
	}

	/**
	 * Get active integrations information.
	 *
	 * @return array
	 */
	private function get_integrations_info(): array {
		$builders = array();
		$features = array();

		// Check builders.
		$builder_classes = array(
			'bricks'    => Bricks::class,
			'gutenberg' => Gutenberg::class,
		);

		foreach ( $builder_classes as $name => $class ) {
			$builders[ $name ] = $class::is_active();
		}

		return array(
			'builders' => $builders,
		);
	}

	/**
	 * Get CSS files information.
	 *
	 * @return array
	 */
	private function get_css_files_info(): array {
		$css_dir = Plugin::get_dynamic_css_dir();
		$files   = array();

		// Get Core CSS files.
		$core       = new Core();
		$core_files = $core->get_css_files();

		foreach ( $core_files as $css_file ) {
			$file_path = $css_dir . '/' . $css_file->filename;
			$files[]   = $this->get_file_status( $css_file->handle, $file_path );
		}

		// Get Gutenberg CSS files if active.
		if ( Gutenberg::is_active() ) {
			$gutenberg       = new Gutenberg();
			$gutenberg_files = $gutenberg->get_css_files();
			foreach ( $gutenberg_files as $css_file ) {
				$file_path = $css_dir . '/' . $css_file->filename;
				$files[]   = $this->get_file_status( $css_file->handle, $file_path );
			}
		}

		// Get Bricks CSS files if active.
		if ( Bricks::is_active() ) {
			$bricks       = new Bricks();
			$bricks_files = $bricks->get_css_files();
			foreach ( $bricks_files as $css_file ) {
				$file_path = $css_dir . '/' . $css_file->filename;
				$files[]   = $this->get_file_status( $css_file->handle, $file_path );
			}
		}

		return $files;
	}

	/**
	 * Get file status information.
	 *
	 * @param string $handle    The file handle.
	 * @param string $file_path The file path.
	 * @return array
	 */
	private function get_file_status( $handle, $file_path ): array {
		$exists   = file_exists( $file_path );
		$modified = $exists ? gmdate( 'Y-m-d H:i:s', filemtime( $file_path ) ) : null;
		$size     = $exists ? filesize( $file_path ) : null;

		return array(
			'handle'   => $handle,
			'path'     => $file_path,
			'exists'   => $exists,
			'modified' => $modified,
			'size'     => $size,
		);
	}

	/**
	 * Get settings information.
	 *
	 * @return array
	 */
	private function get_settings_info(): array {
		$settings = Database_Settings::get_instance()->get_vars();

		return array(
			'total'       => count( $settings ),
			'option_name' => Database_Settings::ACSS_SETTINGS_OPTION,
		);
	}

	/**
	 * Get feature flags information.
	 *
	 * @return array
	 */
	private function get_flags_info(): array {
		if ( ! Flag::is_initialized() ) {
			return array(
				'initialized' => false,
				'active'      => array(),
				'ignored'     => array(),
			);
		}

		$all_flags    = Flag::get_flags();
		$active_flags = array();

		foreach ( $all_flags as $flag => $value ) {
			if ( Flag::is_on( $flag ) ) {
				$active_flags[] = $flag;
			}
		}

		return array(
			'initialized' => true,
			'active'      => $active_flags,
			'ignored'     => Flag::get_ignored_flags(),
		);
	}

	/**
	 * Output status as formatted text.
	 *
	 * @param array $status The status data.
	 * @return void
	 */
	private function output_text( $status ): void {
		// Plugin Information.
		if ( isset( $status['plugin'] ) ) {
			WP_CLI::log( WP_CLI::colorize( '%GPlugin Information%n' ) );
			WP_CLI::log( sprintf( '  Version:       %s', $status['plugin']['version'] ) );
			WP_CLI::log( sprintf( '  DB Version:    %s', $status['plugin']['db_version'] ) );
			WP_CLI::log( sprintf( '  CSS Directory: %s', $status['plugin']['css_directory'] ) );
			WP_CLI::log( '' );
		}

		// Active Integrations.
		if ( isset( $status['integrations'] ) ) {
			WP_CLI::log( WP_CLI::colorize( '%GActive Integrations%n' ) );
			$builders = $status['integrations']['builders'];
			$active   = array_keys( array_filter( $builders ) );
			WP_CLI::log( sprintf( '  Builders: %s', empty( $active ) ? 'none' : implode( ', ', $active ) ) );
			WP_CLI::log( '' );
		}

		// CSS Files.
		if ( isset( $status['css'] ) ) {
			WP_CLI::log( WP_CLI::colorize( '%GCSS Files%n' ) );
			foreach ( $status['css'] as $file ) {
				$status_text = $file['exists']
					? sprintf( 'exists (modified: %s, size: %s)', $file['modified'], size_format( $file['size'] ) )
					: WP_CLI::colorize( '%Rmissing%n' );
				WP_CLI::log( sprintf( '  %s: %s', $file['handle'], $status_text ) );
			}
			WP_CLI::log( '' );
		}

		// Settings.
		if ( isset( $status['settings'] ) ) {
			WP_CLI::log( WP_CLI::colorize( '%GSettings%n' ) );
			WP_CLI::log( sprintf( '  Total:  %d', $status['settings']['total'] ) );
			WP_CLI::log( sprintf( '  Option: %s', $status['settings']['option_name'] ) );
			WP_CLI::log( '' );
		}

		// Feature Flags.
		if ( isset( $status['flags'] ) ) {
			WP_CLI::log( WP_CLI::colorize( '%GFeature Flags%n' ) );
			if ( ! $status['flags']['initialized'] ) {
				WP_CLI::log( '  Not initialized' );
			} else {
				$active = $status['flags']['active'];
				WP_CLI::log( sprintf( '  Active: %s', empty( $active ) ? 'none' : implode( ', ', $active ) ) );
			}
			WP_CLI::log( '' );
		}
	}
}
