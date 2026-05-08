<?php
/**
 * Automatic.css Doctor CLI Command.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\CLI;

use Automatic_CSS\Framework\Core\Core;
use Automatic_CSS\Framework\Integrations\Bricks;
use Automatic_CSS\Framework\Integrations\Gutenberg;
use Automatic_CSS\Model\Config\Expansions;
use Automatic_CSS\Model\Config\Framework;
use Automatic_CSS\Model\Database_Settings;
use Automatic_CSS\Plugin;
use WP_CLI;
use WP_CLI\Utils;

/**
 * Run diagnostic checks on Automatic.css installation.
 *
 * ## EXAMPLES
 *
 *     # Run all health checks
 *     $ wp acss doctor
 *
 *     # Get JSON output for scripting
 *     $ wp acss doctor --format=json
 *
 *     # Attempt to fix issues
 *     $ wp acss doctor --fix
 */
class Doctor_Command {

	/**
	 * Status constants for check results.
	 */
	private const STATUS_OK      = 'ok';
	private const STATUS_WARNING = 'warning';
	private const STATUS_ERROR   = 'error';
	private const STATUS_INFO    = 'info';

	/**
	 * Run diagnostic checks on the plugin installation.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format. Options: text, json. Default: text.
	 *
	 * [--fix]
	 * : Attempt to fix issues where possible (e.g., create missing directories).
	 *
	 * ## EXAMPLES
	 *
	 *     # Run all health checks
	 *     $ wp acss doctor
	 *
	 *     # Get JSON output
	 *     $ wp acss doctor --format=json
	 *
	 *     # Attempt to fix issues
	 *     $ wp acss doctor --fix
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function __invoke( $args, $assoc_args ): void {
		$format = Utils\get_flag_value( $assoc_args, 'format', 'text' );
		$fix    = Utils\get_flag_value( $assoc_args, 'fix', false );

		$results = $this->run_checks( $fix );

		if ( 'json' === $format ) {
			WP_CLI::log( wp_json_encode( $results, JSON_PRETTY_PRINT ) );
			return;
		}

		$this->output_text( $results );
	}

	/**
	 * Run all diagnostic checks.
	 *
	 * @param bool $fix Whether to attempt fixes.
	 * @return array
	 */
	private function run_checks( bool $fix ): array {
		return array(
			'environment'   => $this->check_environment(),
			'filesystem'    => $this->check_filesystem( $fix ),
			'plugin'        => $this->check_plugin_health(),
			'configuration' => $this->check_configuration(),
			'css'           => $this->check_css_files(),
			'integrations'  => $this->check_integrations(),
			'activity'      => $this->check_recent_activity(),
		);
	}

	/**
	 * Check environment requirements.
	 *
	 * @return array
	 */
	private function check_environment(): array {
		$checks = array();

		// PHP version.
		$php_version = PHP_VERSION;
		$checks[]    = array(
			'label'   => 'PHP version',
			'status'  => version_compare( $php_version, '8.1', '>=' ) ? self::STATUS_OK : self::STATUS_ERROR,
			'message' => sprintf( '%s (requires 8.1+)', $php_version ),
		);

		// WordPress version.
		global $wp_version;
		$checks[] = array(
			'label'   => 'WordPress version',
			'status'  => version_compare( $wp_version, '5.9', '>=' ) ? self::STATUS_OK : self::STATUS_ERROR,
			'message' => sprintf( '%s (requires 5.9+)', $wp_version ),
		);

		// WP_DEBUG status.
		$checks[] = array(
			'label'   => 'WP_DEBUG',
			'status'  => self::STATUS_INFO,
			'message' => defined( 'WP_DEBUG' ) && WP_DEBUG ? 'enabled' : 'disabled',
		);

		return $checks;
	}

