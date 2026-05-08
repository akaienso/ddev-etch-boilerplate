<?php
/**
 * Migration for ACSS 3.0.8.
 *
 * @package Automatic_CSS\Migrations\Versions
 */

namespace Automatic_CSS\Migrations\Versions;

use Automatic_CSS\Helpers\Logger;
use Automatic_CSS\Migrations\MigrationInterface;

/**
 * Migration_3_0_8 class.
 *
 * Turns off certain settings that were inadvertently turned on when we
 * started setting default values for null settings.
 */
class Migration_3_0_8 implements MigrationInterface {

	/**
	 * Get the target version for this migration.
	 *
	 * @return string
	 */
	public function get_version(): string {
		return '3.0.8';
	}

	/**
	 * Get a human-readable description of what this migration does.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Turn off option-auto-radius if it was null';
	}

	/**
	 * Run the migration on the given settings values.
	 *
	 * @param array<string, mixed> $values The current settings values.
	 * @return array<string, mixed> The migrated settings values.
	 */
	public function run( array $values ): array {
		$settings_to_turn_off = array(
			'option-auto-radius',
		);
		foreach ( $settings_to_turn_off as $setting ) {
			if ( array_key_exists( $setting, $values ) && null === $values[ $setting ] ) {
				Logger::log( sprintf( '%s: turning off %s', __METHOD__, $setting ) );
				$values[ $setting ] = 'off';
			}
		}
		return $values;
	}
}
