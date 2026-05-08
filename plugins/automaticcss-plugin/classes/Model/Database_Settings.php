<?php
/**
 * Automatic.css Database_Settings file.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Model;

use Automatic_CSS\Exceptions\Insufficient_Permissions;
use Automatic_CSS\Exceptions\Invalid_Form_Values;
use Automatic_CSS\Exceptions\Invalid_Variable;
use Automatic_CSS\Helpers\Flag;
use Automatic_CSS\Helpers\Timer;
use Automatic_CSS\Helpers\Logger;
use Automatic_CSS\Migrations\MigrationRunner;
use Automatic_CSS\Migrations\Versions;
use Automatic_CSS\Model\Config\UI;
use Automatic_CSS\Traits\ContainerAwareSingleton;

/**
 * Automatic.css Database_Settings class.
 *
 * Implements SettingsRepositoryInterface for settings access.
 * Uses Singleton pattern for backward compatibility, but supports
 * dependency injection via the interface for testability.
 *
 * @see SettingsRepositoryInterface For the contract this class fulfills.
 */
final class Database_Settings implements SettingsRepositoryInterface {

	use ContainerAwareSingleton;

	/**
	 * Stores the name of the plugin's database option
	 *
	 * @var string
	 */
	public const ACSS_SETTINGS_OPTION = 'automatic_css_settings';

	/**
	 * Stores the current value from the wp_options table.
	 *
	 * @var array|null
	 */
	private $plugin_wp_options = null;

	/**
	 * Capability needed to write settings
	 *
	 * @var string
	 */
	public const CAPABILITY = 'manage_options';

	/**
	 * The migration runner instance.
	 *
	 * @var MigrationRunner|null
	 */
	private ?MigrationRunner $migration_runner = null;

	/**
	 * Initialize the class
	 *
	 * @return Database_Settings The current instance of the class.
	 */
	public function init() {
		// Initialize the migration runner with registered migrations.
		$this->migration_runner = $this->create_migration_runner();
		// Handle database changes when the plugin is updated.
		add_filter( 'automaticcss_upgrade_database', array( $this, 'run_migrations' ), 10, 3 );

		if ( is_admin() ) {
			// Handle deleting database options when the plugin is deleted.
			add_action( 'automaticcss_delete_plugin_data_start', array( $this, 'delete_database_options' ) );
		}
		return $this;
	}

	/**
	 * Create and configure the migration runner.
	 *
	 * @return MigrationRunner
	 */
	private function create_migration_runner(): MigrationRunner {
		$runner = new MigrationRunner();

		// Register migrations in version order.
		$runner->register( Versions\Migration_2_0_0::class );
		$runner->register( Versions\Migration_2_2_0::class );
		$runner->register( Versions\Migration_2_4_0::class );
		$runner->register( Versions\Migration_2_5_0::class );
		$runner->register( Versions\Migration_2_6_0::class );
		$runner->register( Versions\Migration_2_7_0::class );
		$runner->register( Versions\Migration_2_8_0::class );
		$runner->register( Versions\Migration_3_0_0::class );
		$runner->register( Versions\Migration_3_0_8::class );
		$runner->register( Versions\Migration_3_1_3::class );
		$runner->register( Versions\Migration_4_0_0_Alpha_1::class );
		$runner->register( Versions\Migration_4_0_0_Beta_1::class );
		$runner->register( Versions\Migration_4_0_0_Beta_2::class );
		$runner->register( Versions\Migration_4_0_0_Beta_3::class );
		$runner->register( Versions\Migration_4_0_0_Beta_4::class );

		return $runner;
	}

	/**
	 * Run migrations when upgrading the plugin.
	 *
	 * This is the filter hook callback for 'automaticcss_upgrade_database'.
	 * Delegates to upgrade_database() which handles all migrations via MigrationRunner.
	 *
	 * @param array<string, mixed> $values           The current settings values.
	 * @param string               $current_version  The version being upgraded to.
	 * @param string               $previous_version The version being upgraded from.
	 * @return array<string, mixed> The migrated settings values.
	 */
	public function run_migrations( $values, $current_version, $previous_version ) {
		return $this->upgrade_database( $values, $current_version, $previous_version );
	}

	/**
	 * Get the current VARS values from the wp_options database table.
	 *
	 * @return array<string, mixed>
	 */
	public function get_vars(): array {
		if ( ! isset( $this->plugin_wp_options ) ) {
			$this->plugin_wp_options = (array) get_option( self::ACSS_SETTINGS_OPTION, array() );
		}
		return $this->plugin_wp_options;
	}

