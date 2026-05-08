<?php
/**
 * Automatic.css Permissions helper file.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Helpers;

use Automatic_CSS\Framework\Integrations\Bricks;

/**
 * Automatic.css WordPress helper class
 */
class Permissions {

	public const ACSS_FULL_ACCESS_CAPABILITY = 'manage_options';

	/**
	 * Whether the user has acss access
	 *
	 * @var bool|null
	 */
	private $has_acss_access;

	/**
	 * Whether the user has bricks access
	 *
	 * @var bool|null
	 */
	private $has_bricks_access;

	/**
	 * Whether the user is logged in
	 *
	 * @var bool|null
	 */
	private $is_logged_in;

	/**
	 * Whether the user is a super admin
	 *
	 * @var bool|null
	 */
	private $is_super_admin;

	/**
	 * Whether the user has ACSS access
	 *
	 * @var bool|null
	 */
	private $has_acss_full_capability;

	/**
	 * Whether the user has Bricks access
	 *
	 * @var bool|null
	 */
	private $has_bricks_capability;

	/**
	 * Constructor
	 *
	 * @param array<string, bool> $options Array with keys: has_acss_access, has_bricks_access.
	 */
	public function __construct( array $options = array() ) {
		$this->has_acss_access = $options['has_acss_access'] ?? null;
		$this->has_bricks_access = $options['has_bricks_access'] ?? null;
		$this->is_logged_in = $options['is_logged_in'] ?? null;
		$this->is_super_admin = $options['is_super_admin'] ?? null;
		$this->has_acss_full_capability = $options['has_acss_full_capability'] ?? null;
		$this->has_bricks_capability = $options['has_bricks_capability'] ?? null;
	}

	/**
	 * Initialize the permissions
	 * Have to be initialized in the `init` hook because we need to check if the user is logged in.
	 *
	 * @return void
	 */
	public function init() {
		// Null checks are needed because we can dependency inject the permissions for testing.
		// STEP 1: basic logged in / account type checks.
		if ( null === $this->is_logged_in ) {
			$this->is_logged_in = \is_user_logged_in();
		}
		if ( null === $this->is_super_admin ) {
			$this->is_super_admin = \is_multisite() && \is_super_admin();
		}
		if ( null === $this->has_acss_full_capability ) {
			$this->has_acss_full_capability = \current_user_can( self::ACSS_FULL_ACCESS_CAPABILITY );
		}
		if ( null === $this->has_bricks_capability ) {
			$this->has_bricks_capability = Bricks::is_active() && class_exists( '\Bricks\Capabilities' ) && \Bricks\Capabilities::current_user_has_full_access();
		}
		// STEP 2: "compound" checks (i.e. checks that depend on the basic checks).
		if ( null === $this->has_acss_access ) {
			$this->has_acss_access = $this->should_have_acss_access();
		}
		if ( null === $this->has_bricks_access ) {
			$this->has_bricks_access = $this->should_have_bricks_access();
		}
	}

	/**
	 * Determine if the user should have ACSS access
	 *
	 * @return bool
	 */
	private function should_have_acss_access() {
		if ( $this->is_logged_in && $this->has_acss_full_capability ) {
			return true;
		}

		if ( $this->is_super_admin ) {
			return true;
		}

		return false;
	}

	/**
	 * Determine if the user should have Bricks access
	 *
	 * @return bool
	 */
	private function should_have_bricks_access() {
		if ( $this->is_logged_in && $this->has_bricks_capability ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if user has acss and builder access
	 *
	 * @return bool
	 */
	public function current_user_has_full_access() {
		return (bool) $this->has_acss_access() && $this->has_bricks_access();
	}

	/**
	 * Whether the user has ACSS access
	 *
	 * @return bool
	 */
	public function has_acss_access() {
		return $this->has_acss_access;
	}

	/**
	 * Whether the user has Bricks access
	 *
	 * @return bool
	 */
	public function has_bricks_access() {
		return $this->has_bricks_access;
	}
}
