<?php
/**
 * Condition Property resolving tests.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Blocks\Utilities\Tests\PropertyResolving;

use WP_UnitTestCase;
use Etch\Blocks\Utilities\ComponentPropertyResolver;

/**
 * Class ConditionPropertyTest
 *
 * Tests for condition property resolution in ComponentPropertyResolver.
 */
class ConditionPropertyTest extends WP_UnitTestCase {

	/**
	 * Helper to create a scalar property definition.
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
	 * Helper to create a condition property definition.
	 *
	 * @param string            $key        Property key.
	 * @param string            $condition  Condition expression.
	 * @param array<int, array> $properties Sub-property definitions.
	 * @return array<string, mixed>
	 */
	private function make_condition_definition( string $key, string $condition, array $properties ): array {
		return array(
			'key'        => $key,
			'name'       => ucfirst( $key ),
			'type'       => array(
				'primitive'   => 'string',
				'specialized' => 'condition',
			),
			'default'    => $condition,
			'properties' => $properties,
		);
	}

	/** Condition child properties are flattened into the parent scope. */
	public function test_child_properties_flatten_into_parent_scope() {
		$property_definitions = array(
			$this->make_condition_definition(
				'show_if',
				'this.isVisible',
				array(
					$this->make_property( 'title', 'string', 'Default Title' ),
					$this->make_property( 'count', 'number', 5 ),
				)
			),
		);

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, array() );

		$this->assertArrayHasKey( 'title', $resolved );
		$this->assertArrayHasKey( 'count', $resolved );
		$this->assertArrayNotHasKey( 'show_if', $resolved );
		$this->assertEquals( 'Default Title', $resolved['title'] );
		$this->assertEquals( 5.0, $resolved['count'] );
	}

	/** Instance attributes override condition child property defaults. */
	public function test_instance_attributes_override_child_defaults() {
		$property_definitions = array(
			$this->make_condition_definition(
				'show_if',
				'this.isVisible',
				array(
					$this->make_property( 'title', 'string', 'Default Title' ),
				)
			),
		);

		$instance_attributes = array(
			'title' => 'Custom Title',
		);

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes );

		$this->assertEquals( 'Custom Title', $resolved['title'] );
	}

	/** Condition children coexist with sibling properties at the same level. */
	public function test_condition_children_coexist_with_siblings() {
		$property_definitions = array(
			$this->make_property( 'heading', 'string', 'Page Heading' ),
			$this->make_condition_definition(
				'show_if',
				'this.isVisible',
				array(
					$this->make_property( 'subtitle', 'string', 'Default Subtitle' ),
				)
			),
			$this->make_property( 'enabled', 'boolean', true ),
		);

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, array() );

		$this->assertEquals( 'Page Heading', $resolved['heading'] );
		$this->assertEquals( 'Default Subtitle', $resolved['subtitle'] );
		$this->assertTrue( $resolved['enabled'] );
		$this->assertArrayNotHasKey( 'show_if', $resolved );
	}

	/** A condition with no child properties resolves to nothing. */
	public function test_empty_condition_adds_nothing_to_resolved() {
		$property_definitions = array(
			$this->make_property( 'heading', 'string', 'Page Heading' ),
			$this->make_condition_definition( 'show_if', 'this.active', array() ),
		);

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, array() );

		$this->assertCount( 1, $resolved );
		$this->assertEquals( 'Page Heading', $resolved['heading'] );
	}

	/** Nested conditions flatten recursively into the parent scope. */
	public function test_nested_conditions_flatten_recursively() {
		$property_definitions = array(
			$this->make_condition_definition(
				'outer_cond',
				'this.showOuter',
				array(
					$this->make_condition_definition(
						'inner_cond',
						'this.showInner',
						array(
							$this->make_property( 'deep_prop', 'string', 'Deep Value' ),
						)
					),
				)
			),
		);

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, array() );

		$this->assertArrayHasKey( 'deep_prop', $resolved );
		$this->assertEquals( 'Deep Value', $resolved['deep_prop'] );
		$this->assertArrayNotHasKey( 'outer_cond', $resolved );
		$this->assertArrayNotHasKey( 'inner_cond', $resolved );
	}

	/** Condition children can contain group properties. */
	public function test_condition_can_contain_group_property() {
		$property_definitions = array(
			$this->make_condition_definition(
				'show_if',
				'this.isVisible',
				array(
					array(
						'key'        => 'settings',
						'name'       => 'Settings',
						'type'       => array(
							'primitive'   => 'object',
							'specialized' => 'group',
						),
						'properties' => array(
							$this->make_property( 'color', 'string', '#000' ),
						),
					),
				)
			),
		);

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, array() );

		$this->assertArrayHasKey( 'settings', $resolved );
		$this->assertIsArray( $resolved['settings'] );
		$this->assertEquals( '#000', $resolved['settings']['color'] );
	}
}
