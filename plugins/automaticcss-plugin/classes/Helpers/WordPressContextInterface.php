<?php
/**
 * WordPress context interface.
 *
 * Provides an abstraction over WordPress environment functions
 * (is_admin, did_action, get_current_screen, etc.) to improve
 * testability by allowing dependency injection.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Helpers;

/**
 * Interface for accessing WordPress environment state.
 */
interface WordPressContextInterface {

	/**
	 * Check if we're in the WordPress admin area.
	 *
	 * @return bool True if in admin, false otherwise.
	 */
	public function is_admin(): bool;

	/**
	 * Get the number of times an action has been fired.
	 *
	 * @param string $action The action hook name.
	 * @return int The number of times the action has been fired.
	 */
	public function did_action( string $action ): int;

	/**
	 * Check if the current screen is the block editor.
	 *
	 * @return bool True if on block editor, false otherwise.
	 */
	public function is_block_editor(): bool;

	/**
	 * Check if the current screen is the site editor.
	 *
	 * @return bool True if on site editor, false otherwise.
	 */
	public function is_site_editor(): bool;
}
