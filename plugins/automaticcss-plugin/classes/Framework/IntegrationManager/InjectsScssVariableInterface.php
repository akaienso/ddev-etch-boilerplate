<?php
/**
 * Interface for integrations that inject SCSS variables.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Framework\IntegrationManager;

/**
 * Interface InjectsScssVariableInterface
 *
 * Integrations implementing this interface will have their SCSS variable
 * automatically injected by the IntegrationsManager.
 */
interface InjectsScssVariableInterface {
	/**
	 * Get the SCSS option name for this integration.
	 *
	 * The returned value will be used as the variable name in SCSS compilation.
	 * For example, returning 'option-bricks' will set $option-bricks: on; in SCSS.
	 *
	 * @return string The SCSS option name (e.g., 'option-bricks', 'option-etch').
	 */
	public static function get_scss_option_name(): string;
}
