<?php
/**
 * Migration interface for ACSS settings upgrades.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Migrations;

/**
 * Interface for version-specific migrations.
 *
 * Each migration handles upgrading settings from one version to another.
 * Migrations are executed in order by the MigrationRunner.
 */
interface MigrationInterface {

	/**
	 * Get the target version for this migration.
	 *
	 * This is the version that triggers the migration when upgrading TO it.
	 * For example, Migration_2_4_0 returns '2.4.0' and runs when upgrading
	 * from any version < 2.4.0 to any version >= 2.4.0.
	 *
	 * @return string Semantic version string (e.g., '2.4.0', '3.0.0').
	 */
	public function get_version(): string;

	/**
	 * Run the migration on the given settings values.
	 *
	 * @param array<string, mixed> $values The current settings values.
	 * @return array<string, mixed> The migrated settings values.
	 */
	public function run( array $values ): array;

	/**
	 * Get a human-readable description of what this migration does.
	 *
	 * Used for logging and debugging purposes.
	 *
	 * @return string Description of the migration.
	 */
	public function get_description(): string;
}
