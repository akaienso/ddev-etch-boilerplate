<?php
/**
 * Migration interface for Etch database upgrades.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Migrations;

/**
 * Interface for version-specific migrations.
 *
 * Each migration handles upgrading data from one version to another.
 * Migrations are self-contained: they determine what data they need
 * to read and write (settings, post content, post meta, etc.).
 *
 * Migrations are executed in order by the MigrationRunner.
 */
interface MigrationInterface {

	/**
	 * Get the target version for this migration.
	 *
	 * This is the version that triggers the migration when upgrading TO it.
	 * For example, Migration_1_1_0 returns '1.1.0' and runs when upgrading
	 * from any version < 1.1.0 to any version >= 1.1.0.
	 *
	 * @return string Semantic version string (e.g., '1.1.0', '2.0.0').
	 */
	public function get_version(): string;

	/**
	 * Run the migration.
	 *
	 * Each migration is responsible for:
	 * - Determining what data needs to be migrated
	 * - Reading that data (settings, posts, meta, etc.)
	 * - Transforming the data as needed
	 * - Writing the updated data back
	 *
	 * @return void
	 */
	public function run(): void;

	/**
	 * Get a human-readable description of what this migration does.
	 *
	 * Used for logging and debugging purposes.
	 *
	 * @return string Description of the migration.
	 */
	public function get_description(): string;
}
