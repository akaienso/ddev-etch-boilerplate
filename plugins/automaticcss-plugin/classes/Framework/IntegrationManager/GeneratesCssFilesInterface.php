<?php
/**
 * Interface for integrations that generate CSS files.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Framework\IntegrationManager;

/**
 * Interface GeneratesCssFilesInterface
 *
 * Integrations implementing this interface can have their CSS files
 * generated and deleted by the CSS_Engine.
 */
interface GeneratesCssFilesInterface {
	/**
	 * Get all CSS files managed by this integration.
	 *
	 * @return array<\Automatic_CSS\Helpers\SCSS_File> The CSS files.
	 */
	public function get_css_files(): array;

	/**
	 * Generate and save all registered stylesheets for the provided variables.
	 *
	 * @param array $variables The values for the framework's variables.
	 * @return array The handles of the generated CSS files.
	 */
	public function generate_own_css_files( array $variables ): array;
}
