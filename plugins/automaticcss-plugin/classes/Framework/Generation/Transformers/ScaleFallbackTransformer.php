<?php
/**
 * Scale fallback transformer for framework variable generation.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Framework\Generation\Transformers;

/**
 * Handles scale variables that fall back to custom values when set to zero.
 */
class ScaleFallbackTransformer {

	/**
	 * Scale variables and their custom fallback keys.
	 */
	private const SCALE_FALLBACKS = array(
		'text-scale'        => 'text-scale-custom',
		'mob-text-scale'    => 'mob-text-scale-custom',
		'heading-scale'     => 'heading-scale-custom',
		'mob-heading-scale' => 'mob-heading-scale-custom',
		'space-scale'       => 'space-scale-custom',
		'mob-space-scale'   => 'mob-space-scale-custom',
	);

	/**
	 * Maybe transform a scale value, returning the custom fallback if zero.
	 *
	 * @param string $var    The variable name.
	 * @param mixed  $value  The current value.
	 * @param array  $values All settings values (to look up custom fallback).
	 * @return mixed The transformed value or original if no transformation needed.
	 */
	public function maybe_transform( $var, $value, $values ) {
		if ( ! array_key_exists( $var, self::SCALE_FALLBACKS ) ) {
			return $value;
		}

		if ( 0.0 === floatval( $value ) ) {
			$fallback_key = self::SCALE_FALLBACKS[ $var ];
			return $values[ $fallback_key ] ?? '';
		}

		return $value;
	}
}
