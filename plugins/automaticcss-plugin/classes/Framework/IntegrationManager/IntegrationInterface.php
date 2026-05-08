<?php
/**
 * Interface for integrations managed by IntegrationsManager.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Framework\IntegrationManager;

interface IntegrationInterface {
	/**
	 * Check if the integration is active.
	 *
	 * @return bool
	 */
	public static function is_active();
}
