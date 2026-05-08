<?php
/**
 * Automatic.css CSS CLI Command.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\CLI;

use Automatic_CSS\CSS_Engine\GenerationOrchestrator;
use Automatic_CSS\Exceptions\CSS_Generation_Failed;
use Automatic_CSS\Helpers\Timer;
use Automatic_CSS\Model\Database_Settings;
use WP_CLI;

/**
 * Manage Automatic.css stylesheets.
 *
 * ## EXAMPLES
 *
 *     # Regenerate all CSS files
 *     $ wp acss css regenerate
 *     Generated files:
 *       - /wp-content/uploads/automatic-css/automatic.css
 *       - /wp-content/uploads/automatic-css/automatic-variables.css
 *     Success: 2 CSS files regenerated in 1.23 seconds.
 */
class CSS_Command {

	/**
	 * Regenerate all CSS files from current settings.
	 *
	 * Regenerates all Automatic.css stylesheets using the current saved settings
	 * without modifying any settings values.
	 *
	 * ## EXAMPLES
	 *
	 *     # Regenerate CSS files
	 *     $ wp acss css regenerate
	 *     Generated files:
	 *       - /wp-content/uploads/automatic-css/automatic.css
	 *       - /wp-content/uploads/automatic-css/automatic-variables.css
	 *     Success: 2 CSS files regenerated in 1.23 seconds.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function regenerate( $args, $assoc_args ): void {
		$timer    = new Timer();
		$settings = Database_Settings::get_instance()->get_vars();

		if ( empty( $settings ) ) {
			WP_CLI::error( 'No settings found. Please configure Automatic.css first.' );
		}

		WP_CLI::log( 'Regenerating CSS files...' );

		try {
			$orchestrator = GenerationOrchestrator::get_instance();
			$generated    = $orchestrator->generate( $settings );

			if ( empty( $generated ) ) {
				WP_CLI::warning( 'No CSS files were generated.' );
				return;
			}

			// List generated files.
			WP_CLI::log( 'Generated files:' );
			foreach ( $generated as $file ) {
				WP_CLI::log( sprintf( '  - %s', $file ) );
			}

			WP_CLI::success(
				sprintf(
					'%d CSS file(s) regenerated in %s seconds.',
					count( $generated ),
					$timer->get_time()
				)
			);
		} catch ( CSS_Generation_Failed $e ) {
			WP_CLI::error( sprintf( 'CSS generation failed: %s', $e->getMessage() ) );
		} catch ( \Exception $e ) {
			WP_CLI::error( sprintf( 'Error: %s', $e->getMessage() ) );
		}
	}
}
