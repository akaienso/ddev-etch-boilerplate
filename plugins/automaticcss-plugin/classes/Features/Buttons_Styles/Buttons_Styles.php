<?php
/**
 * Automatic.css Buttons Style Feature.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Features\Buttons_Styles;

use Automatic_CSS\Framework\AssetManager\DefaultAssetEnqueuing;
use Automatic_CSS\Framework\AssetManager\HasAssetsToEnqueueInterface;
use Automatic_CSS\Framework\ContextManager\Context;
use Automatic_CSS\Framework\IntegrationManager\IntegrationInterface;
use Automatic_CSS\Framework\Integrations\Bricks;
use Automatic_CSS\Helpers\JS_File;
use Automatic_CSS\Model\Database_Settings;

/**
 * Buttons Style class
 */
class Buttons_Styles implements IntegrationInterface, HasAssetsToEnqueueInterface {

	use DefaultAssetEnqueuing; // Adds enqueue_assets() method.

	/**
	 * ACSS prefix for buttons
	 *
	 * @var string
	 */
	protected $acss_prefix_btn_class = 'btn--';

	/**
	 * Bricks prefix for buttons
	 *
	 * @var array
	 */
	protected $bricks_prefix_btn_class = array(
		'bricks-background-',
		'bricks-color-',
	);

	/**
	 * List of CSS classes from ACSS buttons
	 *
	 * @var array
	 */
	protected $acss_colors_list = array(
		'btn--primary'  => 'Primary',
		'btn--secondary' => 'Secondary',
		'btn--tertiary' => 'Tertiary',
		'btn--accent'   => 'Accent',
		'btn--base'     => 'Base',
		'btn--neutral'  => 'Neutral',
		'btn--warning'  => 'Warning',
		'btn--info'     => 'Info',
		'btn--danger'   => 'Danger',
		'btn--success'  => 'Success',
	);

	/**
	 * List of CSS classes from ACSS buttons shades
	 *
	 * @var array
	 */
	protected $acss_shades_list = array(
		'light' => 'Light',
		'dark'  => 'Dark',
	);

	/**
	 * List of CSS classes from ACSS buttons sizes
	 *
	 * @var array
	 */
	protected $acss_sizes_list = array(
		'btn--xs'   => 'Extra Small',
		'btn--s'    => 'Small',
		'btn--m'    => 'Medium',
		'btn--l'    => 'Large',
		'btn--xl'   => 'Extra Large',
		'btn--xxl'  => 'Huge',
	);

	/**
	 * JS File for builder.
	 *
	 * @var JS_File
	 */
	private $builder_js;

	/**
	 * Initialize the feature.
	 *
	 * @param array<string, CSS_File|JS_File|null> $options Array with keys: builder_js.
	 */
	public function __construct( array $options = array() ) {
		$this->builder_js = $options['builder_js'] ?? new JS_File(
			'buttons-styles-preview-script',
			ACSS_FEATURES_URL . '/Buttons_Styles/js/buttons-styles-preview.js',
			ACSS_FEATURES_DIR . '/Buttons_Styles/js/buttons-styles-preview.js',
		);

		add_filter( 'bricks/elements/button/controls', array( $this, 'change_bricks_controls' ) );
		add_filter( 'bricks/elements/button/controls', array( $this, 'add_bricks_buttons_styles' ) );
		add_filter( 'bricks/elements/button/controls', array( $this, 'add_bricks_buttons_sizes' ) );
		add_filter( 'bricks/element/render_attributes', array( $this, 'change_btn_class_name_for_bricks' ), 10, 3 );
	}

	/**
	 * Bricks filter to change the Buttons controls for Bricks panel
	 *
	 * @param mixed $controls   Bricks button controls.
	 *
	 * @return array
	 */
	public function change_bricks_controls( $controls ) {
		if ( ! is_array( $controls ) ) {
			return $controls;
		}

		// Remove the circle option.
		if ( isset( $controls['circle'] ) ) {
			unset( $controls['circle'] );
		}

		// Move the Style above to size option.
		$controls = $this->_reorder_array_by_key( $controls, 'size', 'style' );

		return $controls;
	}

	/**
	 * Bricks filter to update the style select for Bricks panel
	 *
	 * @param mixed $controls   Bricks button controls.
	 *
	 * @return array
	 */
	public function add_bricks_buttons_styles( $controls ) {
		if ( ! is_array( $controls ) || ! isset( $controls['style'] ) || ! is_array( $controls['style'] ) ) {
			return $controls;
		}

		$styles_list = $this->get_styles_list();

		if ( count( $styles_list ) > 0 ) {
			$controls['style']['options'] = $styles_list;
			$controls['style']['default'] = array_key_first( $styles_list );
		}

		return $controls;
	}

	/**
	 * Bricks filter to update the size select for Bricks panel
	 *
	 * @param mixed $controls   Bricks button controls.
	 *
	 * @return array
	 */
	public function add_bricks_buttons_sizes( $controls ) {
		if ( ! is_array( $controls ) || ! isset( $controls['size'] ) || ! is_array( $controls['size'] ) ) {
			return $controls;
		}

		$size_list = $this->get_sizes_list();

		if ( count( $size_list ) > 0 ) {
			$controls['size']['options'] = $size_list;
			$controls['size']['default'] = 'btn--m';
		}

		return $controls;
	}

