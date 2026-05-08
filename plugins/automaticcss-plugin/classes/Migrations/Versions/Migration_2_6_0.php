<?php
/**
 * Migration for ACSS 2.6.0.
 *
 * @package Automatic_CSS\Migrations\Versions
 */

namespace Automatic_CSS\Migrations\Versions;

use Automatic_CSS\Helpers\Logger;
use Automatic_CSS\Migrations\MigrationInterface;

/**
 * Migration_2_6_0 class.
 *
 * Adds new tab inactive text color settings with defaults.
 */
class Migration_2_6_0 implements MigrationInterface {

	/**
	 * Get the target version for this migration.
	 *
	 * @return string
	 */
	public function get_version(): string {
		return '2.6.0';
	}

	/**
	 * Get a human-readable description of what this migration does.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Add new tab inactive text color settings with defaults';
	}

	/**
	 * Run the migration on the given settings values.
	 *
	 * @param array<string, mixed> $values The current settings values.
	 * @return array<string, mixed> The migrated settings values.
	 */
	public function run( array $values ): array {
		$new_settings = array(
			// setting name => default value.
			'f-light-tab-inactive-text-color' => 'var(--shade-dark-trans-80)',
			'f-dark-tab-inactive-text-color'  => 'var(--shade-light-trans-80)',
		);
		foreach ( $new_settings as $setting_name => $default_value ) {
			if ( ! array_key_exists( $setting_name, $values ) ) {
				Logger::log( sprintf( '%s: adding %s with default value %s', __METHOD__, $setting_name, $default_value ) );
				$values[ $setting_name ] = $default_value;
			}
		}
		return $values;
	}
}
