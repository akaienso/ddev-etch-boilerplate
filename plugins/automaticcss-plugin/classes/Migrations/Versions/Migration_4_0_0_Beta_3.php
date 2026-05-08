<?php
/**
 * Migration for ACSS 4.0.0-beta-3.
 *
 * @package Automatic_CSS\Migrations\Versions
 */

namespace Automatic_CSS\Migrations\Versions;

use Automatic_CSS\Helpers\Logger;
use Automatic_CSS\Migrations\MigrationInterface;

/**
 * Migration_4_0_0_Beta_3 class.
 *
 * Converts the concentric radius toggle (on/off) to the new select (off/concentric/reverse).
 */
class Migration_4_0_0_Beta_3 implements MigrationInterface {

	/**
	 * Get the target version for this migration.
	 *
	 * @return string
	 */
	public function get_version(): string {
		return '4.0.0-beta-3';
	}

	/**
	 * Get a human-readable description of what this migration does.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Convert concentric radius toggle to select (on -> standard)';
	}

	/**
	 * Run the migration on the given settings values.
	 *
	 * @param array<string, mixed> $values The current settings values.
	 * @return array<string, mixed> The migrated settings values.
	 */
	public function run( array $values ): array {
		// New install (no DB values set) - nothing to do.
		if ( empty( $values ) ) {
			Logger::log( sprintf( '%s: new install detected, skipping migration', __METHOD__ ) );
			return $values;
		}

		// Migrate concentric radius: "on" -> "standard", "off" stays "off".
		if ( isset( $values['option-card-concentric-radius'] ) && 'on' === $values['option-card-concentric-radius'] ) {
			$values['option-card-concentric-radius'] = 'standard';
			Logger::log( sprintf( '%s: migrated option-card-concentric-radius from "on" to "standard"', __METHOD__ ) );
		}

		return $values;
	}
}