	/**
	 * Return the list of sizes for buttons
	 *
	 * @return array
	 */
	public function get_sizes_list() {
		return $this->acss_sizes_list;
	}

	/**
	 * Return the list of styles for buttons based on the ACSS options
	 *
	 * @param array $options ACSS options.
	 * @return array
	 */
	public function get_styles_list( $options = array() ) {
		if ( empty( $options ) ) {
			$database = Database_Settings::get_instance();
			$options = $database->get_vars();
		}

		if ( empty( $options['option-buttons'] ) || 'on' !== $options['option-buttons'] ) {
			return array();
		}

		$styles_to_add = array();
		foreach ( $this->acss_colors_list as $class_name => $label ) {
			$color_name = str_replace( 'btn--', '', $class_name );

			if ( isset( $options[ 'option-' . $color_name . '-clr' ] ) && 'on' !== $options[ 'option-' . $color_name . '-clr' ] ) {
				// Temp fix for ACSS-396: skip if the main color is not enabled.
				// TODO: refactor the saving process so that option-[color_name]-btn and similar are off when the main color is off.
				continue;
			}

			if ( empty( $options[ 'option-' . $color_name . '-btn' ] ) || 'on' !== $options[ 'option-' . $color_name . '-btn' ] ) {
				continue;
			}

			$styles_to_add[ $class_name ] = $label;

			if ( ! empty( $options[ 'option-' . $color_name . '-btn-shades' ] ) && 'on' == $options[ 'option-' . $color_name . '-btn-shades' ] ) {
				foreach ( $this->acss_shades_list as $shade_name => $shade_label ) {
					$btn_class = $class_name . '-' . $shade_name;
					$styles_to_add[ $btn_class ] = $label . ' ' . $shade_label;
				}
			}
		}

		return $styles_to_add;
	}

	/**
	 * Bricks filter to check all the classes from a button to see if is a ACSS button and add our class
	 *
	 * @param mixed  $attributes Array with all attributes for the key.
	 * @param string $key        Name of bricks element attribute.
	 * @param object $element    Bricks element.
	 *
	 * @return mixed
	 */
	public function change_btn_class_name_for_bricks( $attributes, $key, $element ) {
		if ( 'button' !== $element->name || '_root' !== $key ) {
			return $attributes;
		}

		if ( ! isset( $attributes['_root']['class'] ) || ! is_array( $attributes['_root']['class'] ) ) {
			return $attributes;
		}

		$outline_key_index = false;
		$has_acss_class = false;

		foreach ( $attributes['_root']['class'] as $key => $class ) {
			if ( false !== $outline_key_index && $has_acss_class ) {
				break;
			}

			if ( 'outline' == $class ) {
				$outline_key_index = $key;
				continue;
			}

			if ( $has_acss_class ) {
				continue;
			}

			foreach ( $this->bricks_prefix_btn_class as $bricks_prefix ) {
				$class_to_check = $bricks_prefix . $this->acss_prefix_btn_class;

				if ( false !== strpos( $class, $class_to_check ) ) {
					$has_acss_class = true;
					$attributes['_root']['class'][] = str_replace( $class_to_check, $this->acss_prefix_btn_class, $class );
					break;
				}
			}
		}

		if ( false !== $outline_key_index && $has_acss_class ) {
			$attributes['_root']['class'][ $outline_key_index ] = 'btn--outline';
		}

		return $attributes;
	}

	/**
	 * Change the position of key in an array
	 *
	 * @param array  $array Array that should be verified.
	 * @param string $key1  Key you should put to last position.
	 * @param string $key2  Key you should put to first position.
	 *
	 * @return array
	 */
	private function _reorder_array_by_key( array $array, string $key1, string $key2 ) {
		if ( ! isset( $array[ $key1 ] ) || ! isset( $array[ $key2 ] ) ) {
			return $array;
		}

		$keys = array_keys( $array );
		$positionKey1 = array_search( $key1, $keys, true );
		$positionKey2 = array_search( $key2, $keys, true );

		if ( $positionKey1 > $positionKey2 ) {
			return $array;
		}

		$newArray = array();
		foreach ( $array as $key => $value ) {
			if ( $key === $key2 ) {
				$newArray[ $key1 ] = $array[ $key1 ];
			} elseif ( $key === $key1 ) {
				$newArray[ $key2 ] = $array[ $key2 ];
			} else {
				$newArray[ $key ] = $value;
			}
		}

		return $newArray;
	}

	/**
	 * Enqueue the assets
	 *
	 * @param Context $context The context in which the assets are being enqueued.
	 */
	public function get_assets_to_enqueue( Context $context ): array {
		switch ( $context->get_context() ) {
			case Context::PREVIEW:
				$is_bricks_context = in_array( Bricks::class, $context->get_determiners() );
				if ( ! $is_bricks_context ) {
					return array();
				}
				return array(
					$this->builder_js,
				);
			case Context::BUILDER:
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
		return $acss_database->get_var( 'option-buttons-styles-enable' ) === 'on' ?? false;
	}

	/**
	 * Get the name of the integration.
	 *
	 * @return string
	 */
	public static function get_name() {
		return 'buttons-styles';
	}
}