	/**
	 * Check filesystem requirements.
	 *
	 * @param bool $fix Whether to attempt fixes.
	 * @return array
	 */
	private function check_filesystem( bool $fix ): array {
		$css_dir = Plugin::get_dynamic_css_dir();
		$checks  = array();

		// Directory exists.
		$exists = is_dir( $css_dir );
		if ( ! $exists && $fix ) {
			$exists = wp_mkdir_p( $css_dir );
			if ( $exists ) {
				WP_CLI::log( sprintf( 'Fixed: Created CSS directory %s', $css_dir ) );
			}
		}
		$checks[] = array(
			'label'   => 'CSS directory exists',
			'status'  => $exists ? self::STATUS_OK : self::STATUS_ERROR,
			'message' => $css_dir,
		);

		// Directory writable.
		if ( $exists ) {
			$writable = is_writable( $css_dir );
			$checks[] = array(
				'label'   => 'CSS directory writable',
				'status'  => $writable ? self::STATUS_OK : self::STATUS_ERROR,
				'message' => $writable ? 'yes' : 'no - check permissions',
			);
		}

		// Activity log accessible.
		$log_path   = $css_dir . '/activity.log';
		$log_exists = file_exists( $log_path );
		if ( $log_exists ) {
			$checks[] = array(
				'label'   => 'Activity log',
				'status'  => is_readable( $log_path ) ? self::STATUS_OK : self::STATUS_WARNING,
				'message' => is_readable( $log_path ) ? 'accessible' : 'not readable',
			);
		} else {
			$checks[] = array(
				'label'   => 'Activity log',
				'status'  => self::STATUS_INFO,
				'message' => 'not created yet',
			);
		}

		return $checks;
	}

	/**
	 * Check plugin health.
	 *
	 * @return array
	 */
	private function check_plugin_health(): array {
		$checks = array();

		// Plugin version.
		$plugin_version = Plugin::get_plugin_version();
		$checks[]       = array(
			'label'   => 'Plugin version',
			'status'  => self::STATUS_INFO,
			'message' => $plugin_version,
		);

		// DB version comparison.
		$db_version = get_option( Plugin::ACSS_DB_VERSION, 'not set' );
		$versions_match = $plugin_version === $db_version;
		$checks[]       = array(
			'label'   => 'DB version',
			'status'  => $versions_match ? self::STATUS_OK : self::STATUS_WARNING,
			'message' => $versions_match ? sprintf( '%s (up to date)', $db_version ) : sprintf( '%s (migration may be needed)', $db_version ),
		);

		// Settings exist.
		$settings       = Database_Settings::get_instance()->get_vars();
		$settings_count = count( $settings );
		$checks[]       = array(
			'label'   => 'Settings',
			'status'  => $settings_count > 0 ? self::STATUS_OK : self::STATUS_ERROR,
			'message' => $settings_count > 0 ? sprintf( '%d settings configured', $settings_count ) : 'no settings found',
		);

		// Check upgrade lock.
		$upgrade_lock = get_transient( Plugin::ACSS_DATABASE_UPGRADE_LOCK_OPTION );
		$checks[]     = array(
			'label'   => 'Upgrade lock',
			'status'  => false === $upgrade_lock ? self::STATUS_OK : self::STATUS_WARNING,
			'message' => false === $upgrade_lock ? 'not set' : 'LOCKED - upgrade in progress or stuck',
		);

		// Check delete lock.
		$delete_lock = get_option( Plugin::ACSS_DATABASE_DELETE_LOCK_OPTION, false );
		$checks[]    = array(
			'label'   => 'Delete lock',
			'status'  => ! $delete_lock ? self::STATUS_OK : self::STATUS_WARNING,
			'message' => ! $delete_lock ? 'not set' : 'LOCKED - delete in progress or stuck',
		);

		return $checks;
	}

	/**
	 * Check configuration files.
	 *
	 * @return array
	 */
	private function check_configuration(): array {
		$checks = array();

		// Framework config.
		try {
			$framework = new Framework();
			$framework->load();
			$checks[] = array(
				'label'   => 'framework.json',
				'status'  => self::STATUS_OK,
				'message' => 'loaded successfully',
			);
		} catch ( \Exception $e ) {
			$checks[] = array(
				'label'   => 'framework.json',
				'status'  => self::STATUS_ERROR,
				'message' => $e->getMessage(),
			);
		}

		// Expansions config.
		try {
			$expansions = new Expansions();
			$expansions->load();
			$checks[] = array(
				'label'   => 'Utility expansions',
				'status'  => self::STATUS_OK,
				'message' => 'loaded successfully',
			);
		} catch ( \Exception $e ) {
			$checks[] = array(
				'label'   => 'Utility expansions',
				'status'  => self::STATUS_ERROR,
				'message' => $e->getMessage(),
			);
		}

		return $checks;
	}

