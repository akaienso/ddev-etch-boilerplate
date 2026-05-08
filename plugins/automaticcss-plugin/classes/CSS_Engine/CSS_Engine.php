<?php
/**
 * Automatic.css Framework's CSS_Engine file.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\CSS_Engine;

use Automatic_CSS\Exceptions\CSS_Generation_Failed;
use Automatic_CSS\Model\Database_Settings;
use Automatic_CSS\Model\SettingsRepositoryInterface;
use Automatic_CSS\Helpers\Logger;
use Automatic_CSS\Model\Config\UI;
use Automatic_CSS\Traits\ContainerAwareSingleton;

/**
 * Automatic.css Framework's CSS_Engine class.
 */
class CSS_Engine {

	use ContainerAwareSingleton;

	/**
	 * Stores the settings repository for accessing plugin settings.
	 *
	 * @var SettingsRepositoryInterface
	 */
	private $database_settings;

	/**
	 * Loads the basic CSS_Engine components.
	 *
	 * @param SettingsRepositoryInterface|null $settings Optional settings repository for testing.
	 * @return CSS_Engine
	 */
	public function init( ?SettingsRepositoryInterface $settings = null ) {
		// (re)generate the framework's CSS file(s) when the plugin is activated.
		add_action( 'automaticcss_activate_plugin_start', array( $this, 'update_framework_css_files' ) );
		// (re)generate the framework's CSS file(s) when the plugin is updated.
		// @since 1.1.1.1 - MG - we don't do this anymore: too many side effects.
		// add_action( 'automaticcss_update_plugin_start', array( $this, 'update_framework_css_files' ) );
		// @since 1.4.0 - MG - to handle changes in variable names that need to carry over the values.
		add_action( 'automaticcss_update_plugin_start', array( $this, 'handle_database_upgrade' ), 10, 2 );
		// delete the framework's CSS file(s) when the plugin is deleted.
		add_action( 'automaticcss_delete_plugin_data_end', array( $this, 'delete_css_files' ) );
		add_filter( 'mce_css', array( $this, 'remove_acss_styles_in_tinymce' ) );
		// Note: CSS generation filter is now handled by GenerationOrchestrator.
		// Initialize settings repository.
		$this->database_settings = $settings ?? Database_Settings::get_instance();
		Logger::log( sprintf( '%s: initialized', __METHOD__ ) );
		return $this;
	}

	/**
	 * Set the settings repository.
	 *
	 * Primarily used for testing to inject a mock settings repository.
	 *
	 * @param SettingsRepositoryInterface $settings The settings repository to use.
	 * @return void
	 */
	public function set_settings_repository( SettingsRepositoryInterface $settings ): void {
		$this->database_settings = $settings;
	}

	/**
	 * Create of update the framework's stylesheet(s)
	 * from the existing values (if the wp_option exists)
	 * - OR -
	 * from the framework's defaults (if it doesn't exist)
	 *
	 * @return void
	 */
	public function update_framework_css_files() {
		// TODO: find a better method name.
		Logger::log( sprintf( '%s: creating or updating framework CSS files', __METHOD__ ) );
		// Generate from current vars from db (if they exist), otherwise use defaults.
		$values = $this->database_settings->get_vars();
		Logger::log( sprintf( '%s: values from database: %s', __METHOD__, print_r( $values, true ) ), Logger::LOG_LEVEL_NOTICE );
		if ( ! is_array( $values ) || 0 === count( $values ) ) {
			Logger::log( sprintf( '%s: no vars found in database, using default values', __METHOD__ ) );
			$values = ( new UI() )->get_default_settings();
			Logger::log( sprintf( '%s: default values: %s', __METHOD__, print_r( $values, true ) ), Logger::LOG_LEVEL_NOTICE );
			$this->database_settings->save_settings( $values, false ); // will NOT trigger the CSS file generation.
		}
		$this->generate_all_css_files( $values );
		Logger::log( sprintf( '%s: done', __METHOD__ ) );
	}

	/**
	 * Generate and save all registered stylesheets for the provided values.
	 *
	 * Delegates to GenerationOrchestrator for the actual generation.
	 * This method is kept for backward compatibility.
	 *
	 * @param array $database_settings The values of the settings from the database.
	 * @return array The generated CSS files.
	 * @throws CSS_Generation_Failed If CSS generation fails.
	 */
	public function generate_all_css_files( $database_settings ) {
		$settings = ! empty( $database_settings ) ? $database_settings : $this->database_settings->get_vars();
		return GenerationOrchestrator::get_instance()->generate( $settings );
	}

	/**
	 * Handle changes to the database due to upgrading / downgrading the plugin.
	 *
	 * @param string $current_version The plugin version currently installed.
	 * @param string $previous_version The plugin version previously installed.
	 * @return void
	 */
	public function handle_database_upgrade( $current_version, $previous_version ) {
		Logger::log( sprintf( '%s: starting', __METHOD__ ) );
		$current_values = $this->database_settings->get_vars();
		$new_values = apply_filters( 'automaticcss_upgrade_database', $current_values, $current_version, $previous_version );
		/**
		 * We used to trigger save_vars only if the vars had changed, but the SCSS might have too.
		 * So now we may trigger CSS generation even if the vars haven't changed.
		 *
		 * @since 2.7.0
		 */
		$this->database_settings->save_settings( $new_values ); // will trigger generate_all_css_files.
		Logger::log( sprintf( '%s: done', __METHOD__ ) );
	}

	/**
	 * Delete all CSS_Files
	 *
	 * Delegates to GenerationOrchestrator for the actual deletion.
	 *
	 * @return void
	 */
	public function delete_css_files() {
		GenerationOrchestrator::get_instance()->delete_all_css_files();
	}

	/**
	 * Remove all ACSS css that could be loaded inside the TinyMCE iframe
	 * All styles that are loaded by add_editor_style is loaded inside iframe and cause conflicts
	 *
	 * @param string $mce_css String with all css paths for mce.
	 * @return string
	 */
	public function remove_acss_styles_in_tinymce( $mce_css ) {
		$styles = explode( ',', $mce_css );
		$filtered_styles = array_filter(
			$styles,
			function ( $style ) {
				return strpos( $style, 'automatic-css' ) === false;
			}
		);

		return implode( ',', $filtered_styles );
	}
}
