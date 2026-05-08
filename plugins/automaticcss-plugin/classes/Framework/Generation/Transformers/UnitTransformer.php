<?php
/**
 * Unit transformer for framework variable generation.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Framework\Generation\Transformers;

/**
 * Handles unit conversions: px to rem, percent suffix, percentage-convert scaling.
 */
class UnitTransformer {

	/**
	 * Default root font size percentage.
	 */
	public const DEFAULT_ROOT_FONT_SIZE = 62.5;

	/**
	 * Transform a value based on unit configuration.
	 *
	 * Note: appendunit is handled separately via append_unit() and should be
	 * called after CSS var wrapping to preserve original behavior.
	 *
	 * @param mixed  $value          The value to transform.
	 * @param string $unit           The unit type ('px', 'rem', '%').
	 * @param array  $options        Options with 'skip-unit-conversion', 'percentage-convert'.
	 * @param float  $root_font_size Current root font size for percentage-convert.
	 * @return mixed The transformed value.
	 */
	public function transform( $value, $unit, $options, $root_font_size ) {
		$skip_conversion = isset( $options['skip-unit-conversion'] ) && $options['skip-unit-conversion'];

		if ( ! $skip_conversion ) {
			if ( 'px' === $unit ) {
				$value = floatval( $value ) / 10;
			} elseif ( '%' === $unit && '%' !== substr( $value, -1 ) ) {
				$value .= '%';
			}

			$convert = isset( $options['percentage-convert'] ) && $options['percentage-convert'];
			if ( $convert ) {
				$value = floatval( $value ) * self::DEFAULT_ROOT_FONT_SIZE / $root_font_size;
			}
		}

		return $value;
	}

	/**
	 * Append unit suffix to value if configured.
	 *
	 * This should be called after CSS var wrapping to preserve original behavior.
	 *
	 * @param mixed $value   The value to append unit to.
	 * @param array $options Options with 'appendunit'.
	 * @return mixed The value with unit appended if configured.
	 */
	public function append_unit( $value, $options ) {
		if ( isset( $options['appendunit'] ) && '' !== $options['appendunit'] ) {
			$value .= $options['appendunit'];
		}

		return $value;
	}

	/**
	 * Determine the unit from options or type.
	 *
	 * @param array  $options The variable options.
	 * @param string $type    The variable type.
	 * @return string|null The unit or null.
	 */
	public function get_unit( $options, $type ) {
		if ( array_key_exists( 'unit', $options ) ) {
			return $options['unit'];
		}

		switch ( $type ) {
			case 'px':
			case 'rem':
				return $type;
			case 'percent':
				return '%';
			default:
				return null;
		}
	}
}
