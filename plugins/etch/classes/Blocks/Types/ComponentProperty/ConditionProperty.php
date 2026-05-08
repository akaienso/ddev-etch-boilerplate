<?php
/**
 * Condition Property
 *
 * Represents a condition component property definition (primitive: string, specialized: condition).
 * Contains nested sub-properties that are resolved transparently (flattened into parent scope)
 * when the condition is met.
 *
 * @package Etch\Blocks\Types\ComponentProperty
 */

namespace Etch\Blocks\Types\ComponentProperty;

use Etch\Blocks\Utilities\ComponentPropertyResolver;

/**
 * ConditionProperty class
 */
class ConditionProperty extends ComponentProperty {
	/**
	 * Default value (the condition expression)
	 *
	 * @var string|null
	 */
	public $default = null;

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
	 * Resolve the value for this condition property.
	 *
	 * Condition properties are transparent — their nested properties are resolved
	 * and flattened into the parent scope.
	 *
	 * @param mixed                                         $value   The instance attributes (passed through from parent).
	 * @param array<int, array{key: string, source: mixed}> $sources Sources for dynamic expression evaluation.
	 * @return array<string, mixed> Resolved nested properties.
	 */
	public function resolve_value( $value, array $sources ): array {
		$instance_attributes = is_array( $value ) ? $value : array();

		return ComponentPropertyResolver::resolve_properties( $this->properties, $instance_attributes, $sources );
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
