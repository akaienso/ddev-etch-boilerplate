<?php
/**
 * Automatic.css Flag file.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Helpers;

use Automatic_CSS\Plugin;
use DigitalGravy\FeatureFlag\FeatureFlagStore;
use DigitalGravy\FeatureFlag\Exception\Flag_Key_Not_Found;
use DigitalGravy\FeatureFlag\Storage\Exception\FileNotFoundException;
use DigitalGravy\FeatureFlag\Storage\JsonFile;

/**
 * Automatic.css Flag class.
 */
class Flag {

	/**
	 * The flag store.
	 *
	 * @var FeatureFlagStore
	 */
	private static $flag_store;

	/**
	 * Flags that were ignored during initialization.
	 *
	 * @var array<string, array>
	 */
	private static array $ignored_flags = array();

	/**
	 * Initialize the flag store.
	 *
	 * @return void
	 * @throws FlagsNotInMainFileException If the flags are not in the main file.
	 */
	public static function init() {
		$error_message = '';
		// flags.json contains the feature flags that users will receive.
		$flags_prod = ( new JsonFile( ACSS_PLUGIN_DIR . 'config/flags.json' ) )->get_flags();
		try {
			$flags_dev = ( new JsonFile( ACSS_PLUGIN_DIR . 'config/flags.dev.json' ) )->get_flags();
			// flags.dev.json contains the feature flags that we use locally, and can override flags in flags.json.
		} catch ( FileNotFoundException $e ) {
			$flags_dev = array(); // This file shouldn't exist on production, so catch the exception.
		}
		try {
			// flags.user.json allows the user to override any flag via a JSON file in the uploads directory.
			$flags_user = ( new JsonFile( Plugin::get_dynamic_css_dir() . '/flags.user.json' ) )->get_flags();
		} catch ( FileNotFoundException $e ) {
			$flags_user = array(); // It's ok if this file doesn't exist.
		}
		// Make sure $flags_dev doesn't contain any flags that are already in $flags_prod.
		$flags_dev_not_in_prod = array_diff_key( $flags_dev, $flags_prod );
		if ( ! empty( $flags_dev_not_in_prod ) ) {
			$error_message .=
				sprintf(
					"%s: The following flags.dev.json flags will be ignored because they are not in flags.json:\n%s\n",
					__METHOD__,
					esc_html( implode( "\n", array_keys( $flags_dev_not_in_prod ) ) )
				);
			self::$ignored_flags['dev'] = array_keys( $flags_dev_not_in_prod );
		}
		$flags_dev = array_diff_key( $flags_dev, $flags_dev_not_in_prod );
		// Make sure $flags_user doesn't contain any flags that are already in $flags_prod.
		$flags_user_not_in_prod = array_diff_key( $flags_user, $flags_prod );
		if ( ! empty( $flags_user_not_in_prod ) ) {
			$error_message .=
				sprintf(
					"%s: The following flags.user.json flags will be ignored because they are not in flags.json:\n%s\n",
					__METHOD__,
					esc_html( implode( "\n", array_keys( $flags_user_not_in_prod ) ) )
				);
			self::$ignored_flags['user'] = array_keys( $flags_user_not_in_prod );
		}
		$flags_user = array_diff_key( $flags_user, $flags_user_not_in_prod );
		// All flags are merged into a single store.
		$flag_store = new FeatureFlagStore( $flags_prod, $flags_dev, $flags_user );
		self::$flag_store = $flag_store;
		// This would have been a simple Logger::log() call, but Logger isn't available yet. This is a workaround.
		if ( ! empty( $error_message ) ) {
			throw new FlagsNotInMainFileException( esc_html( $error_message ) );
		}
	}

	/**
	 * Check if the flag store has been initialized.
	 *
	 * @return bool
	 */
	public static function is_initialized(): bool {
		return null !== self::$flag_store;
	}

	/**
	 * Checks if a development flag is on.
	 * A flag is on when the constant is defined and its value is true.
	 *
	 * @param string $flag_name The name of the flag to check.
	 * @return boolean
	 */
	public static function is_on( $flag_name ) {
		try {
			// Use the new flag store.
			return self::$flag_store->is_on( $flag_name );
		} catch ( Flag_Key_Not_Found $e ) {
			// Fallback to the old method.
			// TODO: remove this fallback once we're sure all flags are using the new flag store.
			if ( defined( $flag_name ) ) {
				return (bool) constant( $flag_name );
			}
			if ( defined( 'ACSS_FLAG_' . $flag_name ) ) {
				// Extra fallback as we ditched the ACSS_FLAG_ prefix.
				return (bool) constant( 'ACSS_FLAG_' . $flag_name );
			}
			return false;
		}
	}

	/**
	 * Get all flags.
	 *
	 * @return array<string, string>
	 */
	public static function get_flags(): array {
		return self::$flag_store->get_flags();
	}

	/**
	 * Get flags that were ignored during initialization.
	 *
	 * @return array<string, array>
	 */
	public static function get_ignored_flags(): array {
		return self::$ignored_flags;
	}
}
