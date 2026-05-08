<?php
/**
 * In-memory WordPress context implementation.
 *
 * Test double for WordPressContextInterface that uses in-memory state
 * instead of WordPress functions. Useful for unit testing.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Helpers;

/**
 * In-memory WordPress context for testing.
 */
class InMemoryWordPressContext implements WordPressContextInterface {

	/**
	 * Whether we're in admin context.
	 *
	 * @var bool
	 */
	private $is_admin;

	/**
	 * Action counts by action name.
	 *
	 * @var array<string, int>
	 */
	private $action_counts;

	/**
	 * Whether we're on the block editor.
	 *
	 * @var bool
	 */
	private $is_block_editor;

	/**
	 * Whether we're on the site editor.
	 *
	 * @var bool
	 */
	private $is_site_editor;

	/**
	 * Constructor.
	 *
	 * @param bool               $is_admin       Whether in admin context.
	 * @param array<string, int> $action_counts  Action counts by name.
	 * @param bool               $is_block_editor Whether on block editor.
	 * @param bool               $is_site_editor  Whether on site editor.
	 */
	public function __construct(
		bool $is_admin = false,
		array $action_counts = array(),
		bool $is_block_editor = false,
		bool $is_site_editor = false
	) {
		$this->is_admin = $is_admin;
		$this->action_counts = $action_counts;
		$this->is_block_editor = $is_block_editor;
		$this->is_site_editor = $is_site_editor;
	}

	/**
	 * Check if we're in the WordPress admin area.
	 *
	 * @return bool True if in admin, false otherwise.
	 */
	public function is_admin(): bool {
		return $this->is_admin;
	}

	/**
	 * Get the number of times an action has been fired.
	 *
	 * @param string $action The action hook name.
	 * @return int The number of times the action has been fired.
	 */
	public function did_action( string $action ): int {
		return $this->action_counts[ $action ] ?? 0;
	}

	/**
	 * Check if the current screen is the block editor.
	 *
	 * @return bool True if on block editor, false otherwise.
	 */
	public function is_block_editor(): bool {
		return $this->is_block_editor;
	}

	/**
	 * Check if the current screen is the site editor.
	 *
	 * @return bool True if on site editor, false otherwise.
	 */
	public function is_site_editor(): bool {
		return $this->is_site_editor;
	}

	/**
	 * Set whether we're in admin context.
	 *
	 * @param bool $is_admin Whether in admin.
	 * @return self For method chaining.
	 */
	public function set_is_admin( bool $is_admin ): self {
		$this->is_admin = $is_admin;
		return $this;
	}

	/**
	 * Set the action count for a specific action.
	 *
	 * @param string $action The action name.
	 * @param int    $count  The count.
	 * @return self For method chaining.
	 */
	public function set_action_count( string $action, int $count ): self {
		$this->action_counts[ $action ] = $count;
		return $this;
	}

	/**
	 * Set whether we're on the block editor.
	 *
	 * @param bool $is_block_editor Whether on block editor.
	 * @return self For method chaining.
	 */
	public function set_is_block_editor( bool $is_block_editor ): self {
		$this->is_block_editor = $is_block_editor;
		return $this;
	}

	/**
	 * Set whether we're on the site editor.
	 *
	 * @param bool $is_site_editor Whether on site editor.
	 * @return self For method chaining.
	 */
	public function set_is_site_editor( bool $is_site_editor ): self {
		$this->is_site_editor = $is_site_editor;
		return $this;
	}
}
