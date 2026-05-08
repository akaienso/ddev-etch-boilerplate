<?php
/**
 * Settings Repository Interface.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Model;

/**
 * Interface for settings repository operations.
 *
 * This interface abstracts the settings storage mechanism, enabling:
 * - Unit testing with mock implementations
 * - Potential future storage backends (e.g., custom tables, external APIs)
 * - Clear contract for settings access throughout the codebase
 */
interface SettingsRepositoryInterface {

	/**
	 * Get all settings values.
	 *
	 * @return array<string, mixed> All settings as key-value pairs.
	 */
	public function get_vars(): array;

	/**
	 * Get a specific setting value.
	 *
	 * @param string $var The setting name.
	 * @return mixed|null The setting value, or null if not found.
	 */
	public function get_var( string $var );

	/**
	 * Save settings to storage.
	 *
	 * @param array<string, mixed> $values The settings to save.
	 * @param bool                 $trigger_css_generation Whether to regenerate CSS after saving.
	 * @return array{has_changed: bool, generated_files: array<string>, generated_files_number: int}|null Save result info, or null if nothing to save.
	 * @throws \Automatic_CSS\Exceptions\Invalid_Form_Values If validation fails.
	 * @throws \Automatic_CSS\Exceptions\Insufficient_Permissions If user lacks permissions.
	 */
	public function save_settings( array $values, bool $trigger_css_generation = true );
}
