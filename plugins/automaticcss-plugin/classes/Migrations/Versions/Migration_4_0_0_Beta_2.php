<?php
/**
 * Migration for ACSS 4.0.0-beta-2.
 *
 * @package Automatic_CSS\Migrations\Versions
 */

namespace Automatic_CSS\Migrations\Versions;

use Automatic_CSS\Helpers\Logger;
use Automatic_CSS\Migrations\MigrationInterface;

/**
 * Migration_4_0_0_Beta_2 class.
 *
 * Sets ratio breakpoint to 992px for existing installs upgrading from named breakpoints.
 */
class Migration_4_0_0_Beta_2 implements MigrationInterface {

	/**
	 * Get the target version for this migration.
	 *
	 * @return string
	 */
	public function get_version(): string {
		return '4.0.0-beta-2';
	}

	/**
	 * Get a human-readable description of what this migration does.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Set ratio breakpoint to 992px for existing installs';
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

		// Set ratio breakpoint to 992px for existing installs.
		$values['auto-staggered-grid-breakpoint'] = 992;
		Logger::log( sprintf( '%s: set auto-staggered-grid-breakpoint to 992', __METHOD__ ) );

		return $values;
	}
}
