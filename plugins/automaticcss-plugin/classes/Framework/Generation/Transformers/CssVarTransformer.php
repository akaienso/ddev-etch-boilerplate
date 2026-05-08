<?php
/**
 * CSS variable transformer for framework variable generation.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Framework\Generation\Transformers;

/**
 * Wraps CSS custom property references in var() if not already wrapped.
 */
class CssVarTransformer {

	/**
	 * Maybe wrap a CSS custom property reference in var().
	 *
	 * @param mixed $value The value to transform.
	 * @return mixed The transformed value, or original if not a CSS var.
	 */
	public function maybe_transform( $value ) {
		if ( null === $value || ! is_string( $value ) || 0 !== strpos( $value, '--' ) ) {
			return $value;
		}

		$value = "var({$value}";
		if ( ')' !== substr( $value, -1 ) ) {
			$value .= ')';
		}

		return $value;
	}
}
