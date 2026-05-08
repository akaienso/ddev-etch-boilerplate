<?php
/**
 * Class Property
 *
 * Represents a CSS class component property definition (primitive: array, specialized: class).
 *
 * @package Etch\Blocks\Types\ComponentProperty
 */

namespace Etch\Blocks\Types\ComponentProperty;

use Etch\Blocks\Global\StylesRegister;

/**
 * ClassProperty class
 */
class ClassProperty extends ComponentProperty {

	/**
	 * Default value
	 *
	 * @var array<string>|null
	 */
	public $default = null;

	/**
	 * Create from property data array.
	 *
	 * @param array<string, mixed> $data Property data array.
	 * @return self
	 */
	public static function from_property_array( array $data ): self {
		$instance = new self();
		self::extract_base( $data, $instance );
		return $instance;
	}

	/**
	 * Resolve the value for this class property.
	 *
	 * Converts style IDs into a space-separated class string.
	 *
	 * @param mixed                                         $value   The value to resolve.
	 * @param array<int, array{key: string, source: mixed}> $sources Sources for dynamic expression evaluation.
	 * @return string The resolved class string.
	 */
	public function resolve_value( $value, array $sources ): mixed {
		if ( is_array( $value ) ) {
			$style_ids = array_filter( $value, 'is_string' );
		} elseif ( is_string( $value ) ) {
			$style_ids = array_filter( array_map( 'trim', explode( ' ', $value ) ) );
		} else {
			$style_ids = array();
		}

		return $this->build_class_string_from_ids( $style_ids );
	}

	/**
	 * Build a class string from an array of style IDs.
	 *
	 * @param array<int, string> $style_ids The array of style IDs.
	 * @return string The built class string.
	 */
	private function build_class_string_from_ids( array $style_ids ): string {
		$styles = StylesRegister::get_styles_by_ids( $style_ids );

		$selectors = array();
		foreach ( $styles as $style ) {
			if ( 'class' === $style['type'] ) {
				$selectors[] = ltrim( $style['selector'], '.' );
			}
		}
		return implode( ' ', $selectors );
	}
}
