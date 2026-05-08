<?php
/**
 * Gutenberg integration.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Framework\Integrations;

use Automatic_CSS\Helpers\SCSS_File;
use Automatic_CSS\Framework\AssetManager\HasAssetsToEnqueueInterface;
use Automatic_CSS\Framework\ContextManager\Context;
use Automatic_CSS\Framework\ContextManager\DeterminesContextInterface;
use Automatic_CSS\Framework\IntegrationManager\GeneratesCssFilesInterface;
use Automatic_CSS\Framework\IntegrationManager\GeneratesCssFilesTrait;
use Automatic_CSS\Framework\IntegrationManager\IntegrationInterface;
use Automatic_CSS\Helpers\Flag;
use Automatic_CSS\Helpers\JS_File;
use Automatic_CSS\Helpers\WideEventLogger;
use Automatic_CSS\Helpers\WordPress;
use Automatic_CSS\Helpers\WordPressContext;
use Automatic_CSS\Helpers\WordPressContextInterface;
use Automatic_CSS\Model\Database_Settings;
use Automatic_CSS\Model\SettingsRepositoryInterface;
use Automatic_CSS\Plugin;

/**
 * Gutenberg integration.
 */
class Gutenberg implements IntegrationInterface, HasAssetsToEnqueueInterface, DeterminesContextInterface, GeneratesCssFilesInterface {

	use GeneratesCssFilesTrait; // Adds generate_own_css_files() method.

	/**
	 * Instance of the overrides CSS file
	 *
	 * @var SCSS_File
	 */
	private $overrides_css_file;

	/**
	 * Instance of the editor CSS file
	 *
	 * @var SCSS_File
	 */
	private $editor_css_file;

	/**
	 * Instance of the Custom CSS file
	 *
	 * @var SCSS_File
	 */
	private $custom_css_file;

	/**
	 * Instance of the editor CSS file for iframed editor.
	 *
	 * @var SCSS_File
	 */
	private $editor_iframed_css_file;

	/**
	 * Instance of the color palette CSS file
	 *
	 * @var SCSS_File
	 */
	private $color_palette_css_file;

	/**
	 * Instance of the fix block editor RFS JS file
	 *
	 * @var JS_File
	 */
	private $fix_block_editor_rfs_js_file;

	/**
	 * Instance of the fix metabox WYSIWYG JS file
	 *
	 * @var JS_File
	 */
	private $fix_metabox_wysiwyg_js_file;

	/**
	 * Stores the root font size.
	 *
	 * @var string
	 */
	private $root_font_size;

	/**
	 * Whether to load Gutenberg styles using enqueue_stylesheet().
	 *
	 * @var boolean
	 */
	private $load_gutenberg_styles_using_enqueue_stylesheet;

	/**
	 * The WordPress context for accessing environment state.
	 *
	 * @var WordPressContextInterface
	 */
	private $wp_context;

	/**
	 * The settings repository for accessing plugin settings.
	 *
	 * @var SettingsRepositoryInterface
	 */
	private $settings;

	/**
	 * Cached context debug data for deferred logging.
	 *
	 * @var array<string, mixed>
	 */
	private $context_debug_data = array();

	/**
	 * Whether color palette generation is enabled.
	 *
	 * @var bool
	 */
	private $is_generate_color_palette_enabled;

