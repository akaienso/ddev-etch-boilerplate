<?php
/**
 * Migration runner for Etch database upgrades.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Migrations;

use Etch\Helpers\Logger;
use Etch\Helpers\WideEventLogger;
use Exception;

/**
 * Orchestrates the execution of version migrations.
 *
 * The runner maintains a registry of migration classes, determines which
 * migrations need to run based on version comparison, and executes them
 * in sequence.
 */
class MigrationRunner {

	/**
	 * Registry of migration class names in version order.
	 *
	 * @var array<string>
	 */
	private array $migrations = array();

	/**
	 * Constructor.
	 *
	 * @param array<string> $migrations Optional array of migration class names.
	 */
	public function __construct( array $migrations = array() ) {
		$this->migrations = $migrations;
	}

	/**
	 * Register a migration class.
	 *
	 * @param string $migration_class Fully qualified class name implementing MigrationInterface.
	 * @return self
	 */
	public function register( string $migration_class ): self {
		$this->migrations[] = $migration_class;
		return $this;
	}

	/**
	 * Run all applicable migrations for a version upgrade.
	 *
	 * Migrations are run if:
	 * - previous_version < migration_version
	 * - current_version >= migration_version
	 *
	 * Each migration is self-contained and handles its own data access.
	 *
	 * @param string $current_version  The version being upgraded to.
	 * @param string $previous_version The version being upgraded from.
	 * @return void
	 * @throws Exception If a migration fails.
	 */
	public function run( string $current_version, string $previous_version ): void {
		Logger::log( sprintf( '%s: starting migration from %s to %s', __METHOD__, $previous_version, $current_version ) );

		$migrations_run = array();

		foreach ( $this->migrations as $migration_class ) {
			$migration = new $migration_class();

			if ( ! $migration instanceof MigrationInterface ) {
				Logger::log( sprintf( '%s: skipping %s - does not implement MigrationInterface', __METHOD__, $migration_class ) );
				continue;
			}

			$migration_version = $migration->get_version();

			// Run migration if upgrading across this version boundary.
			if ( $this->should_run_migration( $migration_version, $current_version, $previous_version ) ) {
				Logger::log( sprintf( '%s: running migration %s - %s', __METHOD__, $migration_version, $migration->get_description() ) );

				WideEventLogger::append( 'migration.versions', $migration_version );

				try {
					$migration->run();
					$migrations_run[] = $migration_version;
				} catch ( Exception $e ) {
					$error_message = sprintf( 'Migration %s failed: %s', $migration_version, $e->getMessage() );
					Logger::log( sprintf( '%s: %s', __METHOD__, $error_message ), Logger::LOG_LEVEL_ERROR );
					WideEventLogger::failure(
						'migration',
						$error_message,
						array( 'version' => $migration_version )
					);
					throw $e; // Re-throw to stop the upgrade process.
				}
			}
		}

		if ( ! empty( $migrations_run ) ) {
			WideEventLogger::set( 'migration.from_version', $previous_version );
			WideEventLogger::set( 'migration.to_version', $current_version );
			WideEventLogger::set( 'migration.count', count( $migrations_run ) );
		}

		Logger::log( sprintf( '%s: completed %d migrations', __METHOD__, count( $migrations_run ) ) );
	}

	/**
	 * Determine if a migration should run for the given version transition.
	 *
	 * @param string $migration_version The version this migration targets.
	 * @param string $current_version   The version being upgraded to.
	 * @param string $previous_version  The version being upgraded from.
	 * @return bool True if the migration should run.
	 */
	private function should_run_migration( string $migration_version, string $current_version, string $previous_version ): bool {
		// Migration runs if: previous < migration_version AND current >= migration_version.
		return version_compare( $previous_version, $migration_version, '<' )
			&& version_compare( $current_version, $migration_version, '>=' );
	}

	/**
	 * Get all registered migration class names.
	 *
	 * @return array<string>
	 */
	public function get_migrations(): array {
		return $this->migrations;
	}
}
