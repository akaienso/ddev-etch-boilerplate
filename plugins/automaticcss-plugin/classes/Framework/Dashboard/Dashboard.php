<?php
/**
 * Dashboard class
 *
 * @package Automatic_CSS\Framework\Dashboard
 */

namespace Automatic_CSS\Framework\Dashboard;

use Automatic_CSS\Exceptions\CSS_Generation_Failed;
use Automatic_CSS\Exceptions\Invalid_Form_Values;
use Automatic_CSS\Framework\AssetManager\DefaultAssetEnqueuing;
use Automatic_CSS\Framework\AssetManager\HasAssetsToEnqueueInterface;
use Automatic_CSS\Framework\ContextManager\Context;
use Automatic_CSS\Helpers\Flag;
use Automatic_CSS\Helpers\JS_File;
use Automatic_CSS\Helpers\Locale;
use Automatic_CSS\Helpers\Logger;
use Automatic_CSS\Helpers\Permissions;
use Automatic_CSS\Helpers\Timer;
use Automatic_CSS\Helpers\WideEventLogger;
use Automatic_CSS\Model\Config\UI;
use Automatic_CSS\Model\Database_Settings;
use Automatic_CSS\Plugin;

/**
 * Dashboard class
 */
class Dashboard implements HasAssetsToEnqueueInterface {

	use DefaultAssetEnqueuing; // Adds enqueue_assets() method.

	/**
	 * Dashboard JS file
	 *
	 * @var JS_File
	 */
	private $dashboard_js_file;

	/**
	 * Hot reload JS file
	 *
	 * @var JS_File
	 */
	private $hot_reload_js_file;

	/**
	 * Permissions
	 *
	 * @var Permissions
	 */
	private $permissions;

	/**
	 * Constructor
	 *
	 * @param Permissions $permissions Permissions.
	 * @param JS_File     $dashboard_js_file Dashboard JS file.
	 * @param JS_File     $hot_reload_js_file Hot reload JS file.
	 */
	public function __construct( $permissions, $dashboard_js_file = null, $hot_reload_js_file = null ) {
		$this->permissions = $permissions;
		$path = '/Framework/Dashboard/js';
		$load_from_vite = Flag::is_on( 'LOAD_DASHBOARD_FROM_VITE' ) ?? false;
		$load_in_footer = Flag::is_on( 'LOAD_DASHBOARD_SCRIPTS_IN_FOOTER' ) ?? false;
		$this->dashboard_js_file = $dashboard_js_file ?? self::setup_dashboard_js_file( $path, $load_from_vite, $load_in_footer );
		$this->hot_reload_js_file = $hot_reload_js_file ?? self::setup_hot_reload_js_file( $path, $load_in_footer );
		add_filter( 'script_loader_tag', array( $this, 'add_type_attribute' ), 10, 3 );
		add_action( 'wp_ajax_automaticcss_save_settings_new', array( $this, 'save_settings' ) );
	}

	/**
	 * Setup the dashboard JS file.
	 *
	 * @param string $path The path to the dashboard JS file.
	 * @param bool   $load_from_vite Whether to load the dashboard from Vite.
	 * @param bool   $load_in_footer Whether to load the dashboard in the footer.
	 * @return JS_File
	 */
	private static function setup_dashboard_js_file( $path, $load_from_vite, $load_in_footer ) {
		$filename = 'dashboard.min.js';
		$dashboard_file_url = $load_from_vite
			? 'http://localhost:5173/features/Dashboard/main.js'
			: ACSS_CLASSES_URL . "{$path}/{$filename}";
		$dashboard_file_path = $load_from_vite
			? null
			: ACSS_CLASSES_DIR . "{$path}/{$filename}";
		return new JS_File(
			'dashboard',
			$dashboard_file_url,
			$dashboard_file_path,
			array(),
			$load_in_footer
		);
	}

	/**
	 * Setup the hot reload JS file.
	 *
	 * @param string $path The path to the hot reload JS file.
	 * @param bool   $load_in_footer Whether to load the hot reload in the footer.
	 * @return JS_File
	 */
	private static function setup_hot_reload_js_file( $path, $load_in_footer ) {
		$filename = 'acss-hot-reload.js';
		$file_url = ACSS_CLASSES_URL . "{$path}/{$filename}";
		$file_path = ACSS_CLASSES_DIR . "{$path}/{$filename}";
		return new JS_File(
			'acss-hot-reload',
			$file_url,
			$file_path,
			array(),
			$load_in_footer
		);
	}

