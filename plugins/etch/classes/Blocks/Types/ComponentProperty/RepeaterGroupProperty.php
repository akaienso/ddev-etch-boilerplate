<?php
/**
 * Repeater Group Property
 *
 * Represents a repeater group component property definition (primitive: array, specialized: repeater).
 * Contains nested sub-properties that define the shape of each repeater item.
 * Values are arrays of objects, each resolved recursively against the sub-property definitions.
 *
 * @package Etch\Blocks\Types\ComponentProperty
 */

namespace Etch\Blocks\Types\ComponentProperty;

use Etch\Blocks\Utilities\ComponentPropertyResolver;

/**
 * RepeaterGroupProperty class
 */
class RepeaterGroupProperty extends ComponentProperty {
	/**
	 * Default value
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public $default = array();

	/**
	 * Sub-property definitions
	 *
	 * @var array<ComponentProperty>
	 */
	public array $properties = array();

	/**
	 * Create from property data array.
	 *
	 * @param array<string, mixed> $data Property data array.
	 * @return self
	 */
	public static function from_property_array( array $data ): self {
		$instance = new self();
		self::extract_base( $data, $instance );

		if ( isset( $data['properties'] ) && is_array( $data['properties'] ) ) {
			$instance->properties = array_values(
				array_filter(
					array_map(
						fn( $prop ) => ComponentProperty::from_array( $prop ),
						$data['properties']
					)
				)
			);
		}

		return $instance;
	}

	/**
	 * Resolve the value for this repeater group property.
	 *
	 * Parses the value as an array of objects, then recursively resolves
	 * each item's sub-properties using the property definitions.
	 *
	 * @param mixed                                         $value   The value to resolve (expected array of associative arrays).
	 * @param array<int, array{key: string, source: mixed}> $sources Sources for dynamic expression evaluation.
	 * @return array<int, array<string, mixed>> The resolved repeater items.
	 */
	public function resolve_value( $value, array $sources ): mixed {
		$items = $this->parse_repeater_value( $value );

		$property_definitions = $this->properties ?? array();
		$resolved             = array();

		foreach ( $items as $item ) {
			$resolved[] = ComponentPropertyResolver::resolve_properties( $property_definitions, $item, $sources );
		}

		return $resolved;
	}

	/**
	 * Parse a repeater value into an array of associative arrays.
	 *
	 * @param mixed $value The value to parse.
	 * @return array<int, array<string, mixed>> The parsed items.
	 */
	private function parse_repeater_value( $value ): array {
		if ( is_array( $value ) && array_is_list( $value ) ) {
			return array_map(
				fn( $item ) => is_array( $item ) ? $item : array(),
				$value
			);
		}

		if ( ! is_string( $value ) ) {
			return array();
		}

		$parse_val = trim( $value );

		// Unwrap {[...]} wrapper
		if ( str_starts_with( $parse_val, '{[' ) && str_ends_with( $parse_val, ']}' ) ) {
			$parse_val = substr( $parse_val, 1, -1 );
		}

		$parsed = json_decode( $parse_val, true );
		if ( json_last_error() === JSON_ERROR_NONE && is_array( $parsed ) ) {
			return $parsed;
		}

		return array();
	}

	/**
	 * Convert to array
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		$result = parent::to_array();

		$result['properties'] = array_map(
			fn( ComponentProperty $prop ) => $prop->to_array(),
			$this->properties
		);

		return $result;
	}
}
