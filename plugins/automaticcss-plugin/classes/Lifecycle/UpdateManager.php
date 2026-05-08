<?php
/**
 * Automatic.css Update Manager class file.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Lifecycle;

use Automatic_CSS\Exceptions\CSS_Generation_Failed;
use Automatic_CSS\Exceptions\Insufficient_Permissions;
use Automatic_CSS\Exceptions\Invalid_Form_Values;
use Automatic_CSS\Helpers\Logger;
use Automatic_CSS\Helpers\OptionsContextInterface;
use Closure;

/**
 * Manages plugin version updates and database migrations.
 *
 * This class handles checking for version differences between the plugin
 * and database, acquiring locks to prevent concurrent updates, and
 * triggering migration hooks.
 *
 * @since 4.0.0
 */
class UpdateManager {

	/**
	 * Option name for locking the plugin during the database upgrade process.
	 *
	 * @var string
	 */
	public const ACSS_DATABASE_UPGRADE_LOCK_OPTION = 'automaticcss_database_upgrade_lock';

	/**
	 * Option name for the plugin's database version.
	 *
	 * @var string
	 */
	public const ACSS_DB_VERSION = 'automatic_css_db_version';

	/**
	 * Lock expiration time in seconds (10 minutes).
	 *
	 * @var int
	 */
	private const LOCK_EXPIRATION = 600;

	/**
	 * Options context for database operations.
	 *
	 * @var OptionsContextInterface
	 */
	private OptionsContextInterface $options;

	/**
	 * The current plugin version.
	 *
	 * @var string
	 */
	private string $plugin_version;

	/**
	 * Function to fire WordPress hooks with version parameters.
	 * Using a callable to enable testing without WordPress.
	 *
	 * @var Closure(string, string, string): void
	 */
	private Closure $do_action;

	/**
	 * Constructor.
	 *
	 * @param OptionsContextInterface                    $options        Options context for database operations.
	 * @param string                                     $plugin_version The current plugin version.
	 * @param Closure(string, string, string): void|null $do_action    Function to fire hooks (defaults to do_action).
	 */
	public function __construct(
		OptionsContextInterface $options,
		string $plugin_version,
		?Closure $do_action = null
	) {
		$this->options        = $options;
		$this->plugin_version = $plugin_version;
		$this->do_action      = $do_action ?? fn( string $hook, string $pv, string $dv ) => do_action( $hook, $pv, $dv );
	}

	/**
	 * Handle the plugin's update if current version and last db saved version don't match.
	 *
	 * All the hooks in the upgrader_* family are not suitable because they will run the code
	 * from before the update was carried over while the files and directories have been updated.
	 * That means if your upgrader_* hook calls a function/method/namespace that is no longer
	 * present in the new code, that's going to cause a fatal error.
	 *
	 * @return void
	 */
	public function maybe_update(): void {
		// Check if the plugin is locked during the database upgrade process.
		$lock = $this->options->get_transient( self::ACSS_DATABASE_UPGRADE_LOCK_OPTION );
		Logger::log( sprintf( '%s: starting with lock = "%s"', __METHOD__, $lock ) );
		if ( 'yes' === $lock ) {
			// We're already running the upgrade process.
			Logger::log( sprintf( '%s: upgrade process already running, skipping', __METHOD__ ) );
			return;
		}
		// Set the lock.
		$this->options->set_transient( self::ACSS_DATABASE_UPGRADE_LOCK_OPTION, 'yes', self::LOCK_EXPIRATION );
		// Run the updates.
		$db_version = $this->options->get_option( self::ACSS_DB_VERSION );
		if ( false === $db_version ) {
			// This is a new installation or someone deleted the option.
			Logger::log( sprintf( '%s: new installation or option deleted, skipping updates.', __METHOD__ ) );
		} elseif ( $this->plugin_version !== $db_version ) {
			Logger::log(
				sprintf(
					'%s: db_version (%s) differs from plugin_version (%s) => running updates.',
					__METHOD__,
					$db_version,
					$this->plugin_version
				)
			);
			try {
				// Run updates.
				( $this->do_action )( 'automaticcss_update_plugin_start', $this->plugin_version, $db_version );
				// Possibly other update tasks...
				( $this->do_action )( 'automaticcss_update_plugin_end', $this->plugin_version, $db_version );
				Logger::log( sprintf( '%s: plugin update done', __METHOD__ ) );
			} catch ( Insufficient_Permissions | Invalid_Form_Values $e ) {
				Logger::log( sprintf( '%s: error while running updates: %s', __METHOD__, $e->getMessage() ) );
				$this->show_admin_error( 'An issue occurred while updating the plugin: ' . $e->getMessage() );
				// Remove the lock.
				$this->options->delete_transient( self::ACSS_DATABASE_UPGRADE_LOCK_OPTION );
				return;
			} catch ( CSS_Generation_Failed $e ) {
				// CSS generation can fail during migration (e.g., due to incomplete test data).
				// The migration itself succeeded; CSS can be regenerated from the dashboard.
				Logger::log( sprintf( '%s: CSS generation failed during update: %s', __METHOD__, $e->getMessage() ) );
			}
		}
		// Update the db_version.
		$this->options->update_option( self::ACSS_DB_VERSION, $this->plugin_version );
		Logger::log( sprintf( '%s: db version set to %s', __METHOD__, $this->plugin_version ) );
		// Remove the lock.
		$this->options->delete_transient( self::ACSS_DATABASE_UPGRADE_LOCK_OPTION );
		Logger::log( sprintf( '%s: lock removed', __METHOD__ ) );
		Logger::log( sprintf( '%s: done', __METHOD__ ) );
	}

	/**
	 * Handle the plugin's update through WP's auto updates.
	 *
	 * @param array<string, array<object>> $results The results of all attempted updates.
	 * @return void
	 */
	public function maybe_autoupdate( array $results ): void {
		if ( ! isset( $results['plugin'] ) || ! is_array( $results['plugin'] ) ) {
			return;
		}
		foreach ( $results['plugin'] as $plugin ) {
			if ( ! empty( $plugin->item->slug ) && 'automaticcss-plugin' === $plugin->item->slug ) {
				Logger::log( sprintf( '%s: the plugin was updated through automatic_updates_complete, triggering maybe_update', __METHOD__ ) );
				$this->maybe_update();
				return;
			}
		}
	}

	/**
	 * Show an admin error notice.
	 *
	 * @param string $message The error message to display.
	 * @return void
	 */
	private function show_admin_error( string $message ): void {
		add_action(
			'admin_notices',
			function () use ( $message ) {
				Logger::log( 'admin_notices action' );
				$class = 'notice notice-error';
				$full_message = '[Automatic.css] ' . $message;
				printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $full_message ) );
			}
		);
	}
}
