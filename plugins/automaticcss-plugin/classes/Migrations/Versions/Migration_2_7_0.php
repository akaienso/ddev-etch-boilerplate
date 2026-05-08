<?php
/**
 * Migration for ACSS 2.7.0.
 *
 * @package Automatic_CSS\Migrations\Versions
 */

namespace Automatic_CSS\Migrations\Versions;

use Automatic_CSS\Helpers\Logger;
use Automatic_CSS\Migrations\MigrationInterface;

/**
 * Migration_2_7_0 class.
 *
 * Renames button-related settings to use more consistent naming.
 */
class Migration_2_7_0 implements MigrationInterface {

	/**
	 * Get the target version for this migration.
	 *
	 * @return string
	 */
	public function get_version(): string {
		return '2.7.0';
	}

	/**
	 * Get a human-readable description of what this migration does.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Rename button settings to consistent naming (btn-weight → btn-font-weight, etc.)';
	}

	/**
	 * Run the migration on the given settings values.
	 *
	 * @param array<string, mixed> $values The current settings values.
	 * @return array<string, mixed> The migrated settings values.
	 */
	public function run( array $values ): array {
		$settings_to_migrate = array(
			'btn-weight'            => 'btn-font-weight',
			'btn-text-style'        => 'btn-font-style',
			'btn-width'             => 'btn-min-width',
			'btn-pad-y'             => 'btn-padding-block',
			'btn-pad-x'             => 'btn-padding-inline',
			'btn-border-size'       => 'btn-border-width',
			'outline-btn-border-size' => 'btn-outline-border-width',
			'btn-radius'            => 'btn-border-radius',
		);
		foreach ( $settings_to_migrate as $old_var_name => $new_var_name ) {
			if ( array_key_exists( $old_var_name, $values ) ) {
				Logger::log( sprintf( '%s: converting %s to %s', __METHOD__, $old_var_name, $new_var_name ) );
				$values[ $new_var_name ] = $values[ $old_var_name ];
				unset( $values[ $old_var_name ] );
			}
		}
		return $values;
	}
}
