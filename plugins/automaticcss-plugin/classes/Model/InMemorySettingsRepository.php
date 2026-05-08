<?php
/**
 * In-Memory Settings Repository for testing.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Model;

/**
 * In-memory implementation of SettingsRepositoryInterface.
 *
 * This class is intended for unit testing. It stores settings in memory
 * without any database interaction, allowing tests to run in isolation.
 *
 * Usage in tests:
 * ```php
 * $settings = new InMemorySettingsRepository([
 *     'color-primary' => '#ff0000',
 *     'option-bricks' => 'on',
 * ]);
 *
 * $container->bind( SettingsRepositoryInterface::class, $settings );
 * ```
 */
class InMemorySettingsRepository implements SettingsRepositoryInterface {

	/**
	 * The settings storage.
	 *
	 * @var array<string, mixed>
	 */
	private array $settings;

	/**
	 * Track if save was called (for assertions).
	 *
	 * @var bool
	 */
	private bool $save_called = false;

	/**
	 * Last values passed to save_settings (for assertions).
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $last_saved_values = null;

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $initial_settings Initial settings values.
	 */
	public function __construct( array $initial_settings = array() ) {
		$this->settings = $initial_settings;
	}

	/**
	 * Get all settings values.
	 *
	 * @return array<string, mixed>
	 */
	public function get_vars(): array {
		return $this->settings;
	}

	/**
	 * Get a specific setting value.
	 *
	 * @param string $var The setting name.
	 * @return mixed|null
	 */
	public function get_var( string $var ) {
		return $this->settings[ $var ] ?? null;
	}

	/**
	 * Save settings (stores in memory).
	 *
	 * This implementation does not validate or trigger CSS generation.
	 * It simply stores the values for testing purposes.
	 *
	 * @param array<string, mixed> $values The settings to save.
	 * @param bool                 $trigger_css_generation Ignored in this implementation.
	 * @return array{has_changed: bool, generated_files: array<string>, generated_files_number: int}|null
	 */
	public function save_settings( array $values, bool $trigger_css_generation = true ) {
		if ( empty( $values ) ) {
			return null;
		}

		$this->save_called = true;
		$this->last_saved_values = $values;

		$has_changed = $this->settings !== $values;
		$this->settings = $values;

		return array(
			'has_changed' => $has_changed,
			'generated_files' => array(),
			'generated_files_number' => 0,
		);
	}

	/**
	 * Set a single setting value (test helper).
	 *
	 * @param string $var The setting name.
	 * @param mixed  $value The value to set.
	 * @return void
	 */
	public function set_var( string $var, $value ): void {
		$this->settings[ $var ] = $value;
	}

	/**
	 * Check if save_settings was called (test assertion helper).
	 *
	 * @return bool
	 */
	public function was_save_called(): bool {
		return $this->save_called;
	}

	/**
	 * Get the last saved values (test assertion helper).
	 *
	 * @return array<string, mixed>|null
	 */
	public function get_last_saved_values(): ?array {
		return $this->last_saved_values;
	}

	/**
	 * Reset the test tracking state.
	 *
	 * @return void
	 */
	public function reset_tracking(): void {
		$this->save_called = false;
		$this->last_saved_values = null;
	}
}
