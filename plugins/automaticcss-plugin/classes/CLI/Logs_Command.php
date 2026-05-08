<?php
/**
 * Automatic.css Logs CLI Command.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\CLI;

use Automatic_CSS\Plugin;
use WP_CLI;
use WP_CLI\Utils;

/**
 * Manage Automatic.css logs.
 *
 * ## EXAMPLES
 *
 *     # Show recent activity log entries
 *     $ wp acss logs tail
 *
 *     # Show last 50 lines of activity log
 *     $ wp acss logs tail --lines=50
 *
 *     # Clear the activity log
 *     $ wp acss logs clear --yes
 */
class Logs_Command {

	/**
	 * Activity log filename.
	 */
	private const ACTIVITY_LOG = 'activity.log';

	/**
	 * Debug log filename.
	 */
	private const DEBUG_LOG = 'debug.log';

	/**
	 * Show recent log entries.
	 *
	 * ## OPTIONS
	 *
	 * [--events=<number>]
	 * : Number of events to show (for activity log). Default: 20.
	 *
	 * [--lines=<number>]
	 * : Number of lines to show (for debug log). Default: 100.
	 *
	 * [--type=<type>]
	 * : Log type to show. Options: activity, debug. Default: activity.
	 *
	 * [--follow]
	 * : Watch the log file for new entries (like tail -f). Press Ctrl+C to stop.
	 *
	 * ## EXAMPLES
	 *
	 *     # Show last 20 events from activity log
	 *     $ wp acss logs tail
	 *
	 *     # Show last 50 events
	 *     $ wp acss logs tail --events=50
	 *
	 *     # Show debug log (line-based)
	 *     $ wp acss logs tail --type=debug
	 *
	 *     # Show last 50 lines of debug log
	 *     $ wp acss logs tail --type=debug --lines=50
	 *
	 *     # Watch for new entries
	 *     $ wp acss logs tail --follow
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function tail( $args, $assoc_args ): void {
		$type   = Utils\get_flag_value( $assoc_args, 'type', 'activity' );
		$follow = Utils\get_flag_value( $assoc_args, 'follow', false );

		$log_file = $this->get_log_path( $type );

		if ( ! file_exists( $log_file ) ) {
			WP_CLI::error( sprintf( 'Log file does not exist: %s', $log_file ) );
		}

		if ( $follow ) {
			$this->follow_log( $log_file );
			return;
		}

		if ( 'activity' === $type ) {
			$events  = (int) Utils\get_flag_value( $assoc_args, 'events', 20 );
			$content = $this->tail_events( $log_file, $events );
		} else {
			$lines   = (int) Utils\get_flag_value( $assoc_args, 'lines', 100 );
			$content = $this->tail_lines( $log_file, $lines );
		}

		if ( empty( $content ) ) {
			WP_CLI::log( 'Log file is empty.' );
			return;
		}

		WP_CLI::log( $content );
	}

	/**
	 * Clear log files.
	 *
	 * ## OPTIONS
	 *
	 * [--type=<type>]
	 * : Log type to clear. Options: activity, debug, all. Default: activity.
	 *
	 * [--yes]
	 * : Skip confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     # Clear activity log
	 *     $ wp acss logs clear --yes
	 *
	 *     # Clear debug log
	 *     $ wp acss logs clear --type=debug --yes
	 *
	 *     # Clear all logs
	 *     $ wp acss logs clear --type=all --yes
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function clear( $args, $assoc_args ): void {
		$type = Utils\get_flag_value( $assoc_args, 'type', 'activity' );

		$types_to_clear = array();
		if ( 'all' === $type ) {
			$types_to_clear = array( 'activity', 'debug' );
		} else {
			$types_to_clear = array( $type );
		}

		// Build confirmation message.
		$files_to_clear = array();
		foreach ( $types_to_clear as $log_type ) {
			$path = $this->get_log_path( $log_type );
			if ( file_exists( $path ) ) {
				$files_to_clear[ $log_type ] = $path;
			}
		}

		if ( empty( $files_to_clear ) ) {
			WP_CLI::warning( 'No log files found to clear.' );
			return;
		}

		$file_list = implode( ', ', array_keys( $files_to_clear ) );
		WP_CLI::confirm( sprintf( 'Clear %s log(s)?', $file_list ), $assoc_args );

		$cleared = 0;
		foreach ( $files_to_clear as $log_type => $path ) {
			if ( $this->clear_log_file( $path, $log_type ) ) {
				$cleared++;
			}
		}

		if ( $cleared > 0 ) {
			WP_CLI::success( sprintf( 'Cleared %d log file(s).', $cleared ) );
		}
	}

	/**
	 * Show log file paths and sizes.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format. Options: table, json, yaml. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     # Show log file info
	 *     $ wp acss logs path
	 *
	 *     # Get paths as JSON
	 *     $ wp acss logs path --format=json
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function path( $args, $assoc_args ): void {
		$format = Utils\get_flag_value( $assoc_args, 'format', 'table' );

		$logs = array(
			array(
				'type'     => 'activity',
				'path'     => $this->get_log_path( 'activity' ),
				'exists'   => file_exists( $this->get_log_path( 'activity' ) ),
				'size'     => file_exists( $this->get_log_path( 'activity' ) )
					? filesize( $this->get_log_path( 'activity' ) )
					: 0,
				'size_fmt' => file_exists( $this->get_log_path( 'activity' ) )
					? size_format( filesize( $this->get_log_path( 'activity' ) ) )
					: '-',
			),
			array(
				'type'     => 'debug',
				'path'     => $this->get_log_path( 'debug' ),
				'exists'   => file_exists( $this->get_log_path( 'debug' ) ),
				'size'     => file_exists( $this->get_log_path( 'debug' ) )
					? filesize( $this->get_log_path( 'debug' ) )
					: 0,
				'size_fmt' => file_exists( $this->get_log_path( 'debug' ) )
					? size_format( filesize( $this->get_log_path( 'debug' ) ) )
					: '-',
			),
		);

		if ( 'json' === $format ) {
			WP_CLI::log( wp_json_encode( $logs, JSON_PRETTY_PRINT ) );
			return;
		}

		if ( 'yaml' === $format ) {
			WP_CLI::print_value( $logs, array( 'format' => 'yaml' ) );
			return;
		}

		// Table format.
		$items = array();
		foreach ( $logs as $log ) {
			$items[] = array(
				'Type'   => $log['type'],
				'Path'   => $log['path'],
				'Exists' => $log['exists'] ? 'yes' : 'no',
				'Size'   => $log['size_fmt'],
			);
		}

		Utils\format_items( 'table', $items, array( 'Type', 'Path', 'Exists', 'Size' ) );
	}

	/**
	 * Get the path to a log file.
	 *
	 * @param string $type Log type (activity or debug).
	 * @return string
	 */
	private function get_log_path( $type ): string {
		$css_dir = Plugin::get_dynamic_css_dir();

		switch ( $type ) {
			case 'debug':
				return $css_dir . '/' . self::DEBUG_LOG;
			case 'activity':
			default:
				return $css_dir . '/' . self::ACTIVITY_LOG;
		}
	}

