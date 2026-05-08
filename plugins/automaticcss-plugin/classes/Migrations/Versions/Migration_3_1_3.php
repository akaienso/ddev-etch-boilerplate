<?php
/**
 * Migration for ACSS 3.1.3.
 *
 * @package Automatic_CSS\Migrations\Versions
 */

namespace Automatic_CSS\Migrations\Versions;

use Automatic_CSS\Helpers\Logger;
use Automatic_CSS\Migrations\MigrationInterface;

/**
 * Migration_3_1_3 class.
 *
 * Sets background link/button settings to empty for existing installs.
 * These settings were introduced in 3.1.0 but should have had empty values
 * for existing installs. This migration catches up users who haven't yet
 * received these settings.
 */
class Migration_3_1_3 implements MigrationInterface {

	/**
	 * Get the target version for this migration.
	 *
	 * @return string
	 */
	public function get_version(): string {
		return '3.1.3';
	}

	/**
	 * Get a human-readable description of what this migration does.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Set bg-*-link, bg-*-link-hover, and bg-*-button settings to empty for existing installs';
	}

	/**
	 * Run the migration on the given settings values.
	 *
	 * @param array<string, mixed> $values The current settings values.
	 * @return array<string, mixed> The migrated settings values.
	 */
	public function run( array $values ): array {
		$settings_to_empty = array(
			'bg-ultra-light-link',
			'bg-ultra-light-link-hover',
			'bg-ultra-light-button',
			'bg-light-link',
			'bg-light-link-hover',
			'bg-light-button',
			'bg-dark-link',
			'bg-dark-link-hover',
			'bg-dark-button',
			'bg-ultra-dark-link',
			'bg-ultra-dark-link-hover',
			'bg-ultra-dark-button',
		);
		foreach ( $settings_to_empty as $setting ) {
			if ( ! array_key_exists( $setting, $values ) ) {
				Logger::log( sprintf( '%s: setting %s to empty', __METHOD__, $setting ) );
				$values[ $setting ] = '';
			}
		}
		return $values;
	}
}
