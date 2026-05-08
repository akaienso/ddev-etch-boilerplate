<?php
/**
 * Migration for ACSS 4.0.0-beta-4.
 *
 * @package Automatic_CSS\Migrations\Versions
 */

namespace Automatic_CSS\Migrations\Versions;

use Automatic_CSS\Helpers\Logger;
use Automatic_CSS\Migrations\MigrationInterface;

/**
 * Migration_4_0_0_Beta_4 class.
 *
 * Migrates box shadow names from m/l/xl to 1/2/3 and converts
 * easing presets from fixed-name settings to the nameable slot format.
 */
class Migration_4_0_0_Beta_4 implements MigrationInterface {

	/**
	 * Setting key => old default name that should be migrated.
	 *
	 * @var array<string, string>
	 */
	private const BOX_SHADOW_RENAMES = array(
		'box-shadow-1-name' => 'm',
		'box-shadow-2-name' => 'l',
		'box-shadow-3-name' => 'xl',
	);

	/**
	 * Old easing setting key => [slot number, default name].
	 *
	 * @var array<string, array{int, string}>
	 */
	private const EASING_MAP = array(
		'ease-smooth' => array( 1, 'smooth' ),
		'ease-snappy' => array( 2, 'snappy' ),
		'ease-gentle' => array( 3, 'gentle' ),
		'ease-bouncy' => array( 4, 'bouncy' ),
	);

	/**
	 * Get the target version for this migration.
	 *
	 * @return string
	 */
	public function get_version(): string {
		return '4.0.0-beta-4';
	}

	/**
	 * Get a human-readable description of what this migration does.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Migrate box shadow names (m/l/xl to 1/2/3) and easing presets to nameable slots';
	}

	/**
	 * Run the migration on the given settings values.
	 *
	 * @param array<string, mixed> $values The current settings values.
	 * @return array<string, mixed> The migrated settings values.
	 */
	public function run( array $values ): array {
		if ( empty( $values ) ) {
			Logger::log( sprintf( '%s: new install detected, skipping migration', __METHOD__ ) );
			return $values;
		}

		$values = $this->migrate_box_shadows( $values );
		$values = $this->migrate_easing_presets( $values );

		return $values;
	}

	/**
	 * Migrate box shadow names from m/l/xl to 1/2/3.
	 *
	 * @param array<string, mixed> $values The settings values.
	 * @return array<string, mixed> The migrated values.
	 */
	private function migrate_box_shadows( array $values ): array {
		$slot = 1;
		foreach ( self::BOX_SHADOW_RENAMES as $key => $old_name ) {
			if ( isset( $values[ $key ] ) && $values[ $key ] === $old_name ) {
				$values[ $key ] = (string) $slot;
				Logger::log( sprintf( '%s: migrated %s from "%s" to "%s"', __METHOD__, $key, $old_name, $slot ) );
			}
			++$slot;
		}
		return $values;
	}

	/**
	 * Migrate easing presets from fixed-name keys to nameable slots.
	 *
	 * Old format: ease-smooth = "cubic-bezier(0.4, 0, 0.2, 1)"
	 * New format: ease-1-name = "smooth", ease-1-value = "cubic-bezier(0.4, 0, 0.2, 1)"
	 *
	 * @param array<string, mixed> $values The settings values.
	 * @return array<string, mixed> The migrated values.
	 */
	private function migrate_easing_presets( array $values ): array {
		foreach ( self::EASING_MAP as $old_key => $mapping ) {
			list( $slot, $name ) = $mapping;
			$name_key  = "ease-{$slot}-name";
			$value_key = "ease-{$slot}-value";

			if ( isset( $values[ $old_key ] ) ) {
				$values[ $name_key ]  = $name;
				$values[ $value_key ] = $values[ $old_key ];
				unset( $values[ $old_key ] );
				Logger::log( sprintf( '%s: migrated %s to %s + %s', __METHOD__, $old_key, $name_key, $value_key ) );
			}
		}
		return $values;
	}
}
