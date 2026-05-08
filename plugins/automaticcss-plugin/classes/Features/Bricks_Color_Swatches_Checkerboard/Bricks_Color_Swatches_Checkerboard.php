<?php
/**
 * Automatic.css Bricks_Color_Swatches_Checkerboard class file.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Features\Bricks_Color_Swatches_Checkerboard;

use Automatic_CSS\Framework\AssetManager\DefaultAssetEnqueuing;
use Automatic_CSS\Framework\AssetManager\HasAssetsToEnqueueInterface;
use Automatic_CSS\Framework\ContextManager\Context;
use Automatic_CSS\Framework\IntegrationManager\IntegrationInterface;
use Automatic_CSS\Framework\Integrations\Bricks;
use Automatic_CSS\Helpers\CSS_File;
use Automatic_CSS\Model\Database_Settings;

/**
 * Builder Bricks_Color_Swatches_Checkerboard class.
 */
class Bricks_Color_Swatches_Checkerboard implements IntegrationInterface, HasAssetsToEnqueueInterface {

	use DefaultAssetEnqueuing; // Adds enqueue_assets() method.

	/**
	 * CSS File for builder.
	 *
	 * @var CSS_File
	 */
	private $builder_css;

	/**
	 * Initialize the feature.
	 *
	 * @param array<string, CSS_File|JS_File|null> $options Array with keys: builder_js.
	 */
	public function __construct( array $options = array() ) {
		$this->builder_css = $options['builder_css'] ?? new CSS_File(
			'bricks-color-swatches-checkerboard',
			ACSS_FEATURES_URL . '/Bricks_Color_Swatches_Checkerboard/css/checkerboard.css',
			ACSS_FEATURES_DIR . '/Bricks_Color_Swatches_Checkerboard/css/checkerboard.css',
		);
	}

	/**
	 * Enqueue the assets
	 *
	 * @param Context $context The context in which the assets are being enqueued.
	 */
	public function get_assets_to_enqueue( Context $context ): array {
		switch ( $context->get_context() ) {
			case Context::BUILDER:
				$is_bricks_context = in_array( Bricks::class, $context->get_determiners() );
				if ( ! $is_bricks_context ) {
					return array();
				}
				return array(
					$this->builder_css,
				);
			case Context::PREVIEW:
			case Context::FRONTEND:
			default:
				return array();
		}
	}

	/**
	 * Whether the integration is active.
	 *
	 * @return boolean
	 */
	public static function is_active() {
		$acss_database = Database_Settings::get_instance();
		return $acss_database->get_var( 'option-bricks-color-swatches-checkerboard-enable' ) === 'on' ?? false;
	}

	/**
	 * Get the name of the integration.
	 *
	 * @return string
	 */
	public static function get_name() {
		return 'bricks-color-swatches-checkerboard';
	}
}
