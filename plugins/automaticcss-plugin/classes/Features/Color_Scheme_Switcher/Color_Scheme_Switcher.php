<?php
/**
 * Automatic.css Color Scheme Switcher class file.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Features\Color_Scheme_Switcher;

use Automatic_CSS\Framework\AssetManager\DefaultAssetEnqueuing;
use Automatic_CSS\Framework\AssetManager\HasAssetsToEnqueueInterface;
use Automatic_CSS\Framework\ContextManager\Context;
use Automatic_CSS\Framework\IntegrationManager\IntegrationInterface;
use Automatic_CSS\Framework\Integrations\Bricks;
use Automatic_CSS\Helpers\CSS_File;
use Automatic_CSS\Helpers\JS_File;
use Automatic_CSS\Helpers\Permissions;
use Automatic_CSS\Model\Database_Settings;

/**
 * Builder Color Scheme Switcher class.
 */
class Color_Scheme_Switcher implements IntegrationInterface, HasAssetsToEnqueueInterface {

	use DefaultAssetEnqueuing; // Adds enqueue_assets() method.

	/**
	 * The permissions.
	 *
	 * @var Permissions
	 */
	private $permissions;

	/**
	 * JS File for frontend.
	 *
	 * @var JS_File
	 */
	private $color_scheme_frontend_js;

	/**
	 * CSS File for frontend.
	 *
	 * @var CSS_File
	 */
	private $color_scheme_frontend_css;

	/**
	 * JS File for builder.
	 *
	 * @var JS_File
	 */
	private $color_scheme_builder_js;

	/**
	 * CSS File for builder.
	 *
	 * @var CSS_File
	 */
	private $color_scheme_builder_css;

	/**
	 * Initialize the feature.
	 *
	 * @param Permissions|null                     $permissions The permissions.
	 * @param array<string, CSS_File|JS_File|null> $options Array with keys: frontend_js, frontend_css, builder_js, builder_css.
	 */
	public function __construct( $permissions = null, array $options = array() ) {
		$this->permissions = $permissions ?? new Permissions();
		$model = Database_Settings::get_instance();

		$this->color_scheme_frontend_js = $options['frontend_js'] ?? new JS_File(
			'color-scheme-switcher-frontend',
			ACSS_FEATURES_URL . '/Color_Scheme_Switcher/js/frontend.min.js',
			ACSS_FEATURES_DIR . '/Color_Scheme_Switcher/js/frontend.min.js',
			array(),
			true,
			'acss',
			array(
				'color_mode' => $model->get_var( 'website-color-scheme' ),
				'enable_client_color_preference' => 'on' === $model->get_var( 'option-prefers-color-scheme' ) ? 'true' : 'false',
			)
		);
		$this->color_scheme_frontend_css = $options['frontend_css'] ?? new CSS_File(
			'color-scheme-switcher-frontend',
			ACSS_FEATURES_URL . '/Color_Scheme_Switcher/css/frontend.css',
			ACSS_FEATURES_DIR . '/Color_Scheme_Switcher/css/frontend.css'
		);
		$this->color_scheme_builder_js = $options['builder_js'] ?? new JS_File(
			'color-scheme-switcher-bricks',
			ACSS_FEATURES_URL . '/Color_Scheme_Switcher/js/bricks.min.js',
			ACSS_FEATURES_DIR . '/Color_Scheme_Switcher/js/bricks.min.js',
		);
		$this->color_scheme_builder_css = $options['builder_css'] ?? new CSS_File(
			'color-scheme-switcher-bricks',
			ACSS_FEATURES_URL . '/Color_Scheme_Switcher/css/bricks.css',
			ACSS_FEATURES_DIR . '/Color_Scheme_Switcher/css/bricks.css'
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
				if ( ! $this->permissions->current_user_has_full_access() ) {
					return array();
				}
				$is_bricks_context = in_array( Bricks::class, $context->get_determiners() );
				if ( ! $is_bricks_context ) {
					return array();
				}
				return array(
					$this->color_scheme_builder_js,
					$this->color_scheme_builder_css,
				);
			case Context::FRONTEND:
				$is_bricks_context = in_array( Bricks::class, $context->get_determiners() );
				if ( ! $is_bricks_context ) {
					return array();
				}

				return array(
					$this->color_scheme_frontend_js,
					$this->color_scheme_frontend_css,
				);
			case Context::PREVIEW:
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
		return $acss_database->get_var( 'option-color-scheme-switcher-enable' ) === 'on' ?? false;
	}

	/**
	 * Get the name of the integration.
	 *
	 * @return string
	 */
	public static function get_name() {
		return 'color-scheme-switcher';
	}
}
