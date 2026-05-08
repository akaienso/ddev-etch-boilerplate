<?php
/**
 * Color transformer for framework variable generation.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Framework\Generation\Transformers;

use Automatic_CSS\Helpers\Color;

/**
 * Transforms color settings into HSL and RGB component variables.
 */
class ColorTransformer {

	/**
	 * Transform a color setting into component variables.
	 *
	 * @param string $var   The variable name (e.g., 'color-primary', 'color-primary-alt').
	 * @param string $value The hex color value.
	 * @return array Associative array of variable name => value pairs.
	 */
	public function transform( $var, $value ) {
		if ( null === $value || '' === $value ) {
			return array();
		}

		$color = new Color( $value );
		$var = str_replace( 'color-', '', $var );
		$is_alt_color = '-alt' === substr( $var, -4 );
		$var = $is_alt_color ? str_replace( '-alt', '', $var ) : $var;
		$var_suffix = $is_alt_color ? '-alt' : '';

		return array(
			$var . '-h' . $var_suffix   => $color->h,
			$var . '-s' . $var_suffix   => $color->s_perc,
			$var . '-l' . $var_suffix   => $color->l_perc,
			$var . $var_suffix . '-hex' => $color->hex,
			$var . '-r' . $var_suffix   => $color->r,
			$var . '-g' . $var_suffix   => $color->g,
			$var . '-b' . $var_suffix   => $color->b,
		);
	}
}
