<?php
/**
 * SettingsServiceInterface.php
 *
 * Interface for the SettingsService to enable mocking in tests.
 *
 * @package Etch\Services
 */

declare(strict_types=1);

namespace Etch\Services;

use WP_Post;

/**
 * SettingsServiceInterface
 *
 * @package Etch\Services
 */
interface SettingsServiceInterface {

	/**
	 * Get all settings.
	 *
	 * @return array<string, mixed> Array of settings.
	 */
	public function get_settings(): array;

	/**
	 * Set all settings.
	 *
	 * @param array<string, mixed> $settings Array of settings.
	 * @return void
	 */
	public function set_settings( array $settings ): void;

	/**
	 * Check if a specific setting exists.
	 *
	 * @param string $key The setting key.
	 * @return bool True if the setting exists, false otherwise.
	 */
	public function has_setting( string $key ): bool;

	/**
	 * Get a specific setting.
	 *
	 * @param string $key The setting key.
	 * @return mixed The setting value or null if not found.
	 */
	public function get_setting( string $key ): mixed;

	/**
	 * Set a single setting.
	 *
	 * @param string $key The setting key.
	 * @param mixed  $value The setting value.
	 * @return void
	 */
	public function set_setting( string $key, mixed $value ): void;

	/**
	 * Delete a single setting.
	 *
	 * @param string $key The setting key.
	 * @return void
	 */
	public function delete_setting( string $key ): void;
}
