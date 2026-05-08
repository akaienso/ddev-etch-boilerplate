<?php
/**
 * SettingsService.php
 *
 * Service class for etch specific settings
 *
 * @package Etch\Services
 */

declare(strict_types=1);

namespace Etch\Services;

use Etch\Helpers\EncryptionHelper;
use Etch\Helpers\ObfuscationHelper;
use Etch\Traits\Singleton;
/**
 * SettingsService class.
 *
 * @package Etch\Services
 */
class SettingsService implements SettingsServiceInterface {
	use Singleton;

	private const OPTION_NAME = 'etch_settings';

	private const SENSITIVE_SETTINGS = array( 'ai_api_key' );

	private const DEFAULTS = array(
		'allow_raw_html_unsafe_usage' => false,
		'remove_wp_default_styles_and_scripts' => true,
		'allow_font_uploads' => false,
		'experimental_ai' => false,
		'ai_show_reasoning' => true,
		'ai_show_tool_calls' => true,
		'partial_class_fix' => true,
		'component_editor_redesign' => false,
		'overwrite_on_paste' => false,
		'component_instance_inputs_redesign' => false,
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Ensure default values
		$settings = $this->get_settings();

		// Only update if we're actually changing something
		$needs_update = false;

		// Check and set default values
		foreach ( self::DEFAULTS as $key => $value ) {
			if ( ! array_key_exists( $key, $settings ) ) {
				$settings[ $key ] = $value;
				$needs_update = true;
			}
		}

		// Only write to database if we made changes
		if ( $needs_update ) {
			$this->set_settings( $settings );
		}
	}

	/**
	 * Get all settings.
	 *
	 * @return array<string, mixed> Array of settings.
	 */
	public function get_settings(): array {
		$settings = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		foreach ( self::SENSITIVE_SETTINGS as $key ) {
			if ( isset( $settings[ $key ] ) && is_string( $settings[ $key ] ) && '' !== $settings[ $key ] ) {
				$decrypted = EncryptionHelper::decrypt( $settings[ $key ] );
				$settings[ $key ] = false !== $decrypted
					? ObfuscationHelper::obfuscate( $decrypted )
					: '';
			}
		}

		return $settings;
	}

	/**
	 * Set all settings.
	 *
	 * @param array<string, mixed> $settings Array of settings.
	 * @return void
	 */
	public function set_settings( array $settings ): void {
		$stored = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		foreach ( self::SENSITIVE_SETTINGS as $key ) {
			if ( ! isset( $settings[ $key ] ) || ! is_string( $settings[ $key ] ) || '' === $settings[ $key ] ) {
				continue;
			}

			$decrypted = false;

			if ( isset( $stored[ $key ] ) && is_string( $stored[ $key ] ) ) {
				$decrypted = EncryptionHelper::decrypt( $stored[ $key ] );
			}

			if ( false !== $decrypted && ObfuscationHelper::is_obfuscated( $settings[ $key ], $decrypted ) ) {
				$settings[ $key ] = $stored[ $key ];
			} else {
				$settings[ $key ] = EncryptionHelper::encrypt( $settings[ $key ] );
			}
		}

		update_option( self::OPTION_NAME, $settings );
	}

	/**
	 * Check if a specific setting exists.
	 *
	 * @param string $key The setting key.
	 * @return bool True if the setting exists, false otherwise.
	 */
	public function has_setting( string $key ): bool {
		$settings = $this->get_settings();
		return array_key_exists( $key, $settings );
	}

	/**
	 * Get a specific setting.
	 *
	 * @param string $key The setting key.
	 * @return mixed The setting value or null if not found.
	 */
	public function get_setting( string $key ): mixed {
		$settings = $this->get_settings();
		return $settings[ $key ] ?? null;
	}

	/**
	 * Set a single setting.
	 *
	 * @param string $key The setting key.
	 * @param mixed  $value The setting value.
	 * @return void
	 */
	public function set_setting( string $key, mixed $value ): void {
		$settings = $this->get_settings();
		$settings[ $key ] = $value;
		$this->set_settings( $settings );
	}

	/**
	 * Get a decrypted sensitive setting value (for internal use).
	 *
	 * @param string $key The setting key.
	 * @return mixed The decrypted setting value or null if not found.
	 */
	public function get_decrypted_setting( string $key ): mixed {
		$settings = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $settings ) || ! isset( $settings[ $key ] ) ) {
			return null;
		}

		$value = $settings[ $key ];

		if ( in_array( $key, self::SENSITIVE_SETTINGS, true ) && is_string( $value ) && '' !== $value ) {
			$decrypted = EncryptionHelper::decrypt( $value );
			return false !== $decrypted ? $decrypted : null;
		}

		return $value;
	}

	/**
	 * Delete a single setting.
	 *
	 * @param string $key The setting key.
	 * @return void
	 */
	public function delete_setting( string $key ): void {
		$settings = $this->get_settings();
		unset( $settings[ $key ] );
		$this->set_settings( $settings );
	}
}