	/**
	 * Constructor.
	 *
	 * @param SCSS_File|null                   $overrides_css_file The overrides CSS file.
	 * @param SCSS_File|null                   $editor_css_file The editor CSS file.
	 * @param SCSS_File|null                   $color_palette_css_file The color palette CSS file.
	 * @param SCSS_File|null                   $custom_css_file The ACSS Custom CSS file.
	 * @param JS_File|null                     $fix_block_editor_rfs_js_file The fix block editor RFS JS file.
	 * @param JS_File|null                     $fix_metabox_wysiwyg_js_file The fix metabox WYSIWYG JS file.
	 * @param boolean|null                     $load_gutenberg_styles_using_enqueue_stylesheet Whether to load Gutenberg styles using enqueue_stylesheet().
	 * @param WordPressContextInterface|null   $wp_context The WordPress context for environment state.
	 * @param SettingsRepositoryInterface|null $settings The settings repository for plugin settings.
	 */
	public function __construct(
		$overrides_css_file = null,
		$editor_css_file = null,
		$color_palette_css_file = null,
		$custom_css_file = null,
		$fix_block_editor_rfs_js_file = null,
		$fix_metabox_wysiwyg_js_file = null,
		$load_gutenberg_styles_using_enqueue_stylesheet = null,
		$wp_context = null,
		$settings = null
	) {
		$this->wp_context = $wp_context ?? new WordPressContext();
		$this->settings = $settings ?? Database_Settings::get_instance();
		$this->overrides_css_file = $overrides_css_file ?? new SCSS_File(
			'automaticcss-gutenberg',
			'automatic-gutenberg.css',
			array(
				'source_file' => 'platforms/gutenberg/automatic-gutenberg.scss',
				'imports_folder' => 'platforms/gutenberg',
			)
		);
		$this->editor_css_file = $editor_css_file ?? new SCSS_File(
			'automaticcss-core-for-block-editor',
			'automatic-core-for-block-editor.css',
			array(
				'source_file' => 'platforms/gutenberg/automatic-core-for-block-editor.scss',
				'imports_folder' => 'platforms/gutenberg',
			)
		);
		$this->editor_iframed_css_file = $editor_iframed_css_file ?? new SCSS_File(
			'automaticcss-core-for-iframe-editor',
			'automatic-core-for-iframe-editor.css',
			array(
				'source_file' => 'platforms/gutenberg/automatic-core-for-iframe-editor.scss',
				'imports_folder' => 'platforms/gutenberg',
			)
		);
		// $this->editor_css_file->set_enabled( $this->is_load_styling_backend_enabled );
		$this->color_palette_css_file = $color_palette_css_file ?? new SCSS_File(
			'automaticcss-gutenberg-color-palette',
			'automatic-gutenberg-color-palette.css',
			array(
				'source_file' => 'platforms/gutenberg/automatic-gutenberg-color-palette.scss',
				'imports_folder' => 'platforms/gutenberg',
			)
		);
		$this->custom_css_file = $custom_css_file ?? new SCSS_File(
			'automaticcss-custom',
			'automatic-custom-css.css',
			array(
				'source_file' => Plugin::get_dynamic_css_dir() . '/automatic-custom-css.scss',
				'no_source_prefix' => true,
				'imports_folder' => './',
				'skip_file_exists_check' => true,
			)
		);
		$this->root_font_size = $this->settings->get_var( 'root-font-size' );
		$this->is_generate_color_palette_enabled = 'on' === $this->settings->get_var( 'option-gutenberg-color-palette-generate' );
		// Get the 'LOAD_GUTENBERG_STYLES_USING_ENQUEUE_STYLESHEET' flag.
		$this->load_gutenberg_styles_using_enqueue_stylesheet = is_bool( $load_gutenberg_styles_using_enqueue_stylesheet ) ? $load_gutenberg_styles_using_enqueue_stylesheet : Flag::is_on( 'LOAD_GUTENBERG_STYLES_USING_ENQUEUE_STYLESHEET' );
		$this->fix_block_editor_rfs_js_file = $fix_block_editor_rfs_js_file ?? new JS_File(
			'fix-block-editor-rfs',
			ACSS_FRAMEWORK_URL . '/Integrations/Gutenberg/js/fix-block-editor-rfs.js',
			ACSS_FRAMEWORK_DIR . '/Integrations/Gutenberg/js/fix-block-editor-rfs.js',
			array(),
			true,
			'automatic_css_block_editor_options',
			array(
				'root_font_size' => $this->root_font_size,
				'load_gutenberg_styles_using_enqueue_stylesheet' => $this->load_gutenberg_styles_using_enqueue_stylesheet ? 'on' : 'off',
				'iframed_content' => $this->editor_iframed_css_file->css_contents,
				'iframed_stylesheet_url' => $this->editor_iframed_css_file->file_url,
			)
		);
		$this->fix_metabox_wysiwyg_js_file = $fix_metabox_wysiwyg_js_file ?? new JS_File(
			'fix-metabox-wysiwyg',
			ACSS_FRAMEWORK_URL . '/Integrations/Gutenberg/js/fix-metabox-wysiwyg.js',
			ACSS_FRAMEWORK_DIR . '/Integrations/Gutenberg/js/fix-metabox-wysiwyg.js',
			array(),
			true,
			'automatic_css_metabox_options',
			array(
				'root_font_size' => $this->root_font_size,
			)
		);
		// Handle the case where we want to load Gutenberg styles using enqueue_stylesheet().
		// We can enqueue the preview assets in two ways:
		// - if LOAD_GUTENBERG_STYLES_USING_ENQUEUE_STYLESHEET is on,
		// we use enqueue_stylesheet() to enqueue the stylesheets.
		// - if LOAD_GUTENBERG_STYLES_USING_ENQUEUE_STYLESHEET is off,
		// we use add_editor_style() to enqueue the stylesheets.
		// However, add_editor_style() needs to be called before get_block_editor_theme_styles(),
		// or it won't work. We hook into after_theme_setup for it.
		add_action( 'after_setup_theme', array( $this, 'enqueue_preview_assets_via_add_editor_style' ) );

		// Debug feature: forces Gutenberg to load in non-iframe mode for testing.
		if ( Flag::is_on( 'FORCE_GUTENBERG_IN_NON_IFRAME_MODE' ) ) {
			self::force_loading_not_in_iframe();
		}
	}

