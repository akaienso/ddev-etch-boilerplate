<?php
/**
 * WordPress context implementation.
 *
 * Default implementation that wraps WordPress functions.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Helpers;

/**
 * Default WordPress context implementation.
 */
class WordPressContext implements WordPressContextInterface {

	/**
	 * Check if we're in the WordPress admin area.
	 *
	 * @return bool True if in admin, false otherwise.
	 */
	public function is_admin(): bool {
		return is_admin();
	}

	/**
	 * Get the number of times an action has been fired.
	 *
	 * @param string $action The action hook name.
	 * @return int The number of times the action has been fired.
	 */
	public function did_action( string $action ): int {
		return did_action( $action );
	}

	/**
	 * Check if the current screen is the block editor.
	 *
	 * @return bool True if on block editor, false otherwise.
	 */
	public function is_block_editor(): bool {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}
		$current_screen = get_current_screen();
		if ( null === $current_screen ) {
			return false;
		}
		return method_exists( $current_screen, 'is_block_editor' ) && $current_screen->is_block_editor();
	}

	/**
	 * Check if the current screen is the site editor.
	 *
	 * @return bool True if on site editor, false otherwise.
	 */
	public function is_site_editor(): bool {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}
		$current_screen = get_current_screen();
		if ( null === $current_screen ) {
			return false;
		}
		return 'site-editor' === $current_screen->base;
	}
}
