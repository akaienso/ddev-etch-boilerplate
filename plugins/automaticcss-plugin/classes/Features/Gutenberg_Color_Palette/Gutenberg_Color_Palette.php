<?php
/**
 * Automatic.css Gutenberg_Color_Palette class file.
 *
 * Adds ACSS colors to WordPress/Gutenberg block editor color picker.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Features\Gutenberg_Color_Palette;

use Automatic_CSS\Framework\IntegrationManager\IntegrationInterface;
use Automatic_CSS\Helpers\Logger;
use Automatic_CSS\Model\Config\Framework;
use Automatic_CSS\Model\Database_Settings;

/**
 * Gutenberg_Color_Palette class.
 *
 * Manages adding ACSS color palettes to the WordPress block editor.
 * Supports both modern theme.json and legacy add_theme_support approaches.
 */
class Gutenberg_Color_Palette implements IntegrationInterface {

	/**
	 * Whether color palette generation is enabled.
	 *
	 * @var bool
	 */
	private $is_generate_enabled = false;

	/**
	 * Whether to replace existing colors or merge with them.
	 *
	 * @var bool
	 */
	private $is_replace_enabled = false;

	/**
	 * Constructor.
	 *
	 * Registers hooks based on theme.json support.
	 */
	public function __construct() {
		// Read settings.
		$database_settings = Database_Settings::get_instance();
		$this->is_generate_enabled = 'on' === $database_settings->get_var( 'option-gutenberg-color-palette-generate' );
		$this->is_replace_enabled = 'on' === $database_settings->get_var( 'option-gutenberg-color-palette-replace' );

		// Determine which hook to use based on theme.json support.
		if ( function_exists( 'wp_theme_has_theme_json' ) && wp_theme_has_theme_json() ) {
			add_filter( 'wp_theme_json_data_theme', array( $this, 'add_color_palette_to_theme_json' ) );
		} else {
			add_action( 'after_setup_theme', array( $this, 'add_color_palette' ), 11 );
		}

		// Update status during CSS generation.
		if ( is_admin() ) {
			add_action( 'automaticcss_before_generate_framework_css', array( $this, 'update_status' ) );
		}
	}

	/**
	 * Whether the integration is active.
	 *
	 * @return bool
	 */
	public static function is_active() {
		return true; // Always active - Gutenberg is part of WordPress core.
	}

	/**
	 * Get the name of the integration.
	 *
	 * @return string
	 */
	public static function get_name() {
		return 'gutenberg-color-palette';
	}

	/**
	 * Update the enabled status from CSS generation variables.
	 *
	 * @param array $variables The values for the framework's variables.
	 * @return void
	 */
	public function update_status( $variables ) {
		$this->is_generate_enabled = isset( $variables['option-gutenberg-color-palette-generate'] ) && 'on' === $variables['option-gutenberg-color-palette-generate'];
		$this->is_replace_enabled = isset( $variables['option-gutenberg-color-palette-replace'] ) && 'on' === $variables['option-gutenberg-color-palette-replace'];
		Logger::log( sprintf( '%s: color palette generation is %s', __METHOD__, $this->is_generate_enabled ? 'enabled' : 'disabled' ) );
	}

	/**
	 * Add the color palette to the block editor via add_theme_support.
	 *
	 * Used for classic themes without theme.json.
	 *
	 * @return void
	 */
	public function add_color_palette() {
		if ( ! $this->is_generate_enabled || ! self::is_allowed_post_type() ) {
			return;
		}

		$gb_color_palette = $this->get_acss_color_palette();

		// Merge with existing palette if replace is not enabled.
		if ( ! $this->is_replace_enabled ) {
			$gb_current_color_palette = current( (array) get_theme_support( 'editor-color-palette' ) );

			if ( false === $gb_current_color_palette && class_exists( 'WP_Theme_JSON_Resolver' ) ) {
				$settings = \WP_Theme_JSON_Resolver::get_core_data()->get_settings();
				if ( isset( $settings['color']['palette']['default'] ) ) {
					$gb_current_color_palette = $settings['color']['palette']['default'];
				}
			}
			if ( ! empty( $gb_current_color_palette ) ) {
				$gb_color_palette = array_merge( $gb_current_color_palette, $gb_color_palette );
			}
		}

		add_theme_support( 'editor-color-palette', $gb_color_palette );
		Logger::log( sprintf( '%s: added %d colors to editor palette', __METHOD__, count( $gb_color_palette ) ) );
	}

