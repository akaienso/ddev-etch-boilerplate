<?php
/**
 * WS Form integration.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Framework\Integrations;

use Automatic_CSS\Framework\IntegrationManager\InjectsScssVariableInterface;
use Automatic_CSS\Framework\IntegrationManager\IntegrationInterface;

/**
 * WS Form integration.
 */
class WSForms implements IntegrationInterface, InjectsScssVariableInterface {

	/**
	 * Check if WS Form is active.
	 *
	 * @return boolean
	 */
	public static function is_active() {
		include_once ABSPATH . 'wp-admin/includes/plugin.php';
		return is_plugin_active( 'ws-form/ws-form.php' ) || is_plugin_active( 'ws-form-pro/ws-form.php' );
	}

	/**
	 * Get the name of the integration.
	 *
	 * @return string
	 */
	public static function get_name(): string {
		return 'ws-form';
	}

	/**
	 * Get the SCSS option name for this integration.
	 *
	 * @return string
	 */
	public static function get_scss_option_name(): string {
		return 'option-ws-form';
	}
}
