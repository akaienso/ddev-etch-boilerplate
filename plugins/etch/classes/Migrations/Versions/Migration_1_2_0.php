<?php
/**
 * Migration for version 1.2.0
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Migrations\Versions;

use Etch\Helpers\Logger;
use Etch\Helpers\WideEventLogger;
use Etch\Migrations\MigrationInterface;
use Etch\Services\SettingsService;
use Etch\Services\SettingsServiceInterface;

/**
 * Cleans up unused legacy settings.
 */
class Migration_1_2_0 implements MigrationInterface {

	/**
	 * List of legacy settings to clean up.
	 */
	private const LEGACY_SETTINGS = array(
		'allow_unsafe_usage', // was renamed to 'allow_raw_html_unsafe_usage'
		'experimental_undo_redo_v1',
		'remove_wp_default_css', // was renamed to 'remove_wp_default_styles_and_scripts'
	);

	/**
	 * Settings service for storing settings.
	 *
	 * @var SettingsServiceInterface
	 */
	private SettingsServiceInterface $settings_service;

	/**
	 * Constructor.
	 *
	 * @param SettingsServiceInterface|null $settings_service Settings service for storing settings.
	 */
	public function __construct(
		?SettingsServiceInterface $settings_service = null
	) {
		$this->settings_service = $settings_service ?? SettingsService::get_instance();
	}

	/**
	 * Get the target version for this migration.
	 *
	 * @return string Semantic version string.
	 */
	public function get_version(): string {
		return '1.2.0';
	}

	/**
	 * Get a human-readable description of what this migration does.
	 *
	 * @return string Description of the migration.
	 */
	public function get_description(): string {
		return 'Removes unused legacy settings from the database to clean up after previous migrations and renames.';
	}

	/**
	 * Run the migration.
	 *
	 * @return void
	 */
	public function run(): void {
		$this->remove_legacy_settings();
	}

	/**
	 * Removes legacy settings.
	 *
	 * @return void
	 */
	private function remove_legacy_settings(): void {
		foreach ( self::LEGACY_SETTINGS as $key ) {
			$found = $this->settings_service->has_setting( $key );
			WideEventLogger::set( "migration.1_2_0.{$key}_found", $found );

			if ( $found ) {
				$this->settings_service->delete_setting( $key );
				WideEventLogger::set( "migration.1_2_0.{$key}_removed", true );
				Logger::log( "Migration 1.2.0: Removed legacy setting '{$key}'." );
			} else {
				Logger::log( "Migration 1.2.0: Legacy setting '{$key}' not found, skipping." );
			}
		}
	}
}
