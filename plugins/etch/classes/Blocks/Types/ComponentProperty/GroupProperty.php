<?php
/**
 * Group Property
 *
 * Represents a group component property definition (primitive: object, specialized: group).
 * Contains nested sub-properties that are resolved recursively.
 *
 * @package Etch\Blocks\Types\ComponentProperty
 */

namespace Etch\Blocks\Types\ComponentProperty;

use Etch\Blocks\Utilities\ComponentPropertyResolver;

/**
 * GroupProperty class
 */
class GroupProperty extends ComponentProperty {
	/**
	 * Default value
	 *
	 * @var array<string, mixed>
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
	 * Resolve the value for this group property.
	 *
	 * Recursively resolves each sub-property using instance attribute values,
	 * falling back to sub-property defaults.
	 *
	 * @param mixed                                         $value   The value to resolve (expected associative array).
	 * @param array<int, array{key: string, source: mixed}> $sources Sources for dynamic expression evaluation.
	 * @return array<string, mixed> The resolved group property values.
	 */
	public function resolve_value( $value, array $sources ): mixed {
		$prop_value = array();

		if ( is_array( $value ) ) {
			$prop_value = $value;
		} else if ( is_string( $value ) ) {
			$prop_value = $this->safeParseGroupValue( $value );
		}

		$property_definitions = $this->properties ?? array();

		return ComponentPropertyResolver::resolve_properties( $property_definitions, $prop_value, $sources );
	}

	/**
	 * Safely parse a group value.
	 *
	 * @param string $value The value to parse.
	 * @return array<string, mixed> The parsed group value.
	 */
	private function safeParseGroupValue( string $value ): array {
		$parse_val = trim( $value );

		// Shouldn't happen but ensure dynamic data is correctly unwrapped
		if ( str_starts_with( $parse_val, '{{' ) && str_ends_with( $parse_val, '}}' ) ) {
			$parse_val = substr( $parse_val, 1, -1 );
		}

		// Try to parse JSON
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
