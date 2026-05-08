<?php
/**
 * Automatic.css Contextual Menus class file.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Features\Bem_Class_Generator;

use Automatic_CSS\Framework\AssetManager\DefaultAssetEnqueuing;
use Automatic_CSS\Framework\AssetManager\HasAssetsToEnqueueInterface;
use Automatic_CSS\Framework\ContextManager\Context;
use Automatic_CSS\Framework\IntegrationManager\IntegrationInterface;
use Automatic_CSS\Framework\Integrations\Bricks;
use Automatic_CSS\Helpers\Flag;
use Automatic_CSS\Helpers\JS_File;
use Automatic_CSS\Helpers\Permissions;
use Automatic_CSS\Model\Database_Settings;

/**
 * Builder Contextual Menus class.
 */
class Bem_Class_Generator implements IntegrationInterface, HasAssetsToEnqueueInterface {

	use DefaultAssetEnqueuing; // Adds enqueue_assets() method.

	/**
	 * The permissions.
	 *
	 * @var Permissions
	 */
	private $permissions;

	/**
	 * JS File for builder.
	 *
	 * @var JS_File
	 */
	private $builder_js;

	/**
	 * Initialize the feature.
	 *
	 * @param Permissions|null                     $permissions The permissions.
	 * @param array<string, CSS_File|JS_File|null> $options Array with keys: builder_js.
	 */
	public function __construct( $permissions = null, array $options = array() ) {
		$this->permissions = $permissions ?? new Permissions();
		$this->builder_js = $options['builder_js'] ?? new JS_File(
			'bem-class-generator',
			ACSS_FEATURES_URL . '/Bem_Class_Generator/js/bem-class-generator.min.js',
			ACSS_FEATURES_DIR . '/Bem_Class_Generator/js/bem-class-generator.min.js',
		);

		add_filter( 'script_loader_tag', array( $this, 'add_type_attribute' ), 10, 3 );
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
		if ( 'acss-bem-class-generator' === $handle ) {
			$load_as_module =
			Flag::is_on( 'LOAD_DASHBOARD_SCRIPTS_AS_MODULE' ) || Flag::is_on( 'LOAD_DASHBOARD_FROM_VITE' ) ?
			' type="module"' :
			'';
			$tag = '<script' . $load_as_module . ' src="' . esc_url( $src ) . '"></script>'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
		}

		return $tag;
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
			case Context::BUILDER:
				$is_bricks_context = in_array( Bricks::class, $context->get_determiners() );
				if ( ! $is_bricks_context ) {
					return array();
				}
				return array(
					$this->builder_js,
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
		return $acss_database->get_var( 'option-bem-class-generator-enable' ) === 'on' ?? false;
	}

	/**
	 * Get the name of the integration.
	 *
	 * @return string
	 */
	public static function get_name() {
		return 'bem-class-generator';
	}
}
