<?php
/**
 * Migration for ACSS 2.4.0.
 *
 * @package Automatic_CSS\Migrations\Versions
 */

namespace Automatic_CSS\Migrations\Versions;

use Automatic_CSS\Helpers\Logger;
use Automatic_CSS\Migrations\MigrationInterface;

/**
 * Migration_2_4_0 class.
 *
 * Sets breakpoint-xl from vp-max if empty.
 */
class Migration_2_4_0 implements MigrationInterface {

	/**
	 * Get the target version for this migration.
	 *
	 * @return string
	 */
	public function get_version(): string {
		return '2.4.0';
	}

	/**
	 * Get a human-readable description of what this migration does.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Set breakpoint-xl from vp-max if empty';
	}

	/**
	 * Run the migration on the given settings values.
	 *
	 * @param array<string, mixed> $values The current settings values.
	 * @return array<string, mixed> The migrated settings values.
	 */
	public function run( array $values ): array {
		if ( ( ! array_key_exists( 'breakpoint-xl', $values ) || '' === $values['breakpoint-xl'] ) && array_key_exists( 'vp-max', $values ) ) {
			Logger::log( sprintf( '%s: breakpoint-xl is now a variable, taking the value of vp-max', __METHOD__ ) );
			$values['breakpoint-xl'] = $values['vp-max'];
		}
		return $values;
	}
}
