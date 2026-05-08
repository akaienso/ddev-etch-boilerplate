<?php
/**
 * Migration for ACSS 4.0.0-alpha-1.
 *
 * @package Automatic_CSS\Migrations\Versions
 */

namespace Automatic_CSS\Migrations\Versions;

use Automatic_CSS\Helpers\Color;
use Automatic_CSS\Helpers\Logger;
use Automatic_CSS\Migrations\MigrationInterface;
use Automatic_CSS\PHPColors\Color as PHPColor;

/**
 * Migration_4_0_0_Alpha_1 class.
 *
 * Converts color settings from HSL to OKLCH color space.
 */
class Migration_4_0_0_Alpha_1 implements MigrationInterface {

	/**
	 * Get the target version for this migration.
	 *
	 * @return string
	 */
	public function get_version(): string {
		return '4.0.0-alpha-1';
	}

	/**
	 * Get a human-readable description of what this migration does.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Convert color settings from HSL to OKLCH color space';
	}

	/**
	 * Run the migration on the given settings values.
	 *
	 * @param array<string, mixed> $values The current settings values.
	 * @return array<string, mixed> The migrated settings values.
	 */
	public function run( array $values ): array {
		$color_types      = array( 'primary', 'secondary', 'tertiary', 'base', 'accent', 'neutral', 'success', 'info', 'warning', 'danger' );
		$color_variations = array( 'hover', 'ultra-light', 'semi-light', 'light', 'medium', 'dark', 'semi-dark', 'ultra-dark' );

		foreach ( $color_types as $color_type ) {
			// Convert base color.
			Logger::log( sprintf( '%s: start color conversion to oklch', $color_type ) );
			$color_key = 'color-' . $color_type;
			if ( array_key_exists( $color_key, $values ) ) {
				$color_value = $values[ $color_key ];
				$oklch       = new Color( $color_value );
				$values[ $color_type . '-l-oklch' ] = $oklch->l_oklch;
				$values[ $color_type . '-c-oklch' ] = $oklch->c_oklch;
				$values[ $color_type . '-h-oklch' ] = $oklch->h_oklch;

				Logger::log( sprintf( '%s: converted %s to oklch: l=%s, c=%s, h=%s', __METHOD__, $color_key, $oklch->l_oklch, $oklch->c_oklch, $oklch->h_oklch ) );
			}

			// Convert shade variations.
			foreach ( $color_variations as $color_variation ) {
				$shade_key_h = $color_type . '-' . $color_variation . '-h';
				$shade_key_s = $color_type . '-' . $color_variation . '-s';
				$shade_key_l = $color_type . '-' . $color_variation . '-l';

				// Get raw values with defaults.
				$h = isset( $values[ $shade_key_h ] ) ? (float) $values[ $shade_key_h ] : 0;
				$s = isset( $values[ $shade_key_s ] ) ? (float) $values[ $shade_key_s ] : 0;
				$l = isset( $values[ $shade_key_l ] ) ? (float) $values[ $shade_key_l ] : 0;

				// Clamp values to valid ranges to prevent invalid hex generation.
				// H: normalize to 0-360 (hue wraps around).
				// S: clamp to 0-100 (will be divided by 100 below).
				// L: clamp to 0-100 (will be divided by 100 below).
				$h = fmod( fmod( $h, 360 ) + 360, 360 ); // Normalize negative values too.
				$s = max( 0, min( 100, $s ) );
				$l = max( 0, min( 100, $l ) );

				$oklch = PHPColor::hslToOklch(
					array(
						'H' => $h,
						'S' => $s / 100,
						'L' => $l / 100,
					)
				);

				$values[ $color_type . '-' . $color_variation . '-l-oklch' ] = $oklch['L'];
				$values[ $color_type . '-' . $color_variation . '-c-oklch' ] = $oklch['C'];
				$values[ $color_type . '-' . $color_variation . '-h-oklch' ] = $oklch['H'];

				Logger::log( sprintf( '%s: converted %s %s %s to oklch: l=%s, c=%s, h=%s', __METHOD__, $shade_key_h, $shade_key_s, $shade_key_l, $oklch['L'], $oklch['C'], $oklch['H'] ) );
			}
		}

		return $values;
	}
}
