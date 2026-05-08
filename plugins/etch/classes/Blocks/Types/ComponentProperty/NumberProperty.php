<?php
/**
 * Number Property
 *
 * Represents a number component property definition.
 *
 * @package Etch\Blocks\Types\ComponentProperty
 */

namespace Etch\Blocks\Types\ComponentProperty;

use Etch\Blocks\Utilities\EtchTypeAsserter;

/**
 * NumberProperty class
 */
class NumberProperty extends ComponentProperty {

	/**
	 * Default value
	 *
	 * @var int|float|null
	 */
	public $default = null;

	/**
	 * Options array
	 *
	 * @var array<int|float>|null
	 */
	public ?array $options = null;

	/**
	 * Create from property data array.
	 *
	 * @param array<string, mixed> $data Property data array.
	 * @return self
	 */
	public static function from_property_array( array $data ): self {
		$instance = new self();
		self::extract_base( $data, $instance );

		if ( isset( $data['options'] ) && is_array( $data['options'] ) ) {
			$instance->options = $data['options'];
		}

		return $instance;
	}

	/**
	 * Resolve the value for this property.
	 *
	 * @param mixed                                         $value   The value to resolve.
	 * @param array<int, array{key: string, source: mixed}> $sources Sources for dynamic expression evaluation.
	 * @return int|float
	 */
	public function resolve_value( $value, array $sources ): mixed {
		if ( null === $value || '' === $value ) {
			return 0;
		}

		return EtchTypeAsserter::to_number( $value );
	}

	/**
	 * Convert to array
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		$result = parent::to_array();

		if ( null !== $this->options ) {
			$result['options'] = $this->options;
		}

		return $result;
	}
}
