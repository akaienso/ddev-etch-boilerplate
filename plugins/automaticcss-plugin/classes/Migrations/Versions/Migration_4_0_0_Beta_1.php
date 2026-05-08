<?php
/**
 * Migration for ACSS 4.0.0-beta-1.
 *
 * @package Automatic_CSS\Migrations\Versions
 */

namespace Automatic_CSS\Migrations\Versions;

use Automatic_CSS\Helpers\Logger;
use Automatic_CSS\Migrations\MigrationInterface;

/**
 * Migration_4_0_0_Beta_1 class.
 *
 * Sets default values for unified lightness dashboard options on existing installs.
 */
class Migration_4_0_0_Beta_1 implements MigrationInterface {

	/**
	 * Get the target version for this migration.
	 *
	 * @return string
	 */
	public function get_version(): string {
		return '4.0.0-beta-1';
	}

	/**
	 * Get a human-readable description of what this migration does.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Set unified lightness options to off for existing installs';
	}

	/**
	 * Run the migration on the given settings values.
	 *
	 * For existing installs, sets option-palette-unify-brand-lightness and
	 * option-palette-unify-status-lightness to 'off' unless they already exist.
	 * New installs (empty values) are left unchanged.
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

		$options_to_set = array(
			'option-palette-unify-brand-lightness',
			'option-palette-unify-status-lightness',
		);

		foreach ( $options_to_set as $option ) {
			if ( ! array_key_exists( $option, $values ) ) {
				$values[ $option ] = 'off';
				Logger::log( sprintf( '%s: setting %s to off', __METHOD__, $option ) );
			} else {
				Logger::log( sprintf( '%s: %s already exists with value %s, skipping', __METHOD__, $option, $values[ $option ] ) );
			}
		}

		return $values;
	}
}
