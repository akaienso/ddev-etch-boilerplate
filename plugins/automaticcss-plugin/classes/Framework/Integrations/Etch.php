<?php
/**
 * Etch integration.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Framework\Integrations;

use Automatic_CSS\Helpers\SCSS_File;
use Automatic_CSS\Framework\AssetManager\DefaultAssetEnqueuing;
use Automatic_CSS\Framework\AssetManager\HasAssetsToEnqueueInterface;
use Automatic_CSS\Framework\ContextManager\Context;
use Automatic_CSS\Framework\ContextManager\DeterminesContextInterface;
use Automatic_CSS\Framework\IntegrationManager\GeneratesCssFilesInterface;
use Automatic_CSS\Framework\IntegrationManager\GeneratesCssFilesTrait;
use Automatic_CSS\Framework\IntegrationManager\InjectsScssVariableInterface;
use Automatic_CSS\Framework\IntegrationManager\IntegrationInterface;
use Automatic_CSS\Features\Visible_Animations\Visible_Animations;
use Automatic_CSS\Helpers\RequestContext;
use Automatic_CSS\Helpers\RequestContextInterface;
use Automatic_CSS\Helpers\WordPress;
use Automatic_CSS\Plugin;

/**
 * Etch integration.
 */
class Etch implements IntegrationInterface, HasAssetsToEnqueueInterface, DeterminesContextInterface, InjectsScssVariableInterface, GeneratesCssFilesInterface {

	use DefaultAssetEnqueuing; // Adds enqueue_assets() method.
	use GeneratesCssFilesTrait; // Adds generate_own_css_files() method.

	/**
	 * The request context for accessing HTTP request parameters.
	 *
	 * @var RequestContextInterface
	 */
	private $request_context;

	/**
	 * Constructor.
	 *
	 * @param RequestContextInterface|null $request_context The request context for HTTP parameters.
	 */
	public function __construct( $request_context = null ) {
		$this->request_context = $request_context ?? new RequestContext();
		add_filter( 'etch/canvas/additional_stylesheets', array( $this, 'enqueue_preview_assets' ) );
		add_action( 'etch/canvas/enqueue_assets', array( $this, 'enqueue_canvas_scripts' ) );
	}

	/**
	 * Is the Etch platform active?
	 *
	 * @return boolean
	 */
	public static function is_active() {
		include_once ABSPATH . 'wp-admin/includes/plugin.php';
		return is_plugin_active( 'etch/etch.php' );
	}

	/**
	 * Is the builder context?
	 *
	 * @return boolean
	 */
	public function is_builder_context() {
		$is_builder = (bool) $this->request_context->get( 'etch' );
		$is_preview = $this->is_preview_context();
		return $is_builder && ! $is_preview;
	}

	/**
	 * Is the preview context?
	 *
	 * @return boolean
	 */
	public function is_preview_context() {
		return false;
	}

	/**
	 * Is the frontend context?
	 *
	 * @return boolean
	 */
	public function is_frontend_context() {
		return ! $this->is_builder_context() && ! $this->is_preview_context() && WordPress::is_wp_frontend();
	}

	/**
	 * Enqueue assets for the given context.
	 *
	 * @param Context $context The context to enqueue assets for.
	 * @return array<SCSS_File> The assets to enqueue for the specified context.
	 */
	public function get_assets_to_enqueue( Context $context ): array {
		return array();
	}

	/**
	 * Enqueue the preview assets.
	 *
	 * @param array<array{id: string, url: string}> $additional_stylesheets The stylesheets to add to Etch's preview.
	 * @return array The (possibly modified) stylesheets to add to Etch's preview.
	 */
	public function enqueue_preview_assets( $additional_stylesheets = array() ) {
		// No self::is_preview_context() because it doesn't work with Etch.
		// We trust that the action is only called when we're in the preview.
		$asset_manager = Plugin::get_instance()->asset_manager;
		$context = new Context( Context::PREVIEW, true, array( self::class ) );
		// @var array<SCSS_File> $stylesheets
		$assets = $asset_manager->get_assets_to_enqueue( $context );
		$stylesheets = array_filter(
			$assets,
			function ( $asset ) {
				return $asset instanceof SCSS_File;
			}
		);
		foreach ( $stylesheets as $stylesheet ) {
			$additional_stylesheets[] = array(
				'id' => $stylesheet->handle,
				'url' => $stylesheet->file_url,
			);
		}
		return apply_filters( 'acss/etch/additional_stylesheets', $additional_stylesheets );
	}

	/**
	 * Enqueue JS assets into the Etch canvas.
	 *
	 * The Etch canvas is an isolated context that doesn't receive
	 * assets from wp_enqueue_scripts. This hook ensures feature
	 * scripts (like Intersection Observer for On Visible effects)
	 * are loaded inside the canvas.
	 *
	 * @return void
	 */
	public function enqueue_canvas_scripts() {
		if ( Visible_Animations::is_active() ) {
			wp_enqueue_script(
				'acss-visible-animations',
				ACSS_FEATURES_URL . '/Visible_Animations/js/visible-animations.min.js',
				array(),
				filemtime( ACSS_FEATURES_DIR . '/Visible_Animations/js/visible-animations.min.js' ),
				true
			);
		}
	}

	/**
	 * Get the name of the integration.
	 *
	 * @return string
	 */
	public static function get_name() {
		return 'etch';
	}

	/**
	 * Get the SCSS option name for this integration.
	 *
	 * @return string
	 */
	public static function get_scss_option_name(): string {
		return 'option-etch';
	}
}
