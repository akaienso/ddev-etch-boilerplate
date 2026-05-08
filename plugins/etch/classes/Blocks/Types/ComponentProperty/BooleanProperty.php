<?php
/**
 * Boolean Property
 *
 * Represents a boolean component property definition.
 *
 * @package Etch\Blocks\Types\ComponentProperty
 */

namespace Etch\Blocks\Types\ComponentProperty;

use Etch\Blocks\Utilities\EtchTypeAsserter;

/**
 * BooleanProperty class
 */
class BooleanProperty extends ComponentProperty {

	/**
	 * Default value
	 *
	 * @var bool | string
	 */
	public $default = false;

	/**
	 * Resolve the value for this property.
	 *
	 * @param mixed                                         $value   The value to resolve.
	 * @param array<int, array{key: string, source: mixed}> $sources Sources for dynamic expression evaluation.
	 * @return bool
	 */
	public function resolve_value( $value, array $sources ): mixed {
		if ( null === $value || '' === $value ) {
			return false;
		}

		return EtchTypeAsserter::to_bool( $value );
	}

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
}
