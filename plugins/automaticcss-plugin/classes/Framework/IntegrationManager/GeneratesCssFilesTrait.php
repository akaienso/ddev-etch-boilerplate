<?php
/**
 * Trait for integrations that generate CSS files.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Framework\IntegrationManager;

use Automatic_CSS\Exceptions\CSS_Generation_Failed;
use Automatic_CSS\Helpers\Logger;
use Automatic_CSS\Helpers\Timer;
use Automatic_CSS\Helpers\SCSS_File;

/**
 * Trait GeneratesCssFilesTrait
 *
 * Provides default implementation for GeneratesCssFilesInterface.
 * Classes using this trait must define a get_all_css_file_properties() method
 * that returns an array of property names containing SCSS_File instances.
 */
trait GeneratesCssFilesTrait {
	/**
	 * Get all CSS files managed by this integration.
	 *
	 * Classes using this trait should override this method to return their CSS files.
	 *
	 * @return array<SCSS_File> The CSS files.
	 */
	public function get_css_files(): array {
		return array();
	}

	/**
	 * Generate and save all registered stylesheets for the provided variables.
	 *
	 * @param array $variables The values for the framework's variables.
	 * @return array The handles of the generated CSS files.
	 * @throws CSS_Generation_Failed If any SCSS file fails to compile.
	 */
	public function generate_own_css_files( array $variables ): array {
		$generated_files = array();
		$css_files = $this->get_css_files();
		if ( is_array( $css_files ) && ! empty( $css_files ) ) {
			foreach ( $css_files as $css_file ) {
				if ( is_a( $css_file, SCSS_File::class ) && $css_file->is_enabled() ) {
					$css_timer = new Timer();
					// Let CSS_Generation_Failed propagate up - caller should handle it.
					if ( false !== $css_file->save_file_from_variables( $variables ) ) {
						Logger::log( sprintf( '%s: generated %s in %s seconds', __METHOD__, $css_file->handle, $css_timer->get_time() ) );
						$generated_files[] = $css_file->handle;
					}
				}
			}
		}
		return $generated_files;
	}
}
