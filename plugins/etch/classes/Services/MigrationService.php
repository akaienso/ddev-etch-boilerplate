<?php
/**
 * Migration service for Etch.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Services;

use Etch\Helpers\Logger;
use Etch\Migrations\MigrationRunner;
use Etch\Migrations\Versions;
use Etch\Traits\Singleton;

/**
 * Service for managing database migrations.
 *
 * Handles migration registration, execution, and integration with
 * the plugin's update lifecycle.
 */
class MigrationService {

	use Singleton;

	/**
	 * The migration runner instance.
	 *
	 * @var MigrationRunner|null
	 */
	private ?MigrationRunner $migration_runner = null;

	/**
	 * Initialize the migration service.
	 *
	 * @return self The current instance of the class.
	 */
	public function init(): self {
		// Initialize the migration runner with registered migrations.
		$this->migration_runner = $this->create_migration_runner();
		// Handle plugin updates.
		add_action( 'etch/lifecycle/update_start', array( $this, 'handle_plugin_update' ), 10, 2 );
		return $this;
	}

	/**
	 * Handle plugin update by running database migrations.
	 *
	 * Called when the plugin version changes. Runs all applicable migrations
	 * for the version transition.
	 *
	 * @param string $current_version  The version being upgraded to.
	 * @param string $previous_version The version being upgraded from.
	 * @return void
	 */
	public function handle_plugin_update( string $current_version, string $previous_version ): void {
		Logger::log( sprintf( '%s: handling update from %s to %s', __METHOD__, $previous_version, $current_version ) );

		// Run migrations via the migration runner.
		if ( null === $this->migration_runner ) {
			$this->migration_runner = $this->create_migration_runner();
		}

		$this->migration_runner->run( $current_version, $previous_version );
	}

	/**
	 * Create and configure the migration runner.
	 *
	 * Register migrations in version order. Each migration class handles
	 * its own data access (settings, posts, meta, etc.).
	 *
	 * @return MigrationRunner
	 */
	private function create_migration_runner(): MigrationRunner {
		$runner = new MigrationRunner();

		// Register migrations in version order.
		$runner->register( Versions\Migration_1_0_0_Rc_2::class );
		$runner->register( Versions\Migration_1_2_0::class );

		return $runner;
	}

	/**
	 * Get the migration runner instance.
	 *
	 * @return MigrationRunner|null
	 */
	public function get_migration_runner(): ?MigrationRunner {
		return $this->migration_runner;
	}

	/**
	 * Set the migration runner instance.
	 *
	 * Useful for testing with a custom runner.
	 *
	 * @param MigrationRunner $runner The runner to use.
	 * @return void
	 */
	public function set_migration_runner( MigrationRunner $runner ): void {
		$this->migration_runner = $runner;
	}
}
