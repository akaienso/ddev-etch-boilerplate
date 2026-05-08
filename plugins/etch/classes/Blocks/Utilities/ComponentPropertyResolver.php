<?php
/**
 * Component Property Resolver
 *
 * Utility class for resolving component properties from definitions and instance attributes.
 *
 * @package Etch\Blocks\Utilities
 */

namespace Etch\Blocks\Utilities;

use Etch\Blocks\Types\ComponentProperty\ComponentProperty;
use Etch\Blocks\Types\ComponentProperty\ConditionProperty;
use Etch\Blocks\Global\Utilities\DynamicContentProcessor;

/**
 * ComponentPropertyResolver class
 *
 * Handles resolution of component properties by merging defaults with instance attributes.
 * Instance attributes are pre-resolved by ComponentBlock, so this class focuses on:
 * - Resolving dynamic expressions in default values
 * - Merging defaults with pre-resolved instance attributes
 * - Delegating type-specific resolution to each property type's resolve_value()
 */
class ComponentPropertyResolver {

	/**
	 * Build a map of component properties from their definitions.
	 *
	 * @param array<int|string, mixed> $property_definitions Array of property definitions from pattern.
	 * @return array<string, ComponentProperty>
	 */
	private static function build_property_map( array $property_definitions ): array {
		$map = array();
		foreach ( $property_definitions as $prop_data ) {
			$property = match ( true ) {
				$prop_data instanceof ComponentProperty => $prop_data,
				is_array( $prop_data )                  => ComponentProperty::from_array( $prop_data ),
				default                                 => null,
			};

			if ( null !== $property ) {
				$map[ $property->key ] = $property;
			}
		}
		return $map;
	}

	/**
	 * Resolve component properties from property definitions and instance attributes.
	 *
	 * Instance attributes are expected to be pre-resolved by ComponentBlock.
	 * This method resolves default values, merges them with instance attributes,
	 * and delegates type-specific resolution to each property's resolve_value().
	 *
	 * @param array<int|string, mixed>                      $property_definitions Array of property definitions from pattern.
	 * @param array<string, mixed>                          $instance_attributes  Instance attributes from component block (pre-resolved).
	 * @param array<int, array{key: string, source: mixed}> $sources              Sources for dynamic expression evaluation (used for defaults).
	 * @return array<string, mixed> Resolved properties array.
	 */
	public static function resolve_properties( array $property_definitions, array $instance_attributes, array $sources = array() ): array {
		$property_map   = self::build_property_map( $property_definitions );
		$resolved_props = array();

		foreach ( $property_map as $key => $property ) {
			// Condition Properties are "transparent"
			if ( $property instanceof ConditionProperty ) {
				$resolved_props += $property->resolve_value( $instance_attributes, $sources );
				continue;
			}

			$value = self::get_raw_value( $key, $property, $instance_attributes );
			if ( null === $value ) {
				continue;
			}

			$value                  = DynamicContentProcessor::apply( $value, array( 'sources' => $sources ) );
			$resolved_props[ $key ] = $property->resolve_value( $value, $sources );
		}

		return $resolved_props;
	}

	/**
	 * Get the raw value for a property key from instance attributes or defaults.
	 *
	 * @param string               $key                 The property key.
	 * @param ComponentProperty    $property            The property definition.
	 * @param array<string, mixed> $instance_attributes The instance attributes.
	 *
	 * @return mixed|null
	 */
	private static function get_raw_value( string $key, ComponentProperty $property, array $instance_attributes ) {
		if ( isset( $instance_attributes[ $key ] ) && null !== $instance_attributes[ $key ] ) {
			return $instance_attributes[ $key ];
		}

		return $property->default ?? null;
	}
}
