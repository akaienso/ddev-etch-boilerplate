<?php
/**
 * Group Property resolving tests.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Blocks\Utilities\Tests\PropertyResolving;

use WP_UnitTestCase;
use Etch\Blocks\Utilities\ComponentPropertyResolver;

/**
 * Class GroupPropertyTest
 *
 * Tests for group property resolution in ComponentPropertyResolver.
 */
class GroupPropertyTest extends WP_UnitTestCase {

	/**
	 * Helper to create a property definition.
	 *
	 * @param string $key       Property key.
	 * @param string $primitive Primitive type (string, number, boolean).
	 * @param mixed  $default   Default value.
	 * @return array<string, mixed>
	 */
	private function make_property( string $key, string $primitive, mixed $default ): array {
		return array(
			'key'     => $key,
			'name'    => ucfirst( $key ),
			'type'    => array( 'primitive' => $primitive ),
			'default' => $default,
		);
	}

	/**
	 * A settings group containing a single title string property.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function definitions_with_title_group(): array {
		return array(
			$this->make_group_definition(
				'settings',
				array(
					$this->make_property( 'title', 'string', 'Default Title' ),
				)
			),
		);
	}

	/**
	 * A nested outer > inner group with a label string property.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function definitions_with_nested_group(): array {
		return array(
			$this->make_group_definition(
				'outer',
				array(
					$this->make_group_definition(
						'inner',
						array(
							$this->make_property( 'label', 'string', 'Nested Default' ),
						)
					),
				)
			),
		);
	}

	/**
	 * Helper to create a group property definition.
	 *
	 * @param string                    $key        Property key.
	 * @param array<int, array>         $properties Sub-property definitions.
	 * @param array<string, mixed>|null $extra      Extra fields to merge.
	 * @return array<string, mixed>
	 */
	private function make_group_definition( string $key, array $properties, ?array $extra = null ): array {
		$def = array(
			'key'        => $key,
			'name'       => ucfirst( $key ),
			'type'       => array(
				'primitive'   => 'object',
				'specialized' => 'group',
			),
			'properties' => $properties,
		);

		if ( null !== $extra ) {
			$def = array_merge( $def, $extra );
		}

		return $def;
	}

	/** Sub-properties use their defaults when no instance attributes are provided. */
	public function test_sub_properties_resolve_to_defaults_when_no_instance_attributes() {
		$property_definitions = array(
			$this->make_group_definition(
				'settings',
				array(
					$this->make_property( 'title', 'string', 'Default Title' ),
					$this->make_property( 'count', 'number', 5 ),
				)
			),
		);

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, array() );