	/**
	 * Get the value for a specific variable from the wp_options database table.
	 *
	 * @param  string $var The variable name.
	 * @return mixed|null
	 */
	public function get_var( string $var ) {
		$vars = $this->get_vars();
		if ( is_array( $vars ) && array_key_exists( $var, $vars ) ) {
			return $vars[ $var ];
		}
		return null;
	}

	/**
	 * Save the plugin's options to the database. Will work even if option doesn't exist (fresh start).
	 *
	 * Note: CSS_Generation_Failed may propagate if CSS generation fails (settings are saved but CSS files were not generated).
	 *
	 * @see    https://developer.wordpress.org/reference/functions/update_option/
	 * @param  array<string, mixed> $values The plugin's options.
	 * @param  bool                 $trigger_css_generation Trigger the CSS generation process upon saving or not.
	 * @return array{has_changed: bool, generated_files: array<string>, generated_files_number: int}|null Info about the saved options and the generated CSS files, or null if nothing to save.
	 * @throws Invalid_Form_Values If the form values are not valid.
	 * @throws Insufficient_Permissions If the user does not have sufficient permissions to save the plugin settings.
	 */
	public function save_settings( array $values, bool $trigger_css_generation = true ) {
		if ( empty( $values ) ) {
			Logger::log( sprintf( '%s: received empty array of values to save - exiting early', __METHOD__ ) );
			return null;
		}
		$doing_cron = defined( 'DOING_CRON' ) && DOING_CRON;
		$doing_cli  = defined( 'WP_CLI' ) && WP_CLI;
		$current_user_ID = get_current_user_id();
		// TODO: remove the following log line when we're done debugging.
		Logger::log( sprintf( '%s: saving settings - user ID %d - doing cron is %s - doing cli is %s', __METHOD__, $current_user_ID, $doing_cron ? 'true' : 'false', $doing_cli ? 'true' : 'false' ) );
		if ( ! current_user_can( self::CAPABILITY ) && ! $doing_cron && ! $doing_cli ) {
			throw new Insufficient_Permissions(
				esc_html(
					sprintf(
						'The current user (ID=%d) does not have sufficient permissions to save the plugin settings. Make sure to save the settings with a user that has the %s capability.',
						$current_user_ID,
						self::CAPABILITY
					)
				)
			);
		}
		$timer = new Timer();
		$return_info = array(
			'has_changed' => false,
			'generated_files' => array(),
			'generated_files_number' => 0,
		);
		$ui = new UI();
		$allowed_variables = $ui->get_all_settings();
		$sanitized_values = array();
		$errors = array();
		Logger::log( sprintf( '%s: triggering automaticcss_settings_save', __METHOD__ ) );
		do_action( 'automaticcss_settings_before_save', $values );
		// STEP: validate the form values and get the sanitized values.
		Logger::log( sprintf( '%s: allowed variables are %s', __METHOD__, print_r( $allowed_variables, true ) ), Logger::LOG_LEVEL_INFO );
		foreach ( $allowed_variables as $var_id => $var_options ) {
			// This makes it so that we ignore non allowed variables coming from the form (i.e. variables not in our config file).
			Logger::log( sprintf( '%s: checking variable %s', __METHOD__, $var_id ), Logger::LOG_LEVEL_INFO );
			$default_value = isset( $var_options['default'] ) ? $var_options['default'] : null;
			try {
				$sanitized_values[ $var_id ] = $this->get_validated_setting( $var_id, $values, $var_options, $default_value );
			} catch ( Invalid_Variable $e ) {
				$errors[ $var_id ] = $e->getMessage();
			}
		}
		// STEP: if there are errors, throw an exception.
		if ( ! empty( $errors ) ) {
			Logger::log( sprintf( "%s: errors found while saving settings:\n%s", __METHOD__, print_r( $errors, true ) ), Logger::LOG_LEVEL_ERROR );
			$error_message = 'The settings you tried to save contain errors. Make sure to fix them in the ACSS settings page and save again.';
			throw new Invalid_Form_Values( esc_html( $error_message ), $errors ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		// STEP: save the sanitized values to the database.
		Logger::log( sprintf( "%s: saving these variables to the database:\n%s", __METHOD__, print_r( $sanitized_values, true ) ), Logger::LOG_LEVEL_NOTICE );
		/**
		 * We used to trigger save_vars only if the vars had changed, but the SCSS might have too.
		 * So now we may trigger CSS generation even if the vars haven't changed.
		 *
		 * @since 2.7.0
		 */
		$return_info['has_changed'] = update_option( self::ACSS_SETTINGS_OPTION, $sanitized_values );
		$this->plugin_wp_options = $sanitized_values;
		do_action( 'automaticcss_settings_after_save', $sanitized_values );
		// STEP: if the settings have changed and CSS generation is enabled, regenerate the CSS.
		if ( $trigger_css_generation ) {
			$generation_result = apply_filters( 'automaticcss_generate_css', array(), $sanitized_values );
			$return_info['generated_files'] = $generation_result;
			$return_info['generated_files_number'] = count( $generation_result );
		}
		do_action( 'automaticcss_settings_after_regeneration', $sanitized_values );
		Logger::log(
			sprintf(
				'%s: done (saved settings: %b; regenerated CSS files: %s) in %s seconds',
				__METHOD__,
				$return_info['has_changed'],
				print_r( implode( ', ', $return_info['generated_files'] ), true ),
				$timer->get_time()
			)
		);
		return $return_info;
	}

	/**
	 * Validate a variable based on its type and value and return a sanitized value.
	 *
	 * @param  string $var_id      Variable's ID.
	 * @param  array  $all_values  All variables' values.
	 * @param  array  $var_options Variable's options.
	 * @param  mixed  $default_value The default value for the variable.
	 * @return mixed
	 * @throws Invalid_Variable Exception if the variable is invalid.
	 */
	private function get_validated_setting( $var_id, $all_values, $var_options, $default_value ) {
		$default_value = Flag::is_on( 'ADD_DEFAULTS_TO_SAVE_PROCESS' ) ? $default_value : null;
		$var_value = isset( $all_values[ $var_id ] ) ? $all_values[ $var_id ] : $default_value;
		// TODO: remove the ENABLE_BACKEND_VALIDATION flag when the code is stable.
		if ( ! Flag::is_on( 'ENABLE_BACKEND_VALIDATION' ) ) {
			return $var_value;
		}
		$type = isset( $var_options['type'] ) ? $var_options['type'] : null;
		if ( null === $type ) {
			$message = sprintf( '%s has no type defined.', $var_id );
			self::log_validation_error( $var_id, $message );
			throw new Invalid_Variable( esc_html( $message ) );
		}
		// STEP: perform a basic sanitization on the form's field.
		$var_value = sanitize_text_field( $var_value );
		// STEP: check that the value is not empty, if required.
		$required = self::is_required( $var_id, $var_value, $var_options, $all_values );
		if ( ! $required && '' === $var_value ) {
			// nothing else to check.
			self::log_validation_error( $var_id, 'is not required and is empty, skipping its validation', Logger::LOG_LEVEL_INFO );
			return $var_value;
		} else if ( $required && '' === $var_value ) {
			self::log_validation_error( $var_id, 'cannot be empty.' );
			throw new Invalid_Variable( esc_html( sprintf( '%s: cannot be empty.', $var_id ) ) );
		}
		// STEP: validate the value based on the type.
		$validation = array_key_exists( 'validation', $var_options ) ? $var_options['validation'] : array();
		switch ( $type ) {
			case 'text':
			case 'textarea':
			case 'codebox':
			case 'clone':
				break;
			case 'number':
			case 'px':
			case 'rem':
			case 'percent':
				// STEP: check that the value is a number.
				if ( ! is_numeric( $var_value ) ) {
					$message = sprintf( '%s: %s is not a number.', $var_id, $var_value );
					self::log_validation_error( $var_id, $message );
					throw new Invalid_Variable( esc_html( $message ) );
				}
				// STEP: convert it to the proper type.
				$var_value = strpos( $var_value, '.' ) !== false ? intval( $var_value ) : floatval( $var_value );
				// STEP: check that the value is within the allowed range.
				$min = isset( $validation['min'] ) ? $validation['min'] : null;
				$max = isset( $validation['max'] ) ? $validation['max'] : null;
				if ( null !== $min && $var_value < $min ) {
					$message = sprintf( '%s: %s is smaller than the minimum allowed value of %s.', $var_id, $var_value, $min );
					self::log_validation_error( $var_id, $message );
					throw new Invalid_Variable( esc_html( $message ) );
				}
				if ( null !== $max && $var_value > $max ) {
					$message = sprintf( '%s: %s is greater than the maximum allowed value of %s.', $var_id, $var_value, $max );
					self::log_validation_error( $var_id, $message );
					throw new Invalid_Variable( esc_html( $message ) );
				}
				break;
			case 'color':
				// STEP: check that the value is a hex color.
				if ( ! $var_value || '' === $var_value || ! preg_match( '/^#[a-f0-9]{6}$/i', $var_value ) ) {
					$message = sprintf( '%s: %s is not a valid hex color.', $var_id, $var_value );
					self::log_validation_error( $var_id, $message );
					throw new Invalid_Variable( esc_html( $message ) );
				}
				break;
			case 'select':
				// STEP: convert the value to the proper type (if it's a string, it stays that way).
				$var_value = self::get_converted_value( $var_value );
				// STEP: check if the value is in the list of allowed values.
				$options = isset( $var_options['options'] ) ? $var_options['options'] : null;
				if ( null === $options ) {
					$message = sprintf( '%s has no options defined.', $var_id );
					self::log_validation_error( $var_id, $message );
					throw new Invalid_Variable( esc_html( $message ) );
				}
				if ( ! in_array( $var_value, $options ) ) {
					$message = sprintf( '%s: %s is not a valid option.', $var_id, $var_value );
					self::log_validation_error( $var_id, $message );
					throw new Invalid_Variable( esc_html( $message ) );
				}
				break;
			case 'toggle':
				// STEP: check that the value is either 'on' or 'off'.
				if ( 'on' !== $var_value && 'off' !== $var_value ) {
					$message = sprintf( '%s: %s is not a valid toggle value.', $var_id, $var_value );
					self::log_validation_error( $var_id, $message );
					throw new Invalid_Variable( esc_html( $message ) );
				}
				break;
		}
		// STEP: return the validated and sanitized value.
		return $var_value;
	}

	/**
	 * Check weather a variable is required based on its settings and possibly other variables' values.
	 *
	 * @param  string $var_id      Variable's ID.
	 * @param  mixed  $var_value   Variable's value.
	 * @param  array  $var_options Variable's options.
	 * @param  array  $all_values  All variables' values.
	 * @return boolean
	 */
	private static function is_required( $var_id, $var_value, $var_options, $all_values ) {
		// STEP: check if it has a default value.
		$validation = $var_options['validation'] ?? array();
		// Any input that doesn't have a "required" property is required.
		$required_by_base_validation = isset( $validation['required'] ) ? (bool) $validation['required'] : true;
		// STEP: check if another field requires this field.
		$required_by_condition = false;
		if ( ! empty( $var_options['displayWhen'] ) ) {
			/**
			 * Possible syntax for displayWhen:
			 * $var_options['displayWhen'] = 'setting_name' -> the field is required when setting_name is 'on'.
			 * $var_options['displayWhen'] = array( 'setting_name', 'value' ) -> the field is required when setting_name is 'value'.
			 * $var_options['displayWhen'] = array( array( 'setting_name', 'value' ), array( 'setting_name2', 'value2' ) ) -> the field is required when setting_name is 'value' AND setting_name2 is 'value2'.
			 *
			 * We'll reduce all of these to the last case, so that we can handle them all the same way.
			 *
			 * Tests (base "require"):
			 * - root-font-size: true
			 * - box-shadow-1-name: false
			 * -
			 * Tests (simple "displayWhen")
			 * - breakpoint-xxl if option-breakpoint-xxl is on: true
			 * - breakpoint-xxl if option-breakpoint-xxl is off: false
			 * - primary-dark-h if option-primary-clr is on: true
			 * - primary-dark-h if option-primary-clr is off: false
			 * - primary-dark-h-alt if option-primary-clr-alt is on: true
			 * - primary-dark-h-alt if option-primary-clr-alt is off: false
			 *
			 * Tests (multiple "displayWhen")
			 * - primary-medium-h if option-primary-clr is on AND option-medium-shade is on: true
			 * - primary-medium-h if option-primary-clr is off OR option-medium-shade is off: false
			 */
			$is_just_setting_name = is_string( $var_options['displayWhen'] );
			$is_multiple_conditions = is_array( $var_options['displayWhen'] ) && is_array( $var_options['displayWhen'][0] );
			// STEP: if it's just a string, set 'on' as the condition's value.
			if ( $is_just_setting_name ) {
				$var_options['displayWhen'] = array( $var_options['displayWhen'], 'on' );
			}
			// STEP: if it's just one condition, set it as the only condition in an array.
			if ( ! $is_multiple_conditions ) {
				$var_options['displayWhen'] = array( $var_options['displayWhen'] );
			}
			// STEP: determine if the field is required based on the condition.
			$required_by_condition = true; // 'AND' logic: start with true and set to false if any condition is not met.
			foreach ( $var_options['displayWhen'] as $condition ) {
				if ( count( $condition ) !== 2 || ! isset( $condition[0] ) || ! isset( $condition[1] ) ) {
					// Invalid condition.
					Logger::log( sprintf( '%s: invalid condition for %s', __METHOD__, $var_id ), Logger::LOG_LEVEL_ERROR );
					continue;
				}
				$condition_field = $condition[0];
				$condition_required_value = self::get_converted_value( $condition[1] );
				$condition_actual_value = isset( $all_values[ $condition_field ] ) ? self::get_converted_value( $all_values[ $condition_field ] ) : null;
				if ( $condition_actual_value !== $condition_required_value ) {
					$required_by_condition = false;
				}
			}
		}
		// STEP: return the result.
		$required = $required_by_base_validation || $required_by_condition;
		Logger::log(
			sprintf(
				'%s: %s is%s required (required = %s, required_by_condition = %s)',
				__METHOD__,
				$var_id,
				$required ? '' : ' not',
				$required_by_base_validation ? 'true' : 'false',
				$required_by_condition ? 'true' : 'false'
			),
			Logger::LOG_LEVEL_NOTICE
		);
		return $required;
	}

	/**
	 * Log a validation error.
	 *
	 * @param string $var_id      The variable ID.
	 * @param string $error_message The error message.
	 * @param string $log_level The log level.
	 * @return void
	 */
	private static function log_validation_error( $var_id, $error_message, $log_level = Logger::LOG_LEVEL_ERROR ) {
		Logger::log( sprintf( '%s: [%s] %s', __METHOD__, $var_id, $error_message ), $log_level );
	}

	/**
	 * Convert the value based on the type. Supports int, float and string.
	 *
	 * @param  mixed $value The input value.
	 * @return mixed
	 */
	private static function get_converted_value( $value ) {
		if ( self::is_int( $value ) ) {
			return intval( $value );
		} else if ( self::is_float( $value ) ) {
			return floatval( $value );
		}
		return $value;
	}

	/**
	 * Is this value an integer?
	 *
	 * @param  mixed $value The value to check.
	 * @return boolean
	 */
	private static function is_int( $value ) {
		return( ctype_digit( strval( $value ) ) );
	}

	/**
	 * Is this value a float?
	 *
	 * @param  mixed $value The value to check.
	 * @return boolean
	 */
	private static function is_float( $value ) {
		return (string) (float) $value === $value;
	}

	/**
	 * Update database fields and values upon plugin upgrade.
	 *
	 * @param  array  $values           The database values.
	 * @param  string $current_version  The version of the plugin we're upgrading to.
	 * @param  string $previous_version The version of the plugin we're upgrading from.
	 * @return array The (maybe modified) database values.
	 */
	public function upgrade_database( $values, $current_version, $previous_version ) {
		// Guard: return null for invalid input (backward compatibility).
		if ( ! is_array( $values ) || empty( $values ) ) {
			Logger::log( sprintf( '%s: empty or non-array values - exiting early', __METHOD__ ) );
			return;
		}
		if ( empty( $current_version ) || empty( $previous_version ) ) {
			Logger::log( sprintf( '%s: empty version string - exiting early', __METHOD__ ) );
			return;
		}

		// Run migrations via the migration runner.
		if ( null === $this->migration_runner ) {
			$this->migration_runner = $this->create_migration_runner();
		}
		return $this->migration_runner->run( $values, $current_version, $previous_version );
	}

	/**
	 * In 3.0.1, base-heading-lh and base-text-lh were not wrapped in calc().
	 * This caused an error when saving settings.
	 * If the settings had been committed to the database, this would cause the plugin to no longer activate.
	 *
	 * @return void
	 */
	public static function hotfix_302() {
		$already_run = get_option( 'automatic_css__hotfix_302', false );
		if ( $already_run ) {
			return;
		}
		$settings = get_option( self::ACSS_SETTINGS_OPTION );
		if ( ! is_array( $settings ) ) {
			return;
		}
		$options_to_fix = array( 'base-heading-lh', 'base-text-lh' );
		// If the options to fix are not wrapped in a calc(), wrap them.
		foreach ( $options_to_fix as $option ) {
			if ( isset( $settings[ $option ] ) && false === strpos( $settings[ $option ], 'calc(' ) ) {
				$settings[ $option ] = 'calc(' . $settings[ $option ] . ')';
				update_option( 'automatic_css__hotfix_302', true );
			}
		}
		update_option( self::ACSS_SETTINGS_OPTION, $settings );
	}

	/**
	 * Delete the framework's database option(s).
	 *
	 * @return void
	 * @throws Insufficient_Permissions If the user does not have permission to delete the database options.
	 */
	public function delete_database_options() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			throw new Insufficient_Permissions( 'You do not have permission to delete the database options.' );
		}
		delete_option( self::ACSS_SETTINGS_OPTION );
	}
}