	/**
	 * Whether the integration is active.
	 *
	 * @return bool
	 */
	public static function is_active() {
		return true;
	}

	/**
	 * Whether the current context is a preview context.
	 *
	 * @return bool
	 */
	public function is_preview_context() {
		// TODO: is_enabled(), is_load_styling_backend_enabled and is_allowed_post_type().
		$is_admin = $this->wp_context->is_admin();
		$did_current_screen = $this->wp_context->did_action( 'current_screen' );
		$is_block_editor_screen = $this->is_block_editor_screen();

		$result = $is_admin && $did_current_screen && $is_block_editor_screen;

		// Store for deferred logging (called by AssetManager after other enqueue_assets keys).
		$this->context_debug_data['preview_context'] = $result;
		$this->context_debug_data['preview_context_debug'] = array(
			'is_admin'               => $is_admin,
			'did_current_screen'     => $did_current_screen,
			'is_block_editor_screen' => $is_block_editor_screen,
		);

		return $result;
	}

	/**
	 * Whether the current context is a builder context.
	 *
	 * @return bool
	 */
	public function is_builder_context() {
		// TODO: is_enabled() and is_allowed_post_type().
		$is_admin = $this->wp_context->is_admin();
		$did_enqueue_block_assets = $this->wp_context->did_action( 'enqueue_block_assets' );
		$did_enqueue_block_editor_assets = $this->wp_context->did_action( 'enqueue_block_editor_assets' );
		$is_block_editor_screen = $this->is_block_editor_screen();

		// Since WP 6.3, usage of enqueue_block_editor_assets is deprecated, and we're encouraged
		// to use enqueue_block_assets instead (which is called on the frontend and backend).
		// @see https://make.wordpress.org/core/2023/07/18/miscellaneous-editor-changes-in-wordpress-6-3/#post-editor-iframed .
		$did_enqueue_assets = $did_enqueue_block_assets > 0 || $did_enqueue_block_editor_assets > 0;

		$result = $is_admin && $did_enqueue_assets && $is_block_editor_screen;

		// Store for deferred logging (called by AssetManager after other enqueue_assets keys).
		$this->context_debug_data['builder_context'] = $result;
		$this->context_debug_data['builder_context_debug'] = array(
			'is_admin'                        => $is_admin,
			'did_enqueue_block_assets'        => $did_enqueue_block_assets,
			'did_enqueue_block_editor_assets' => $did_enqueue_block_editor_assets,
			'is_block_editor_screen'          => $is_block_editor_screen,
		);

		return $result;
	}

