<?php
/**
 * Fluent Forms integration.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Framework\Integrations;

use Automatic_CSS\Framework\IntegrationManager\InjectsScssVariableInterface;
use Automatic_CSS\Framework\IntegrationManager\IntegrationInterface;

/**
 * Fluent Forms integration.
 */
class FluentForms implements IntegrationInterface, InjectsScssVariableInterface {

	/**
	 * Check if Fluent Forms is active.
	 *
	 * @return boolean
	 */
	public static function is_active() {
		include_once ABSPATH . 'wp-admin/includes/plugin.php';
		return is_plugin_active( 'fluentform/fluentform.php' );
	}

	/**
	 * Get the name of the integration.
	 *
	 * @return string
	 */
	public static function get_name(): string {
		return 'fluent-forms';
	}

	/**
	 * Get the SCSS option name for this integration.
	 *
	 * @return string
	 */
	public static function get_scss_option_name(): string {
		return 'option-fluent-form';
	}
}
