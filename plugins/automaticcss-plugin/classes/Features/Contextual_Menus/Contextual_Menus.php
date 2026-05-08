<?php
/**
 * Automatic.css Contextual Menus class file.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Features\Contextual_Menus;

use Automatic_CSS\Framework\AssetManager\DefaultAssetEnqueuing;
use Automatic_CSS\Framework\AssetManager\HasAssetsToEnqueueInterface;
use Automatic_CSS\Framework\ContextManager\Context;
use Automatic_CSS\Framework\IntegrationManager\IntegrationInterface;
use Automatic_CSS\Framework\Integrations\Bricks;
use Automatic_CSS\Helpers\CSS_File;
use Automatic_CSS\Helpers\Flag;
use Automatic_CSS\Helpers\JS_File;
use Automatic_CSS\Helpers\Permissions;
use Automatic_CSS\Model\Database_Settings;

/**
 * Builder Contextual Menus class.
 */
class Contextual_Menus implements IntegrationInterface, HasAssetsToEnqueueInterface {

	use DefaultAssetEnqueuing; // Adds enqueue_assets() method.

	/**
	 * The permissions.
	 *
	 * @var Permissions
	 */
	private $permissions;

	/**
	 * The JS file.
	 *
	 * @var JS_File
	 */
	private $context_menu_js_file;

	/**
	 * The CSS file.
	 *
	 * @var CSS_File
	 */
	private $context_menu_css_file;

	/**
	 * Initialize the feature.
	 *
	 * @param Permissions   $permissions The permissions.
	 * @param JS_File|null  $context_menu_js_file The JS file.
	 * @param CSS_File|null $context_menu_css_file The CSS file.
	 */
	public function __construct( $permissions = null, $context_menu_js_file = null, $context_menu_css_file = null ) {
		$this->permissions = $permissions ?? new Permissions();
		add_filter( 'script_loader_tag', array( $this, 'add_type_attribute' ), 10, 3 );
		$this->context_menu_js_file = $context_menu_js_file ?? new JS_File(
			'class-context-menu',
			ACSS_FEATURES_URL . '/Contextual_Menus/js/main.min.js',
			ACSS_FEATURES_DIR . '/Contextual_Menus/js/main.min.js',
			array(),
			true,
			'p_acssSettings_object',
			array(
				'settings' => get_option( 'automatic_css_settings' ),
			)
		);
		$this->context_menu_css_file = $context_menu_css_file ?? new CSS_File(
			'context-menu-css',
			ACSS_FEATURES_URL . '/Contextual_Menus/css/style.css',
			ACSS_FEATURES_DIR . '/Contextual_Menus/css/style.css'
		);
	}

	/**
	 * Enqueue the assets
	 *
	 * @param Context $context The context in which the assets are being enqueued.
	 */
	public function get_assets_to_enqueue( Context $context ): array {
		if ( ! $this->permissions->current_user_has_full_access() ) {
			return array();
		}
		switch ( $context->get_context() ) {
			// TODO: load the builder-specific assets too:
			// - balloon.css in Gutenberg only (since we no longer support Oxygen)
			// - bricks-enlarge-inputs.css in Bricks only.
			// BUT CHECK THAT THESE ARE NEEDED FIRST.
			case Context::BUILDER:
				return array(
					$this->context_menu_js_file,
					$this->context_menu_css_file,
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
		return $acss_database->get_var( 'option-contextual-menus-enable' ) === 'on' ?? false;
	}

	/**
	 * Get the name of the integration.
	 *
	 * @return string
	 */
	public static function get_name() {
		return 'contextual-menus';
	}

	/**
	 * Adds 'type="module"' to the script tag
	 *
	 * @param string $tag The original script tag.
	 * @param string $handle The script handle.
	 * @param string $src The script source.
	 * @return string
	 */
	public static function add_type_attribute( $tag, $handle, $src ) {
		if ( 'acss-class-context-menu' === $handle ) {
			$load_as_module =
				Flag::is_on( 'LOAD_DASHBOARD_SCRIPTS_AS_MODULE' ) || Flag::is_on( 'LOAD_DASHBOARD_FROM_VITE' ) ?
				' type="module"' :
				'';
			$tag = '<script' . $load_as_module . ' src="' . esc_url( $src ) . '"></script>'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
		}
		// change the script tag by adding type="module" and return it.

		return $tag;
	}
}
