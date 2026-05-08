<?php
/**
 * Default asset enqueuing trait.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Framework\AssetManager;

use Automatic_CSS\Framework\ContextManager\Context;
use Automatic_CSS\Helpers\CSS_File;
use Automatic_CSS\Helpers\JS_File;
use Automatic_CSS\Helpers\SCSS_File;

/**
 * Default asset enqueuing trait.
 *
 * @package Automatic_CSS
 */
trait DefaultAssetEnqueuing {

	/**
	 * Enqueue assets for the specified context.
	 *
	 * @param Context $context The context in which to enqueue assets.
	 * @return void
	 */
	public function enqueue_assets( Context $context ): void {
		foreach ( $this->get_assets_to_enqueue( $context ) as $asset ) {
			if ( $asset instanceof SCSS_File || $asset instanceof CSS_File ) {
				$asset->enqueue_stylesheet();
			} else if ( $asset instanceof JS_File ) {
				$asset->enqueue();
				$asset->localize();
			}
		}
	}
}