	/**
	 * Whether the current context is a frontend context.
	 *
	 * @return bool
	 */
	public function is_frontend_context() {
		return WordPress::is_wp_frontend();
	}

	/**
	 * Log the context debug data to WideEventLogger.
	 *
	 * This method is called by AssetManager after all other enqueue_assets keys
	 * have been logged, ensuring the Gutenberg keys appear at the end.
	 *
	 * @return void
	 */
	public function log_context_debug_data(): void {
		foreach ( $this->context_debug_data as $key => $value ) {
			WideEventLogger::set( 'enqueue_assets.gutenberg.' . $key, $value );
		}
	}

	/**
	 * Are we on a block editor page? (Instance method using wp_context)
	 *
	 * @return boolean
	 */
	public function is_block_editor_screen() {
		return $this->wp_context->is_block_editor() || $this->wp_context->is_site_editor();
	}

	/**
	 * Are we on a block editor page? (Static method for backward compatibility)
	 *
	 * @return boolean
	 */
	public static function is_block_editor() {
		if ( function_exists( 'get_current_screen' ) ) {
			$current_screen = \get_current_screen();
			if ( null === $current_screen ) {
				return false;
			}
			$is_block_editor = method_exists( $current_screen, 'is_block_editor' ) && $current_screen->is_block_editor();
			$is_site_editor = 'site-editor' === $current_screen->base;
			return $is_block_editor || $is_site_editor;
		}
		return false;
	}

	/**
	 * Get the assets to enqueue for the specified context.
	 *
	 * @param Context $context The context to get assets to enqueue for.
	 * @return array<SCSS_File|JS_File> The assets to enqueue for the specified context.
	 */
	public function get_assets_to_enqueue( Context $context ): array {
		$is_my_context = in_array( self::class, $context->get_determiners() );
		switch ( $context->get_context() ) {
			case Context::BUILDER:
				if ( ! $is_my_context ) {
					return array();
				}
				return array(
					$this->fix_block_editor_rfs_js_file,
					$this->fix_metabox_wysiwyg_js_file,
				);
			case Context::PREVIEW:
				if ( ! $is_my_context ) {
					// TODO: Enqueue frontend assets and fix tests?
					return array();
				}
				if ( ! $this->load_gutenberg_styles_using_enqueue_stylesheet ) {
					// We use enqueue_preview_assets_via_add_editor_style() then.
					return array();
				}
				return array(
					$this->editor_css_file,
					$this->overrides_css_file,
					$this->custom_css_file,
				);
			case Context::FRONTEND:
				$frontend_assets = array( $this->overrides_css_file );
				if ( $this->is_generate_color_palette_enabled ) {
					$frontend_assets[] = $this->color_palette_css_file;
				}
				return $frontend_assets;
			default:
				return array();
		}
	}

	/**
	 * Enqueue assets.
	 *
	 * @param Context $context The context.
	 * @return void
	 */
	public function enqueue_assets( Context $context ): void {
		$assets = $this->get_assets_to_enqueue( $context );
		$is_my_context = in_array( self::class, $context->get_determiners() );
		$context_name = $context->get_context();
		if ( Context::FRONTEND === $context_name || $is_my_context ) {
			// acss/gutenberg/builder_context, acss/gutenberg/preview_context, acss/gutenberg/frontend_context.
			// These hooks allow Core to hook into the Gutenberg context.
			// We only want them for Gutenberg's own contexts, or the frontend (which is kind everybody's).
			do_action( sprintf( 'acss/gutenberg/%s_context', $context_name ) );
		}
		if ( Context::PREVIEW === $context_name && $is_my_context ) {
			// Remove the default reset stylesheet from execution, as that causes layout issues.
			if ( ! in_array( 'wp-reset-editor-styles', wp_styles()->done, true ) ) {
				wp_styles()->done[] = 'wp-reset-editor-styles';
			}
		}
		foreach ( $assets as $asset ) {
			if ( $asset instanceof SCSS_File ) {
				$asset->enqueue_stylesheet();
			}
			if ( $asset instanceof JS_File ) {
				$asset->enqueue();
				$asset->localize();
			}
		}
	}

