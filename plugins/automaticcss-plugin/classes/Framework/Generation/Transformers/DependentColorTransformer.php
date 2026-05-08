<?php
/**
 * Dependent color transformer for framework variable generation.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Framework\Generation\Transformers;

/**
 * Handles color variables that depend on already-processed color components.
 *
 * Some form-related variables reference colors via var(--colorname) and need
 * their HSL components extracted from the already-processed color variables.
 */
class DependentColorTransformer {

	/**
	 * Variables that need dependent color processing.
	 */
	private const DEPENDENT_VARS = array(
		'f-focus-color',
		'f-input-placeholder-color',
	);

	/**
	 * Get the list of dependent color variable names.
	 *
	 * @return array The list of dependent color variable names.
	 */
	public function get_dependent_vars() {
		return self::DEPENDENT_VARS;
	}

	/**
	 * Transform a dependent color variable, extracting HSL from already-processed colors.
	 *
	 * @param string $var       The variable name (e.g., 'f-focus-color').
	 * @param mixed  $value     The value containing a color reference (e.g., 'var(--primary)').
	 * @param array  $variables The already-processed variables array.
	 * @return array Additional variables to add (may be empty).
	 */
	public function transform( $var, $value, $variables ) {
		$result = array();

		if ( null === $value ) {
			return $result;
		}

		$matches = array();
		if ( ! preg_match( '/var\(--([A-Za-z]+)(-[A-Za-z-]+)?/', $value, $matches ) ) {
			return $result;
		}

		$color_name = $matches[1];
		$color_variation = isset( $matches[2] ) ? $matches[2] : '';
		$color_var = $color_name . $color_variation;

		$color_h = isset( $variables[ $color_var . '-h' ] ) ? $variables[ $color_var . '-h' ] : '';
		$color_s = isset( $variables[ $color_var . '-s' ] ) ? $variables[ $color_var . '-s' ] : '';
		$color_l = isset( $variables[ $color_var . '-l' ] ) ? $variables[ $color_var . '-l' ] : '';

		if ( '' === $color_h || '' === $color_s || '' === $color_l ) {
			return $result;
		}

		$setting_matches = array();
		if ( preg_match( '/f-(light|dark)(-[A-Za-z-]+)?-color/', $var, $setting_matches ) ) {
			$setting_prefix = $setting_matches[1];
			$setting_variation = $setting_matches[2] ?? '';
			$var_name = 'f-' . $setting_prefix . $setting_variation . '-hsl';
			$result[ $var_name ] = "{$color_h} {$color_s} {$color_l}";
		}

		return $result;
	}
}
