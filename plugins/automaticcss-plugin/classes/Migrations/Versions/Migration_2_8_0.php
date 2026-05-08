<?php
/**
 * Migration for ACSS 2.8.0.
 *
 * @package Automatic_CSS\Migrations\Versions
 */

namespace Automatic_CSS\Migrations\Versions;

use Automatic_CSS\Helpers\Logger;
use Automatic_CSS\Migrations\MigrationInterface;

/**
 * Migration_2_8_0 class.
 *
 * Renames frame/background/text settings to simplified naming.
 */
class Migration_2_8_0 implements MigrationInterface {

	/**
	 * Get the target version for this migration.
	 *
	 * @return string
	 */
	public function get_version(): string {
		return '2.8.0';
	}

	/**
	 * Get a human-readable description of what this migration does.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Rename frame background/text settings (fr-bg-light → bg-ultra-light, etc.)';
	}

	/**
	 * Run the migration on the given settings values.
	 *
	 * @param array<string, mixed> $values The current settings values.
	 * @return array<string, mixed> The migrated settings values.
	 */
	public function run( array $values ): array {
		$settings_to_migrate = array(
			'fr-bg-light'   => 'bg-ultra-light',
			'fr-bg-dark'    => 'bg-ultra-dark',
			'fr-text-light' => 'text-light',
			'fr-text-dark'  => 'text-dark',
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