	/**
	 * Get the last N lines of a file (for debug log).
	 *
	 * @param string $file_path Path to the file.
	 * @param int    $lines     Number of lines to return.
	 * @return string
	 */
	private function tail_lines( $file_path, $lines ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content    = file_get_contents( $file_path );
		$all_lines  = explode( "\n", $content );
		$tail_lines = array_slice( $all_lines, -$lines );

		return implode( "\n", $tail_lines );
	}

	/**
	 * Get the last N events from the activity log.
	 *
	 * Activity log contains JSON objects that may span multiple lines.
	 * Each event starts with a '{' at the beginning of a line.
	 *
	 * @param string $file_path Path to the file.
	 * @param int    $count     Number of events to return.
	 * @return string
	 */
	private function tail_events( $file_path, $count ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = file_get_contents( $file_path );

		if ( empty( $content ) ) {
			return '';
		}

		// Split by lines that start with '{' to identify event boundaries.
		// Each JSON event in the activity log starts with '{' on its own line.
		$events = preg_split( '/(?=^\{)/m', $content, -1, PREG_SPLIT_NO_EMPTY );

		if ( empty( $events ) ) {
			return '';
		}

		// Get the last N events.
		$tail_events = array_slice( $events, -$count );

		return implode( '', $tail_events );
	}

	/**
	 * Follow a log file for new entries.
	 *
	 * @param string $file_path Path to the file.
	 * @return void
	 */
	private function follow_log( $file_path ): void {
		WP_CLI::log( sprintf( 'Following %s (press Ctrl+C to stop)...', $file_path ) );
		WP_CLI::log( '' );

		$last_size = filesize( $file_path );

		// phpcs:ignore Generic.CodeAnalysis.UnconditionalIfStatement.Found -- Intentional infinite loop for tail -f behavior.
		while ( true ) {
			clearstatcache( false, $file_path );
			$current_size = filesize( $file_path );

			if ( $current_size > $last_size ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
				$handle = fopen( $file_path, 'r' );
				if ( $handle ) {
					fseek( $handle, $last_size );
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
					$new_content = fread( $handle, $current_size - $last_size );
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
					fclose( $handle );

					if ( $new_content ) {
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
						echo $new_content;
					}
				}
				$last_size = $current_size;
			} elseif ( $current_size < $last_size ) {
				// File was truncated/rotated.
				WP_CLI::log( '--- Log file rotated ---' );
				$last_size = $current_size;
			}

			// Sleep for 500ms.
			usleep( 500000 );
		}
	}

	/**
	 * Clear a log file.
	 *
	 * @param string $path     Path to the log file.
	 * @param string $log_type Type of log for messaging.
	 * @return bool True on success.
	 */
	private function clear_log_file( $path, $log_type ): bool {
		// Also clear rotated files for activity log.
		if ( 'activity' === $log_type ) {
			for ( $i = 1; $i <= 3; $i++ ) {
				$rotated = $path . '.' . $i;
				if ( file_exists( $rotated ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
					unlink( $rotated );
				}
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$result = file_put_contents( $path, '' );

		if ( false === $result ) {
			WP_CLI::warning( sprintf( 'Failed to clear %s log.', $log_type ) );
			return false;
		}

		WP_CLI::log( sprintf( 'Cleared %s log: %s', $log_type, $path ) );
		return true;
	}
}