		$this->assertIsArray( $resolved['settings'] );
		$this->assertEquals( 'Default Title', $resolved['settings']['title'] );
		$this->assertEquals( 5.0, $resolved['settings']['count'] );
	}

	/** Instance attributes take precedence over sub-property defaults. */
	public function test_instance_attributes_override_sub_property_defaults() {
		$instance_attributes = array(
			'settings' => array(
				'title' => 'Custom Title',
			),
		);

		$resolved = ComponentPropertyResolver::resolve_properties( $this->definitions_with_title_group(), $instance_attributes );

		$this->assertEquals( 'Custom Title', $resolved['settings']['title'] );
	}

	/** Provided attributes merge with missing sub-property defaults. */
	public function test_partial_instance_attributes_merge_with_defaults() {
		$property_definitions = array(
			$this->make_group_definition(
				'settings',
				array(
					$this->make_property( 'title', 'string', 'Default Title' ),
					$this->make_property( 'subtitle', 'string', 'Default Subtitle' ),
				)
			),
		);

		$instance_attributes = array(
			'settings' => array(
				'title' => 'Custom Title',
				// subtitle not provided — should use default
			),
		);

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes );

		$this->assertEquals( 'Custom Title', $resolved['settings']['title'] );
		$this->assertEquals( 'Default Subtitle', $resolved['settings']['subtitle'] );
	}

	/** An invalid (non-JSON) string instance value falls back to defaults. */
	public function test_stringified_json_resolved() {
		$instance_attributes = array(
			'settings' => '{{"title": "Custom Title"}}',
		);

		$resolved = ComponentPropertyResolver::resolve_properties( $this->definitions_with_title_group(), $instance_attributes );

		$this->assertEquals( 'Custom Title', $resolved['settings']['title'] );
	}

	/** An invalid (non-JSON) string instance value falls back to defaults. */
	public function test_non_json_string_falls_back_to_defaults() {
		$instance_attributes = array(
			'settings' => 'not valid json',
		);

		$resolved = ComponentPropertyResolver::resolve_properties( $this->definitions_with_title_group(), $instance_attributes );

		$this->assertEquals( 'Default Title', $resolved['settings']['title'] );
	}

	/** Nested groups resolve their sub-properties recursively. */
	public function test_nested_groups_resolve_recursively() {
		$resolved = ComponentPropertyResolver::resolve_properties( $this->definitions_with_nested_group(), array() );

		$this->assertIsArray( $resolved['outer'] );
		$this->assertIsArray( $resolved['outer']['inner'] );
		$this->assertEquals( 'Nested Default', $resolved['outer']['inner']['label'] );
	}

	/** Instance attributes override defaults in nested groups. */
	public function test_nested_group_instance_attributes_override_defaults() {
		$instance_attributes = array(
			'outer' => array(
				'inner' => array(
					'label' => 'Overridden',
				),
			),
		);

		$resolved = ComponentPropertyResolver::resolve_properties( $this->definitions_with_nested_group(), $instance_attributes );

		$this->assertEquals( 'Overridden', $resolved['outer']['inner']['label'] );
	}

	/** String, number, and boolean sub-properties each resolve to the correct type. */
	public function test_mixed_sub_property_types_resolve_correctly() {
		$property_definitions = array(
			$this->make_group_definition(
				'settings',
				array(
					$this->make_property( 'label', 'string', 'Hello' ),
					$this->make_property( 'count', 'number', 42 ),
					$this->make_property( 'visible', 'boolean', true ),
				)
			),
		);

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, array() );

		$this->assertIsString( $resolved['settings']['label'] );
		$this->assertEquals( 'Hello', $resolved['settings']['label'] );
		$this->assertIsFloat( $resolved['settings']['count'] );
		$this->assertEquals( 42.0, $resolved['settings']['count'] );
		$this->assertIsBool( $resolved['settings']['visible'] );
		$this->assertTrue( $resolved['settings']['visible'] );
	}

	/** Dynamic expressions in sub-property defaults are resolved via data sources. */
	public function test_dynamic_expression_in_sub_property_default() {
		$property_definitions = array(
			$this->make_group_definition(
				'settings',
				array(
					$this->make_property( 'title', 'string', '{this.postTitle}' ),
				)
			),
		);

		$resolved = ComponentPropertyResolver::resolve_properties(
			$property_definitions,
			array(),
			array(
				array(
					'key'   => 'this',
					'source' => array(
						'postTitle' => 'Dynamic Title',
					),
				),
			)
		);

		$this->assertEquals( 'Dynamic Title', $resolved['settings']['title'] );
	}

	/** A group with no sub-properties resolves to an empty array. */
	public function test_empty_sub_properties_returns_empty_array() {
		$property_definitions = array(
			$this->make_group_definition( 'settings', array() ),
		);

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, array() );

		$this->assertIsArray( $resolved['settings'] );
		$this->assertEmpty( $resolved['settings'] );
	}

	/** A null instance value falls back to sub-property defaults. */
	public function test_null_instance_value_falls_back_to_defaults() {
		$instance_attributes = array(
			'settings' => null,
		);

		$resolved = ComponentPropertyResolver::resolve_properties( $this->definitions_with_title_group(), $instance_attributes );

		$this->assertEquals( 'Default Title', $resolved['settings']['title'] );
	}

	/** Group properties coexist with scalar properties at the same level. */
	public function test_group_coexists_with_other_property_types() {
		$property_definitions = array(
			$this->make_property( 'heading', 'string', 'Page Heading' ),
			$this->make_group_definition(
				'settings',
				array(
					$this->make_property( 'color', 'string', '#000' ),
				)
			),
			$this->make_property( 'enabled', 'boolean', true ),
		);

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, array() );

		$this->assertEquals( 'Page Heading', $resolved['heading'] );
		$this->assertIsArray( $resolved['settings'] );
		$this->assertEquals( '#000', $resolved['settings']['color'] );
		$this->assertTrue( $resolved['enabled'] );
	}

	/** Test dynamic data resolution in group properties. */
	public function test_it_resolves_when_dynamic_data_is_passed() {
		$property_definitions = array(
			$this->make_group_definition(
				'settings',
				array(
					$this->make_property( 'title', 'string', null ),
				)
			),
		);

		$instance_attributes = array(
			'settings' => '{props.settings}',
		);

		$resolved = ComponentPropertyResolver::resolve_properties(
			$property_definitions,
			$instance_attributes,
			array(
				array(
					'key'   => 'props',
					'source' => array(
						'settings' => array(
							'title' => 'Dynamic Title from Instance',
						),
					),
				),
			)
		);

		$this->assertEquals( 'Dynamic Title from Instance', $resolved['settings']['title'] );
	}
}