	/**
	 * Enqueue the assets
	 *
	 * @param Context $context The context in which the assets are being enqueued.
	 */
	public function get_assets_to_enqueue( Context $context ): array {
		if ( ! $this->permissions->has_acss_access() ) {
			return array();
		}
		switch ( $context->get_context() ) {
			case Context::PREVIEW:
				return array(
					$this->hot_reload_js_file,
				);
			case Context::BUILDER:
				$this->localize_dashboard( $context );
				return array(
					$this->dashboard_js_file,
					$this->hot_reload_js_file,
				);
			case Context::FRONTEND:
				$this->localize_dashboard( $context );
				return array(
					$this->dashboard_js_file,
				);
			default:
				return array();
		}
	}

	/**
	 * Enqueue the dashboard.
	 *
	 * @param Context $context The context in which the dashboard is being enqueued.
	 */
	private function localize_dashboard( $context ) {
		$context_name = $context->get_context();
		$builder = $this->get_builder_name( $context->get_determiners() );
		$localize_name = 'automatic_css_settings';
		$localized_data = array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'automatic_css_save_settings' ),
			'database_settings' => ( Database_Settings::get_instance() )->get_vars(),
			'ui_settings' => ( new UI() )->load(),
			'version' => Plugin::get_plugin_version(),
			'loading_context' => array(
				'is_frontend' => Context::FRONTEND === $context_name,
				'is_preview' => Context::PREVIEW === $context_name,
				'is_builder' => Context::BUILDER === $context_name,
				'builder' => $builder,
				'active_plugins' => $this->get_active_plugins(),
			),
		);
		$this->dashboard_js_file->set_localize( $localize_name, $localized_data );
	}

	/**
	 * Get the builder name.
	 *
	 * @param array $determiners The determiners.
	 * @return string
	 */
	private function get_builder_name( $determiners ) {
		if ( count( $determiners ) === 0 ) {
			return '';
		}
		// $determiners[0] is the name of a class implementing DeterminesContextInterface.
		return $determiners[0]::get_name();
	}

	/**
	 * Get active plugins.
	 *
	 * @return array
	 */
	private function get_active_plugins() {
		$active_plugins = wp_get_active_and_valid_plugins();
		$plugin_filenames = array_map(
			function ( $path ) {
				$filename = basename( $path );
				switch ( $filename ) {
					case 'frames-plugin.php':
						$filename = 'frames';
						break;
				}
				$pos = strrpos( $filename, '.' );
				return ( false === $pos ) ? $filename : substr( $filename, 0, $pos );
			},
			$active_plugins
		);
		return $plugin_filenames;
	}

	/**
	 * Adds 'type="module"' to the script tag
	 *
	 * @param string $tag The original script tag.
	 * @param string $handle The script handle.
	 * @param string $src The script source.
	 * @return string
	 */
	public static function add_type_attribute( $tag, $handle, $src ) {
		$scripts_to_change = array( 'acss-dashboard', 'acss-hot-reload' );
		// Return early if not one of the scripts to change.
		if ( ! in_array( $handle, $scripts_to_change, true ) ) {
			return $tag;
		}
		$defer = Flag::is_on( 'DEFER_DASHBOARD_SCRIPTS' ) ? ' defer' : '';
		$module_and_crossorigin =
			Flag::is_on( 'LOAD_DASHBOARD_SCRIPTS_AS_MODULE' ) || Flag::is_on( 'LOAD_DASHBOARD_FROM_VITE' ) ?
			' type="module" crossorigin' :
			'';
		$tag = sprintf( '<script%s src="%s"%s></script>', $module_and_crossorigin, esc_url( $src ), $defer ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
		return $tag;
	}

	/**
	 * Save the plugin's settings.
	 *
	 * @return void
	 */
	public function save_settings() {
		$timer = new Timer();
		$this->ensure_permissions_to_save( __METHOD__ );
		$form_settings = $this->sanitize_input_data();
		// Log the raw input from the dashboard.
		WideEventLogger::set( 'save_settings.user_input', $form_settings );
		// Save settings.
		try {
			$this->save( $form_settings, $timer );
		} catch ( \Exception $e ) {
			$error_message = $e->getMessage();
			Logger::log( sprintf( '%s: caught this error: %s', __METHOD__, $error_message ), Logger::LOG_LEVEL_ERROR );
			Logger::log( debug_backtrace(), Logger::LOG_LEVEL_ERROR );
			WideEventLogger::failure(
				'settings',
				sprintf( 'Settings save failed: %s', $error_message )
			);
			Locale::restore_locale();
			wp_send_json_error( $error_message, 500 );
		}
	}

	/**
	 * Ensure the user has the permissions to save the settings.
	 *
	 * @param string $method_name The name of the method that is trying to save the settings.
	 * @return void
	 */
	private function ensure_permissions_to_save( $method_name ) {
		Logger::log( sprintf( '%s: starting', $method_name ) );
		if ( ! check_ajax_referer( 'automatic_css_save_settings', 'nonce', false ) ) {
			Logger::log( sprintf( '%s: failed nonce check - quitting early', $method_name ), Logger::LOG_LEVEL_ERROR );
			wp_send_json_error( 'Failed nonce check.', 400 );
		}
		if ( ! current_user_can( Database_Settings::CAPABILITY ) ) {
			Logger::log( sprintf( '%s: capability check failed - quitting early', $method_name ), Logger::LOG_LEVEL_ERROR );
			wp_send_json_error( 'You cannot save these settings.', 403 );
		}
	}

	/**
	 * Sanitize the input data.
	 *
	 * @return array
	 */
	private function sanitize_input_data() {
		// Sanitize and validate input data.
		$form_settings = json_decode( filter_input( INPUT_POST, 'database_settings' ), true );
		ksort( $form_settings ); // to make debugging easier.
		if ( ! is_array( $form_settings ) ) {
			Logger::log( sprintf( '%s: did not receive form settings in the expected format - quitting early', __METHOD__ ), Logger::LOG_LEVEL_ERROR );
			wp_send_json_error( 'Received empty settings or in an unexpected format.', 400 );
		}
		Logger::log( sprintf( "%s: received these form settings:\n%s", __METHOD__, print_r( $form_settings, true ) ), Logger::LOG_LEVEL_NOTICE );
		return $form_settings;
	}

	/**
	 * Save the settings.
	 *
	 * @param array $form_settings The form settings.
	 * @param Timer $timer The timer.
	 * @return void
	 */
	private function save( $form_settings, $timer ) {
		try {
			Locale::fix_locale();
			$model = Database_Settings::get_instance();
			$old_settings = $model->get_vars();
			$all_settings = array_merge( $old_settings, $form_settings );
			$all_settings['timestamp'] = time(); // Force update_option to always save the settings.
			ksort( $all_settings ); // to make debugging easier.
			$save_info = $model->save_settings( $all_settings );

			// Get the sanitized values after save.
			$sanitized_settings = $model->get_vars();

			// Log which values were transformed (input differs from output).
			$transformed = array();
			foreach ( $form_settings as $key => $input_value ) {
				if ( isset( $sanitized_settings[ $key ] ) && $sanitized_settings[ $key ] !== $input_value ) {
					$transformed[ $key ] = array(
						'input'  => $input_value,
						'output' => $sanitized_settings[ $key ],
					);
				}
			}
			if ( ! empty( $transformed ) ) {
				WideEventLogger::set( 'save_settings.user_input_sanitized', $transformed );
			}

			WideEventLogger::set( 'save_settings.generated_files', $save_info['generated_files'] );
			if ( true === $save_info['has_changed'] ) {
				$time = $timer->get_time();
				$generated_files = $save_info['generated_files_number'];
				// Settings were saved and CSS regenerated.
				Logger::log( sprintf( '%s: settings saved and %d CSS files regenerated - done in %s seconds', __METHOD__, $generated_files, $time ) );
				Locale::restore_locale();
				wp_send_json_success( sprintf( 'Settings updated and %d CSS file(s) generated correctly in %s seconds.', $generated_files, $time ) );
			} else {
				$time = $timer->get_time();
				// Settings were not saved because no changed was detected.
				// Please note: this is not an error! Those are thrown through exceptions.
				Logger::log( sprintf( '%s: no changes detected, did not save or regenerate CSS files - done in %s seconds', __METHOD__, $time ) );
				Locale::restore_locale();
				wp_send_json_success( 'No changes detected. Change a setting and click save to force stylesheet regeneration.' );
			}
		} catch ( Invalid_Form_Values $e ) {
			$error_message = $e->getMessage();
			$errors = $e->get_errors();
			WideEventLogger::failure(
				'validation',
				sprintf( 'Settings validation failed: %s', $error_message ),
				array(
					'fields' => array_keys( $errors ),
					'errors' => $errors,
				)
			);
			Locale::restore_locale();
			wp_send_json_error(
				array(
					'message' => $error_message,
					'errors' => $errors,
				),
				422 // Unprocessable Entity.
			);
		} catch ( CSS_Generation_Failed $e ) {
			// Settings were saved but CSS generation failed.
			$error_message = $e->getMessage();
			$errors = $e->get_errors();
			// WideEventLogger::failure() was already called in SCSS_File.
			Locale::restore_locale();
			wp_send_json_error(
				array(
					'message' => sprintf( 'Settings were saved, but CSS generation failed: %s', $error_message ),
					'errors' => $errors,
				),
				500
			);
		}
	}
}
