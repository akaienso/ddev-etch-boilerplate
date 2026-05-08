<?php
/**
 * Automatic.css Visible Animations class file.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Features\Visible_Animations;

use Automatic_CSS\Framework\AssetManager\DefaultAssetEnqueuing;
use Automatic_CSS\Framework\AssetManager\HasAssetsToEnqueueInterface;
use Automatic_CSS\Framework\ContextManager\Context;
use Automatic_CSS\Framework\IntegrationManager\IntegrationInterface;
use Automatic_CSS\Helpers\JS_File;
use Automatic_CSS\Model\Database_Settings;

/**
 * Visible Animations class.
 *
 * Provides Intersection Observer-based animations that play once
 * when elements become visible in the viewport.
 */
class Visible_Animations implements IntegrationInterface, HasAssetsToEnqueueInterface {

	use DefaultAssetEnqueuing; // Adds enqueue_assets() method.

	/**
	 * JS File for frontend.
	 *
	 * @var JS_File
	 */
	private $frontend_js;

	/**
	 * Initialize the feature.
	 *
	 * @param array<string, JS_File|null> $options Array with keys: frontend_js.
	 */
	public function __construct( array $options = array() ) {
		$this->frontend_js = $options['frontend_js'] ?? new JS_File(
			'acss-visible-animations',
			ACSS_FEATURES_URL . '/Visible_Animations/js/visible-animations.min.js',
			ACSS_FEATURES_DIR . '/Visible_Animations/js/visible-animations.min.js',
			array(),
			true
		);
	}

	/**
	 * Get the assets to enqueue.
	 *
	 * @param Context $context The context in which the assets are being enqueued.
	 * @return array<JS_File>
	 */
	public function get_assets_to_enqueue( Context $context ): array {
		// Only enqueue on frontend.
		if ( Context::FRONTEND !== $context->get_context() ) {
			return array();
		}

		return array(
			$this->frontend_js,
		);
	}

	/**
	 * Whether the integration is active.
	 *
	 * Check if any visible animation option is enabled.
	 *
	 * @return boolean
	 */
	public static function is_active() {
		$acss_database = Database_Settings::get_instance();

		$visible_options = array(
			'option-visible-fade',
			'option-visible-float',
			'option-visible-sink',
			'option-visible-slide',
			'option-visible-grow',
			'option-visible-shrink',
			'option-visible-blur',
		);

		foreach ( $visible_options as $option ) {
			if ( 'on' === $acss_database->get_var( $option ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the name of the integration.
	 *
	 * @return string
	 */
	public static function get_name() {
		return 'visible-animations';
	}
}
