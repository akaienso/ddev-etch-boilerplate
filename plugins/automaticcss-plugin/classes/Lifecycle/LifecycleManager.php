<?php
/**
 * Automatic.css Lifecycle Manager class file.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Lifecycle;

use Automatic_CSS\Helpers\Logger;
use Automatic_CSS\Helpers\OptionsContextInterface;
use Automatic_CSS\Model\SettingsRepositoryInterface;
use Closure;

/**
 * Manages plugin lifecycle events: activation, deactivation, and data deletion.
 *
 * @since 4.0.0
 */
class LifecycleManager {

	/**
	 * Option name for locking the plugin during the deletion process.
	 *
	 * @var string
	 */
	public const ACSS_DATABASE_DELETE_LOCK_OPTION = 'automaticcss_database_delete_lock';

	/**
	 * Options context for database operations.
	 *
	 * @var OptionsContextInterface
	 */
	private OptionsContextInterface $options;

	/**
	 * Factory function to get the settings repository.
	 * Using a factory to avoid circular dependencies during initialization.
	 *
	 * @var Closure(): SettingsRepositoryInterface
	 */
	private Closure $settings_factory;

	/**
	 * Function to fire WordPress hooks.
	 * Using a callable to enable testing without WordPress.
	 *
	 * @var Closure(string): void
	 */
	private Closure $do_action;

	/**
	 * Constructor.
	 *
	 * @param OptionsContextInterface $options          Options context for database operations.
	 * @param Closure                 $settings_factory Factory to get settings repository.
	 * @param Closure|null            $do_action        Function to fire hooks (defaults to do_action).
	 */
	public function __construct(
		OptionsContextInterface $options,
		Closure $settings_factory,
		?Closure $do_action = null
	) {
		$this->options          = $options;
		$this->settings_factory = $settings_factory;
		$this->do_action        = $do_action ?? fn( string $hook ) => do_action( $hook );
	}

	/**
	 * Handle the plugin's activation.
	 *
	 * @return void
	 */
	public function activate(): void {
		try {
			( $this->do_action )( 'automaticcss_activate_plugin_start' );
			// Possibly other activation tasks...
			( $this->do_action )( 'automaticcss_activate_plugin_end' );
		} catch ( \Exception $e ) {
			Logger::log( sprintf( '%s: error while activating the plugin: %s', __METHOD__, $e->getMessage() ) );
			$this->show_admin_error( 'An issue occurred while activating the plugin: ' . $e->getMessage() );
		}
	}

	/**
	 * Handle plugin's deactivation by (maybe) cleaning up after ourselves.
	 *
	 * @return void
	 */
	public function deactivate(): void {
		( $this->do_action )( 'automaticcss_deactivate_plugin_start' );
		$settings = ( $this->settings_factory )();
		$vars     = $settings->get_vars();
		$delete   = is_array( $vars ) && array_key_exists( 'delete-on-deactivation', $vars )
			? strtolower( trim( $vars['delete-on-deactivation'] ) )
			: 'no';
		if ( 'yes' === $delete ) {
			$this->delete_plugin_data();
		}
		( $this->do_action )( 'automaticcss_deactivate_plugin_end' );
	}

	/**
	 * Delete plugin's data.
	 *
	 * @return void
	 */
	public function delete_plugin_data(): void {
		// Check the lock.
		$lock = $this->options->get_option( self::ACSS_DATABASE_DELETE_LOCK_OPTION, false );
		Logger::log( sprintf( '%s: starting with lock = %b', __METHOD__, $lock ) );
		if ( $lock ) {
			// We're already running the delete process.
			Logger::log( sprintf( '%s: delete process already running, skipping', __METHOD__ ) );
			return;
		}
		// Set the lock.
		$this->options->update_option( self::ACSS_DATABASE_DELETE_LOCK_OPTION, true );
		// Delete the data.
		( $this->do_action )( 'automaticcss_delete_plugin_data_start' );
		// Possibly other deletion tasks...
		( $this->do_action )( 'automaticcss_delete_plugin_data_end' );
		$this->options->delete_option( 'automatic_css_db_version' );
		$this->options->delete_option( 'automaticcss_database_upgrade_lock' );
		// Remove the lock.
		$this->options->delete_option( self::ACSS_DATABASE_DELETE_LOCK_OPTION );
		Logger::log( sprintf( '%s: done', __METHOD__ ) );
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