	/**
	 * Add ACSS color palette to theme.json.
	 *
	 * Used for modern FSE themes with theme.json support.
	 *
	 * @param \WP_Theme_JSON_Data $theme_json Theme JSON data from WordPress filter.
	 * @return \WP_Theme_JSON_Data Modified theme JSON data.
	 */
	public function add_color_palette_to_theme_json( $theme_json ) {
		if ( ! $this->is_generate_enabled || ! self::is_allowed_post_type() ) {
			return $theme_json;
		}

		$json = $theme_json->get_data();
		$gb_color_palette = $this->get_acss_color_palette();

		// Merge with existing palette if replace is not enabled.
		if ( ! $this->is_replace_enabled ) {
			if ( ! empty( $json['settings']['color']['palette']['theme'] ) ) {
				$gb_color_palette = array_merge( $json['settings']['color']['palette']['theme'], $gb_color_palette );
			}
		}

		$new_theme_json = array(
			'version'  => 2,
			'settings' => array(
				'color' => array(
					'palette' => array(
						'theme' => $gb_color_palette,
					),
				),
			),
		);

		// Disable default palettes if replace is enabled.
		if ( $this->is_replace_enabled ) {
			$new_theme_json['settings']['color']['defaultPalette'] = false;
			$new_theme_json['settings']['color']['defaultDuotone'] = false;
			$new_theme_json['settings']['color']['defaultGradients'] = false;
		}

		$theme_json = $theme_json->update_with( $new_theme_json );
		Logger::log( sprintf( '%s: added %d colors to theme.json palette', __METHOD__, count( $gb_color_palette ) ) );

		return $theme_json;
	}

	/**
	 * Get ACSS color palette in WordPress format.
	 *
	 * @return array Array of color definitions with name, slug, and color keys.
	 */
	private function get_acss_color_palette() {
		$acss_db = Database_Settings::get_instance();
		$acss_color_palettes = ( new Framework() )->get_color_palettes(
			array(
				'contextual_colors' => 'on' === $acss_db->get_var( 'option-status-colors' ),
				'deprecated_colors' => 'on' === $acss_db->get_var( 'option-shade-clr' ),
				'pro_active_only'   => true,
			)
		);

		$gb_color_palette = array();
		foreach ( $acss_color_palettes as $acss_palette_options ) {
			$acss_palette_colors = array_key_exists( 'colors', $acss_palette_options ) ? $acss_palette_options['colors'] : array();
			foreach ( $acss_palette_colors as $acss_color_name => $acss_color_value ) {
				$gb_color_palette[] = array(
					'name'  => $acss_color_name,
					'slug'  => $acss_color_name,
					'color' => $acss_color_value,
				);
			}
		}

		return $gb_color_palette;
	}

	/**
	 * Check if the current post type allows Gutenberg color palette.
	 *
	 * @return bool
	 */
	private static function is_allowed_post_type() {
		$post_type = null;

		if ( function_exists( 'get_current_screen' ) ) {
			$current_screen = \get_current_screen();
			if ( $current_screen && 'site-editor' === $current_screen->base ) {
				return true;
			}
			if ( $current_screen && $current_screen->post_type ) {
				$post_type = $current_screen->post_type;
			}
		}

		if ( is_null( $post_type ) && is_admin() ) {
			global $pagenow;

			if ( 'post.php' === $pagenow && ! empty( $_GET['post'] ) && ! empty( $_GET['action'] ) && 'edit' === $_GET['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$post_id = intval( $_GET['post'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$post = get_post( $post_id );

				if ( $post ) {
					$post_type = $post->post_type;
				}
			} elseif ( 'post-new.php' === $pagenow ) {
				$post_type = ! empty( $_GET['post_type'] ) ? sanitize_text_field( wp_unslash( $_GET['post_type'] ) ) : 'post'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
		}

		$allowed_post_types = apply_filters( 'acss/gutenberg/allowed_post_types', array( 'page', 'post' ) );
		return $post_type && in_array( $post_type, $allowed_post_types, true );
	}
}
