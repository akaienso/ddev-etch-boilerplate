<?php
/**
 * ObfuscationHelper file.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Helpers;

/**
 * Handles obfuscation of sensitive string values for safe display.
 *
 * Shows a prefix and suffix of the original value with masked characters
 * in between, so users can identify the value without exposing it fully.
 */
final class ObfuscationHelper {

	/**
	 * Obfuscate a string value for safe display.
	 *
	 * @param string $value The value to obfuscate.
	 * @return string The obfuscated value.
	 */
	public static function obfuscate( string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		if ( strlen( $value ) <= 8 ) {
			return str_repeat( '•', strlen( $value ) );
		}

		$prefix = substr( $value, 0, 4 );
		$suffix = substr( $value, -4 );

		return $prefix . '****' . $suffix;
	}

	/**
	 * Check if a value matches the obfuscated form of a given original.
	 *
	 * @param string $value The value to check.
	 * @param string $original The original plaintext to compare against.
	 * @return bool True if value is the obfuscated form of original.
	 */
	public static function is_obfuscated( string $value, string $original ): bool {
		return self::obfuscate( $original ) === $value;
	}
}
