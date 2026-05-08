<?php
/**
 * Migration for ACSS 2.0.0.
 *
 * @package Automatic_CSS\Migrations\Versions
 */

namespace Automatic_CSS\Migrations\Versions;

use Automatic_CSS\Helpers\Logger;
use Automatic_CSS\Migrations\MigrationInterface;

/**
 * Migration_2_0_0 class.
 *
 * Handles pre-2.0 to 2.0 upgrades:
 * - Renames section-padding-x to section-padding-x-max
 * - Renames color variation -val suffix to -l suffix
 * - Converts text size overrides from REM to PX
 */
class Migration_2_0_0 implements MigrationInterface {

	/**
	 * Get the target version for this migration.
	 *
	 * @return string
	 */
	public function get_version(): string {
		return '2.0.0';
	}

	/**
	 * Get a human-readable description of what this migration does.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Convert section padding, color variation suffixes, and text sizes from REM to PX';
	}

	/**
	 * Run the migration on the given settings values.
	 *
	 * @param array<string, mixed> $values The current settings values.
	 * @return array<string, mixed> The migrated settings values.
	 */
	public function run( array $values ): array {
		// Handle section-padding-x -> section-padding-x-max conversion.
		if ( array_key_exists( 'section-padding-x', $values ) ) {
			Logger::log( sprintf( '%s: converting section-padding-x to section-padding-x-max', __METHOD__ ) );
			$values['section-padding-x-max'] = $values['section-padding-x'];
			unset( $values['section-padding-x'] );
		}

		// Handle primary-hover-val -> primary-hover-l conversion.
		$color_types      = array( 'action', 'primary', 'secondary', 'base', 'accent', 'shade' );
		$color_variations = array( 'hover', 'ultra-light', 'light', 'medium', 'dark', 'ultra-dark' );
		foreach ( $color_types as $color_type ) {
			foreach ( $color_variations as $color_variation ) {
				$old_var = $color_type . '-' . $color_variation . '-val';
				if ( array_key_exists( $old_var, $values ) ) {
					$new_var = $color_type . '-' . $color_variation . '-l';
					Logger::log(
						sprintf(
							'%s: converting %s to %s with value %s',
							__METHOD__,
							$old_var,
							$new_var,
							$values[ $old_var ]
						)
					);
					$values[ $new_var ] = $values[ $old_var ];
					unset( $values[ $old_var ] );
				}
			}
		}

		// Handle text overrides REM -> px conversion.
		$text_size_variations         = array( 'xs', 's', 'm', 'l', 'xl', 'xxl' );
		$text_size_min_max_variations = array( 'min', 'max' );
		$root_font_size               = array_key_exists( 'root-font-size', $values ) ? floatval( $values['root-font-size'] ) : 62.5;
		foreach ( $text_size_variations as $text_size_variation ) {
			foreach ( $text_size_min_max_variations as $min_max_variation ) {
				$text_size_var = 'text-' . $text_size_variation . '-' . $min_max_variation;
				// When these values were converted from REM to PX, they were divided by 10 and then adjusted for root-font-size.
				// So: new value = old value * 10 * root-font-size / 62.5.
				if ( array_key_exists( $text_size_var, $values ) && '' !== $values[ $text_size_var ] ) { // accept 0 though.
					$text_size_old_value = $values[ $text_size_var ];
					$text_size_new_value = $text_size_old_value * 10 * $root_font_size / 62.5;
					Logger::log( sprintf( '%s: converting %s from %s to %s', __METHOD__, $text_size_var, $text_size_old_value, $text_size_new_value ) );
					$values[ $text_size_var ] = $text_size_new_value;
				}
			}
		}

		return $values;
	}
}