	/**
	 * Enqueue preview assets.
	 *
	 * @return void
	 */
	public function enqueue_preview_assets_via_add_editor_style() {
		if ( $this->load_gutenberg_styles_using_enqueue_stylesheet ) {
			// We enqueue in enqueue_assets() then.
			return;
		}
		add_theme_support( 'editor-styles' ); // supposed to be not necessary, but it is when not using a FSE theme.

		// add_editor_style() accepts either a URL or a file path relative to the theme root.
		// For some reason, on local, URLs are causing a cURL error 7 (Failed to connect to localhost port 8888).
		// And with HTTP auth, URLs aren't resolving correctly.
		// So in those cases, we use the file path approach.
		$relative_path = ( WordPress::is_local_environment() || WordPress::is_http_auth() )
			? trailingslashit( $this->calculate_relative_path( get_theme_file_path(), Plugin::get_dynamic_css_dir() ) )
			: '';

		$this->editor_css_file->enqueue_stylesheet_via_add_editor_style( $relative_path );
		// Adding editor_iframed_css_file here won't work, because the after_setup_theme happens before
		// we know what blocks were registered. We fix this in fix-block-editor-rfs.js.
		$this->overrides_css_file->enqueue_stylesheet_via_add_editor_style( $relative_path );
		$this->custom_css_file->enqueue_stylesheet_via_add_editor_style( $relative_path );
	}

	/**
	 * Calculate the relative path from theme path to uploads directory.
	 *
	 * @param string $theme_path The theme path.
	 * @param string $uploads_dir The uploads directory path.
	 * @return string The relative path.
	 */
	private function calculate_relative_path( $theme_path, $uploads_dir ) {
		// Convert paths to arrays for easier comparison.
		$theme_parts = explode( '/', rtrim( $theme_path, '/' ) );
		$uploads_parts = explode( '/', rtrim( $uploads_dir, '/' ) );

		// Find the common base path.
		$common_parts = array();
		$i = 0;
		while ( isset( $theme_parts[ $i ] ) && isset( $uploads_parts[ $i ] ) && $theme_parts[ $i ] === $uploads_parts[ $i ] ) {
			$common_parts[] = $theme_parts[ $i ];
			$i++;
		}

		// Calculate how many levels up we need to go from theme path.
		$levels_up = count( $theme_parts ) - count( $common_parts );

		// Build the relative path.
		$relative_path = str_repeat( '../', $levels_up );

		// Add the remaining parts from uploads path.
		$remaining_parts = array_slice( $uploads_parts, count( $common_parts ) );
		$relative_path .= implode( '/', $remaining_parts );

		return $relative_path;
	}

	/**
	 * Force Gutenberg to load not in iframe.
	 * For testing purposes.
	 *
	 * @return void
	 */
	public static function force_loading_not_in_iframe() {
		add_action(
			'enqueue_block_editor_assets',
			function () {
				$filename = 'gutenberg-force-loading-not-in-iframe';
				$filepath = "/Integrations/Gutenberg/js/{$filename}.js";
				wp_enqueue_script(
					"acss-{$filename}",
					ACSS_FRAMEWORK_URL . $filepath,
					array( 'wp-blocks', 'wp-dom-ready', 'wp-edit-post' ),
					filemtime( ACSS_FRAMEWORK_DIR . $filepath ),
					true
				);
			}
		);
	}

	/**
	 * Get the name of the integration.
	 *
	 * @return string
	 */
	public static function get_name() {
		return 'gutenberg';
	}

	/**
	 * Get all CSS files managed by this integration.
	 *
	 * Note: custom_css_file is NOT included here because Core handles its generation.
	 * Gutenberg only needs the reference for enqueueing in the editor.
	 *
	 * @return array<SCSS_File> The CSS files.
	 */
	public function get_css_files(): array {
		return array(
			$this->overrides_css_file,
			$this->editor_css_file,
			$this->editor_iframed_css_file,
			$this->color_palette_css_file,
		);
	}
}