	/**
	 * Check CSS files.
	 *
	 * @return array
	 */
	private function check_css_files(): array {
		$css_dir = Plugin::get_dynamic_css_dir();
		$checks  = array();

		// Get Core CSS files.
		$core       = new Core();
		$core_files = $core->get_css_files();

		foreach ( $core_files as $css_file ) {
			$file_path = $css_dir . '/' . $css_file->filename;
			$checks[]  = $this->get_css_file_check( $css_file->handle, $file_path );
		}

		// Get Gutenberg CSS files if active.
		if ( Gutenberg::is_active() ) {
			$gutenberg       = new Gutenberg();
			$gutenberg_files = $gutenberg->get_css_files();
			foreach ( $gutenberg_files as $css_file ) {
				$file_path = $css_dir . '/' . $css_file->filename;
				$checks[]  = $this->get_css_file_check( $css_file->handle, $file_path );
			}
		}

		// Get Bricks CSS files if active.
		if ( Bricks::is_active() ) {
			$bricks       = new Bricks();
			$bricks_files = $bricks->get_css_files();
			foreach ( $bricks_files as $css_file ) {
				$file_path = $css_dir . '/' . $css_file->filename;
				$checks[]  = $this->get_css_file_check( $css_file->handle, $file_path );
			}
		}

		return $checks;
	}

	/**
	 * Get a CSS file check result.
	 *
	 * @param string $handle    The file handle.
	 * @param string $file_path The file path.
	 * @return array
	 */
	private function get_css_file_check( string $handle, string $file_path ): array {
		if ( ! file_exists( $file_path ) ) {
			return array(
				'label'   => $handle,
				'status'  => self::STATUS_ERROR,
				'message' => 'missing',
			);
		}

		$size     = filesize( $file_path );
		$modified = filemtime( $file_path );
		$age_days = ( time() - $modified ) / DAY_IN_SECONDS;

		// Check if stale (older than 7 days).
		$status = self::STATUS_OK;
		if ( $age_days > 7 ) {
			$status = self::STATUS_WARNING;
		}

		return array(
			'label'   => $handle,
			'status'  => $status,
			'message' => sprintf(
				'%s, %s',
				size_format( $size ),
				$this->human_time_diff( $modified )
			),
		);
	}

	/**
	 * Check integrations.
	 *
	 * @return array
	 */
	private function check_integrations(): array {
		$checks = array();

		// Gutenberg.
		$gutenberg_active = Gutenberg::is_active();
		$checks[]         = array(
			'label'   => 'Gutenberg',
			'status'  => $gutenberg_active ? self::STATUS_OK : self::STATUS_INFO,
			'message' => $gutenberg_active ? 'active' : 'not active',
		);

		// Bricks.
		$bricks_active = Bricks::is_active();
		$checks[]      = array(
			'label'   => 'Bricks',
			'status'  => $bricks_active ? self::STATUS_OK : self::STATUS_INFO,
			'message' => $bricks_active ? 'active' : 'not active (theme not detected)',
		);

		return $checks;
	}

