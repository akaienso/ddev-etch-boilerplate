<?php
/**
 * Array Property
 *
 * Represents an array component property definition.
 *
 * @package Etch\Blocks\Types\ComponentProperty
 */

namespace Etch\Blocks\Types\ComponentProperty;

use Etch\Blocks\Utilities\EtchTypeAsserter;

/**
 * ArrayProperty class
 */
class ArrayProperty extends ComponentProperty {

	/**
	 * Default value
	 *
	 * @var array<mixed>|null
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
	 * Resolve the value for this property.
	 *
	 * @param mixed                                         $value   The value to resolve.
	 * @param array<int, array{key: string, source: mixed}> $sources Sources for dynamic expression evaluation.
	 * @return array<mixed>
	 */
	public function resolve_value( $value, array $sources ): mixed {
		if ( null === $value || '' === $value ) {
			return array();
		}

		return EtchTypeAsserter::to_array( $value );
	}
}
