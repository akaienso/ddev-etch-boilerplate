<?php
/**
 * Migration for ACSS 2.2.0.2.
 *
 * @package Automatic_CSS\Migrations\Versions
 */

namespace Automatic_CSS\Migrations\Versions;

use Automatic_CSS\Helpers\Color;
use Automatic_CSS\Helpers\Logger;
use Automatic_CSS\Migrations\MigrationInterface;

/**
 * Migration_2_2_0 class.
 *
 * Adds new shade hue variables based on existing color values.
 */
class Migration_2_2_0 implements MigrationInterface {

	/**
	 * Get the target version for this migration.
	 *
	 * @return string
	 */
	public function get_version(): string {
		return '2.2.0.3'; // Runs for versions > 2.2.0.2.
	}

	/**
	 * Get a human-readable description of what this migration does.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Add new shade hue variables for color variations';
	}

	/**
	 * Run the migration on the given settings values.
	 *
	 * @param array<string, mixed> $values The current settings values.
	 * @return array<string, mixed> The migrated settings values.
	 */
	public function run( array $values ): array {
		$color_types      = array( 'action', 'primary', 'secondary', 'base', 'accent', 'shade' );
		$color_variations = array( 'hover', 'ultra-light', 'light', 'medium', 'dark', 'ultra-dark' );
		foreach ( $color_types as $color_type ) {
			foreach ( $color_variations as $color_variation ) {
				$hue_key   = $color_type . '-' . $color_variation . '-h';
				$color_key = 'color-' . $color_type;
				if ( ! array_key_exists( $hue_key, $values ) && array_key_exists( $color_key, $values ) ) {
					$hue_value          = ( new Color( $values[ $color_key ] ) )->h;
					$values[ $hue_key ] = $hue_value;
					Logger::log( sprintf( '%s: adding %s with value %s', __METHOD__, $hue_key, $hue_value ) );
				}
			}
		}
		return $values;
	}
}