	/**
	 * Check recent activity log for errors.
	 *
	 * @return array
	 */
	private function check_recent_activity(): array {
		$log_path = Plugin::get_dynamic_css_dir() . '/activity.log';
		$checks   = array();

		if ( ! file_exists( $log_path ) ) {
			$checks[] = array(
				'label'   => 'Activity log',
				'status'  => self::STATUS_INFO,
				'message' => 'not found',
			);
			return $checks;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = file_get_contents( $log_path );

		if ( empty( $content ) ) {
			$checks[] = array(
				'label'   => 'Activity log',
				'status'  => self::STATUS_INFO,
				'message' => 'empty',
			);
			return $checks;
		}

		// Parse recent events.
		$events        = preg_split( '/(?=^\{)/m', $content, -1, PREG_SPLIT_NO_EMPTY );
		$recent_events = array_slice( $events, -100 );
		$total_events  = count( $events );

		$errors   = 0;
		$warnings = 0;
		foreach ( $recent_events as $event_json ) {
			$event = json_decode( $event_json, true );
			if ( ! $event || ! isset( $event['level'] ) ) {
				continue;
			}
			if ( 'error' === $event['level'] ) {
				++$errors;
			} elseif ( 'warning' === $event['level'] ) {
				++$warnings;
			}
		}

		// Recent errors check.
		if ( $errors > 0 ) {
			$checks[] = array(
				'label'   => 'Recent errors',
				'status'  => self::STATUS_WARNING,
				'message' => sprintf( '%d error(s) in last 100 events', $errors ),
			);
		} else {
			$checks[] = array(
				'label'   => 'Recent errors',
				'status'  => self::STATUS_OK,
				'message' => 'none',
			);
		}

		// Total events.
		$checks[] = array(
			'label'   => 'Total events logged',
			'status'  => self::STATUS_INFO,
			'message' => (string) $total_events,
		);

		return $checks;
	}

	/**
	 * Get human-readable time difference.
	 *
	 * @param int $timestamp The timestamp to compare.
	 * @return string
	 */
	private function human_time_diff( int $timestamp ): string {
		$diff = time() - $timestamp;

		if ( $diff < HOUR_IN_SECONDS ) {
			$mins = (int) round( $diff / MINUTE_IN_SECONDS );
			return $mins <= 1 ? 'just now' : sprintf( '%d minutes ago', $mins );
		}

		if ( $diff < DAY_IN_SECONDS ) {
			$hours = (int) round( $diff / HOUR_IN_SECONDS );
			return 1 === $hours ? '1 hour ago' : sprintf( '%d hours ago', $hours );
		}

		$days = (int) round( $diff / DAY_IN_SECONDS );
		return 1 === $days ? '1 day ago' : sprintf( '%d days ago', $days );
	}

	/**
	 * Output results as formatted text.
	 *
	 * @param array $results The check results.
	 * @return void
	 */
	private function output_text( array $results ): void {
		$total_ok      = 0;
		$total_warning = 0;
		$total_error   = 0;

		WP_CLI::log( WP_CLI::colorize( '%GAutomatic.css Doctor%n' ) );
		WP_CLI::log( '====================' );
		WP_CLI::log( '' );

		$section_titles = array(
			'environment'   => 'Environment',
			'filesystem'    => 'File System',
			'plugin'        => 'Plugin Health',
			'configuration' => 'Configuration',
			'css'           => 'CSS Files',
			'integrations'  => 'Integrations',
			'activity'      => 'Recent Activity',
		);

		foreach ( $results as $section => $checks ) {
			$title = $section_titles[ $section ] ?? ucfirst( $section );
			WP_CLI::log( WP_CLI::colorize( '%G' . $title . '%n' ) );

			foreach ( $checks as $check ) {
				$icon = $this->get_status_icon( $check['status'] );
				WP_CLI::log( sprintf( '  %s %s: %s', $icon, $check['label'], $check['message'] ) );

				// Count totals.
				switch ( $check['status'] ) {
					case self::STATUS_OK:
						++$total_ok;
						break;
					case self::STATUS_WARNING:
						++$total_warning;
						break;
					case self::STATUS_ERROR:
						++$total_error;
						break;
				}
			}

			WP_CLI::log( '' );
		}

		// Summary.
		$summary_color = '%G'; // Green by default.
		if ( $total_error > 0 ) {
			$summary_color = '%R'; // Red.
		} elseif ( $total_warning > 0 ) {
			$summary_color = '%Y'; // Yellow.
		}

		$summary_text = sprintf( 'Summary: %d passed, %d warnings, %d errors', $total_ok, $total_warning, $total_error );
		WP_CLI::log( WP_CLI::colorize( $summary_color . $summary_text . '%n' ) );
	}

	/**
	 * Get status icon for text output.
	 *
	 * @param string $status The status.
	 * @return string
	 */
	private function get_status_icon( string $status ): string {
		switch ( $status ) {
			case self::STATUS_OK:
				return WP_CLI::colorize( '%G✓%n' );
			case self::STATUS_WARNING:
				return WP_CLI::colorize( '%Y⚠%n' );
			case self::STATUS_ERROR:
				return WP_CLI::colorize( '%R✗%n' );
			case self::STATUS_INFO:
			default:
				return WP_CLI::colorize( '%Bℹ%n' );
		}
	}
}
