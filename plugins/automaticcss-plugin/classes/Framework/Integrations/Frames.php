<?php
/**
 * Frames integration.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Framework\Integrations;

use Automatic_CSS\Framework\IntegrationManager\IntegrationInterface;

/**
 * Frames integration.
 *
 * Note: The Frames-specific SCSS files were removed. This integration
 * now only provides detection of the Frames plugin for UI purposes.
 */
class Frames implements IntegrationInterface {

	/**
	 * Whether the integration is active.
	 *
	 * @return boolean
	 */
	public static function is_active() {
		include_once ABSPATH . 'wp-admin/includes/plugin.php';
		return is_plugin_active( 'frames-plugin/frames-plugin.php' );
	}

	/**
	 * Get the name of the integration.
	 *
	 * @return string
	 */
	public static function get_name() {
		return 'frames';
	}
}
