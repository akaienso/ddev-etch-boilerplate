<?php
/**
 * Bricks builder class.
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
use Automatic_CSS\Helpers\RequestContext;
use Automatic_CSS\Helpers\RequestContextInterface;
use Automatic_CSS\Helpers\WordPress;

/**
 * Bricks builder class.
 */
class Bricks implements IntegrationInterface, HasAssetsToEnqueueInterface, DeterminesContextInterface, InjectsScssVariableInterface, GeneratesCssFilesInterface {

	use DefaultAssetEnqueuing; // Adds enqueue_assets() method.
	use GeneratesCssFilesTrait; // Adds generate_own_css_files() method.

	/**
	 * The CSS file.
	 *
	 * @var SCSS_File
	 */
	private $css_file;

	/**
	 * The CSS file for the builder.
	 *
	 * @var SCSS_File
	 */
	private $in_builder_css_file;

	/**
	 * The request context for accessing HTTP request parameters.
	 *
	 * @var RequestContextInterface
	 */
	private $request_context;

	/**
	 * Constructor.
	 *
	 * @param SCSS_File|null               $css_file The CSS file.
	 * @param SCSS_File|null               $in_builder_css_file The CSS file for the builder.
	 * @param RequestContextInterface|null $request_context The request context for HTTP parameters.
	 */
	public function __construct( $css_file = null, $in_builder_css_file = null, $request_context = null ) {
		$this->request_context = $request_context ?? new RequestContext();
		$this->css_file = $css_file ?? new SCSS_File(
			'automaticcss-bricks',
			'automatic-bricks.css',
			array(
				'source_file' => 'platforms/bricks/automatic-bricks.scss',
				'imports_folder' => 'platforms/bricks',
			),
			array(
				'deps' => apply_filters( 'automaticcss_bricks_deps', array( 'automaticcss-core' ) ),
			)
		);
		$this->in_builder_css_file = $in_builder_css_file ?? new SCSS_File(
			'automaticcss-bricks-in-builder',
			'automatic-bricks-in-builder.css',
			array(
				'source_file' => 'platforms/bricks/bricks-in-builder.scss',
				'imports_folder' => 'platforms/bricks',
			)
		);
	}

	/**
	 * Whether the builder is active.
	 *
	 * @return boolean
	 */
	public static function is_active() {
		include_once ABSPATH . 'wp-admin/includes/plugin.php';
		$theme = wp_get_theme(); // gets the current theme.
		return 'Bricks' === $theme->name || 'Bricks' === $theme->parent_theme;
	}

	/**
	 * Whether the context is the builder context.
	 *
	 * @return boolean
	 */
	public function is_builder_context() {
		$is_builder = 'run' === $this->request_context->get( 'bricks' );
		$is_preview = $this->is_preview_context();
		return $is_builder && ! $is_preview;
	}

	/**
	 * Whether the context is the preview context.
	 *
	 * @return boolean
	 */
	public function is_preview_context() {
		return $this->request_context->has_get( 'brickspreview' );
	}

	/**
	 * Whether the context is the frontend context.
	 *
	 * @return boolean
	 */
	public function is_frontend_context() {
		return ! $this->is_builder_context() && ! $this->is_preview_context() && WordPress::is_wp_frontend();
	}

	/**
	 * Enqueue the appropriate assets based on context.
	 *
	 * @param Context $context The context.
	 * @return array<SCSS_File> The assets to enqueue for the specified context.
	 */
	public function get_assets_to_enqueue( Context $context ): array {
		$is_my_context = in_array( self::class, $context->get_determiners() );
		switch ( $context->get_context() ) {
			case 'builder':
				if ( ! $is_my_context ) {
					return array();
				}
				return array(
					$this->in_builder_css_file,
				);
			case 'preview':
				if ( ! $is_my_context ) {
					return array();
				}
				return array(
					$this->css_file,
				);
			case 'frontend':
				return array(
					$this->css_file,
				);
			default:
				// In unknown context, don't enqueue anything.
				return array();
		}
	}

	/**
	 * Get the name of the integration.
	 *
	 * @return string
	 */
	public static function get_name() {
		return 'bricks';
	}

	/**
	 * Get the SCSS option name for this integration.
	 *
	 * @return string
	 */
	public static function get_scss_option_name(): string {
		return 'option-bricks';
	}

	/**
	 * Get all CSS files managed by this integration.
	 *
	 * @return array<SCSS_File> The CSS files.
	 */
	public function get_css_files(): array {
		return array(
			$this->css_file,
			$this->in_builder_css_file,
		);
	}
}
