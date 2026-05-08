<?php
/**
 * Automatic.css Logger file.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Helpers;

/**
 * Automatic.css Logger class.
 */
class Logger {

	const LOG_LEVEL_NOW = 0;
	const LOG_LEVEL_ERROR = 1;
	const LOG_LEVEL_WARNING = 2;
	const LOG_LEVEL_NOTICE = 3;
	const LOG_LEVEL_INFO = 4;

	/**
	 * The logger configuration.
	 *
	 * @var LoggerConfig|null
	 */
	private static $config = null;

	/**
	 * Set the logger configuration.
	 *
	 * @param LoggerConfig $config The logger configuration.
	 * @return void
	 */
	public static function set_config( LoggerConfig $config ): void {
		self::$config = $config;
	}

	/**
	 * Log a message at the now level.
	 *
	 * @param mixed $what The message to log.
	 * @return bool
	 */
	public static function now( $what ): bool {
		return self::log( $what, self::LOG_LEVEL_NOW );
	}

	/**
	 * Log a message at the error level.
	 *
	 * @param mixed $what The message to log.
	 * @return bool
	 */
	public static function error( $what ): bool {
		return self::log( $what, self::LOG_LEVEL_ERROR )
			&& self::log( "Backtrace:\n" . print_r( debug_backtrace(), true ), self::LOG_LEVEL_ERROR );
	}

	/**
	 * Log a message at the warning level.
	 *
	 * @param mixed $what The message to log.
	 * @return bool
	 */
	public static function warning( $what ): bool {
		return self::log( $what, self::LOG_LEVEL_WARNING );
	}

	/**
	 * Log a message at the notice level.
	 *
	 * @param mixed $what The message to log.
	 * @return bool
	 */
	public static function notice( $what ): bool {
		return self::log( $what, self::LOG_LEVEL_NOTICE );
	}

	/**
	 * Log a message at the info level.
	 *
	 * @param mixed $what The message to log.
	 * @return bool
	 */
	public static function info( $what ): bool {
		return self::log( $what, self::LOG_LEVEL_INFO );
	}

	/**
	 * Log a message to the debug file.
	 *
	 * @param mixed $what The message to log.
	 * @param int   $log_level The log level.
	 * @param bool  $bypass_should_log Whether to bypass the should log check.
	 * @return bool
	 */
	public static function log( $what, int $log_level = self::LOG_LEVEL_NOTICE, $bypass_should_log = false ): bool {

		// STEP: check if we should log the message.
		if ( false === $bypass_should_log && ! self::should_log( $log_level ) ) {
			return false;
		}

		$debug_file_path = self::$config->debug_file_path;
		$message = print_r( $what, true ) . "\n";
		$ret = file_put_contents( $debug_file_path, $message, FILE_APPEND );
		// STEP: return the result.
		return false !== $ret;
	}

	/**
	 * Check if the logger should log the message.
	 *
	 * @param int $log_level The log level.
	 * @return bool
	 */
	private static function should_log( int $log_level ): bool {
		if ( ! self::$config ) {
			return false;
		}
		if ( ! self::$config->enabled ) {
			return false;
		}
		if ( ! isset( self::$config->enabled_log_levels[ $log_level ] ) ) {
			return false;
		}
		if ( false === (bool) self::$config->enabled_log_levels[ $log_level ] ) {
			return false;
		}
		if ( ! self::can_append_to_debug_file() ) {
			return false;
		}
		return true;
	}

	/**
	 * Check if the logger can append to the debug file.
	 *
	 * @return bool
	 */
	private static function can_append_to_debug_file(): bool {
		$debug_dir = self::$config->debug_file_dir;
		$debug_file = self::$config->debug_file_path;
		// STEP: check if the directory exists. Create it if it doesn't.
		if ( ! file_exists( $debug_dir ) && ! wp_mkdir_p( $debug_dir ) ) {
			return false;
		}
		// STEP: check if the debug file is writable.
		if ( ! is_writable( $debug_dir ) ) {
			return false;
		}
		// STEP: check if the debug file exists and is writable.
		if ( file_exists( $debug_file ) && ! is_writable( $debug_file ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Log the header.
	 *
	 * @return void
	 */
	public static function log_header(): void {
		if ( ! self::$config ) {
			return;
		}
		self::log(
			sprintf(
				'[%s] Plugin version %s - requested by %s',
				gmdate( 'd-M-Y H:i:s' ),
				self::$config->plugin_version,
				self::get_redacted_uri()
			),
			self::LOG_LEVEL_NOW,
			true
		);
	}

	/**
	 * Get the redacted URI.
	 *
	 * @return string
	 */
	public static function get_redacted_uri(): string {
		// STEP: ensure $_SERVER['REQUEST_URI'] is a valid string.
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? filter_var( wp_unslash( $_SERVER['REQUEST_URI'] ), FILTER_SANITIZE_URL ) : '';
		if ( ! is_string( $uri ) ) {
			return '';
		}
		// STEP: define parameters to redact and create the regex pattern.
		$params_to_redact = array( 'username', 'user', 'password', 'pass', 'nonce' );
		$params_to_redact_regex = implode( '|', $params_to_redact );
		// STEP: perform the regex replacement.
		$redacted_uri = preg_replace(
			'/(\?|&)(' . $params_to_redact_regex . ')=([^&]+)/i',
			'$1$2=[redacted]',
			$uri
		);
		// STEP: Return the result, ensuring it's a string.
		return is_string( $redacted_uri ) ? $redacted_uri : '';
	}
}
