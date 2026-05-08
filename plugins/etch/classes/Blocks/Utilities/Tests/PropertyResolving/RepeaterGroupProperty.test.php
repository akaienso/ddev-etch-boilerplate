<?php
/**
 * Repeater Group Property resolving tests.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Blocks\Utilities\Tests\PropertyResolving;

use WP_UnitTestCase;
use Etch\Blocks\Utilities\ComponentPropertyResolver;

/**
 * Class RepeaterGroupPropertyTest
 *
 * Tests for repeater group property resolution in ComponentPropertyResolver.
 */
class RepeaterGroupPropertyTest extends WP_UnitTestCase {

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
	 * Helper to create a repeater group property definition.
	 *
	 * @param string            $key        Property key.
	 * @param array<int, array> $properties Sub-property definitions.
	 * @return array<string, mixed>
	 */
	private function make_repeater_definition( string $key, array $properties ): array {
		return array(
			'key'        => $key,
			'name'       => ucfirst( $key ),
			'type'       => array(
				'primitive'   => 'array',
				'specialized' => 'repeater',
			),
			'properties' => $properties,
		);
	}

	/**
	 * A repeater with title and count sub-properties.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function definitions_with_items_repeater(): array {
		return array(
			$this->make_repeater_definition(
				'items',
				array(
					$this->make_property( 'title', 'string', 'Default Title' ),
					$this->make_property( 'count', 'number', 5 ),
				)
			),
		);
	}

	/**
	 * Data provider for repeater item resolution from different input formats.
	 *
	 * @return array<string, array{mixed, array<int, array<string, mixed>>}>
	 */
	public function repeater_input_formats(): array {
		return array(
			'array input'          => array(
				array(
					array(
						'title' => 'First',
						'count' => 1,
					),
					array(
						'title' => 'Second',
						'count' => 2,
					),
				),
				array(
					array(
						'title' => 'First',
						'count' => 1.0,
					),
					array(
						'title' => 'Second',
						'count' => 2.0,
					),
				),
			),
			'stringified JSON with {[...]} wrapper' => array(
				'{[{"title":"Hello","count":"5"},{"title":"World","count":"10"}]}',
				array(
					array(
						'title' => 'Hello',
						'count' => 5.0,
					),
					array(
						'title' => 'World',
						'count' => 10.0,
					),
				),
			),
		);
	}

	/**
	 * Repeater resolves items from different input formats.
	 *
	 * @dataProvider repeater_input_formats
	 *
	 * @param mixed                            $input    The input value for the repeater.
	 * @param array<int, array<string, mixed>> $expected The expected resolved items.
	 * @return void
	 */
	public function test_resolves_items_from_input( $input, array $expected ) {
		$instance_attributes = array( 'items' => $input );

		$resolved = ComponentPropertyResolver::resolve_properties( $this->definitions_with_items_repeater(), $instance_attributes );

		$this->assertIsArray( $resolved['items'] );
		$this->assertEquals( $expected, $resolved['items'] );
	}

	/**
	 * Data provider for inputs that resolve to an empty repeater array.
	 *
	 * @return array<string, array{array<string, mixed>}>
	 */
	public function empty_repeater_inputs(): array {
		return array(
			'no attributes'    => array( array() ),
			'empty JSON {[]}'  => array( array( 'items' => '{[]}' ) ),
			'invalid string'   => array( array( 'items' => 'not valid json' ) ),
		);
	}

	/**
	 * Repeater resolves to empty array when input has no valid items.
	 *
	 * @dataProvider empty_repeater_inputs
	 *
	 * @param array<string, mixed> $instance_attributes The instance attributes.
	 * @return void
	 */
	public function test_resolves_to_empty_array( array $instance_attributes ) {
		$resolved = ComponentPropertyResolver::resolve_properties( $this->definitions_with_items_repeater(), $instance_attributes );

		$this->assertIsArray( $resolved['items'] );
		$this->assertEmpty( $resolved['items'] );
	}

	/** Sub-properties use their defaults when item has partial data. */
	public function test_partial_item_data_fills_defaults() {
		$instance_attributes = array(
			'items' => array(
				array( 'title' => 'Custom' ),
			),
		);

		$resolved = ComponentPropertyResolver::resolve_properties( $this->definitions_with_items_repeater(), $instance_attributes );

		$this->assertCount( 1, $resolved['items'] );
		$this->assertEquals( 'Custom', $resolved['items'][0]['title'] );
		$this->assertEquals( 5.0, $resolved['items'][0]['count'] );
	}

	/** Repeater with no sub-properties resolves items to empty arrays. */
	public function test_empty_sub_properties_resolves_items_to_empty_arrays() {
		$property_definitions = array(
			$this->make_repeater_definition( 'items', array() ),
		);

		$instance_attributes = array(
			'items' => array(
				array( 'title' => 'ignored' ),
			),
		);

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes );

		$this->assertIsArray( $resolved['items'] );
		$this->assertCount( 1, $resolved['items'] );
		$this->assertEmpty( $resolved['items'][0] );
	}

	/** Mixed sub-property types resolve correctly within each item. */
	public function test_mixed_sub_property_types_resolve_correctly() {
		$property_definitions = array(
			$this->make_repeater_definition(
				'items',
				array(
					$this->make_property( 'label', 'string', 'Hello' ),
					$this->make_property( 'count', 'number', 42 ),
					$this->make_property( 'visible', 'boolean', true ),
				)
			),
		);

		$instance_attributes = array(
			'items' => array(
				array(
					'label' => 'Test',
					'count' => '7',
					'visible' => 'false',
				),
			),
		);

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes );

		$this->assertIsString( $resolved['items'][0]['label'] );
		$this->assertEquals( 'Test', $resolved['items'][0]['label'] );
		$this->assertIsFloat( $resolved['items'][0]['count'] );
		$this->assertEquals( 7.0, $resolved['items'][0]['count'] );
		$this->assertIsBool( $resolved['items'][0]['visible'] );
		$this->assertFalse( $resolved['items'][0]['visible'] );
	}

	/** Repeater coexists with other property types at the same level. */
	public function test_repeater_coexists_with_other_property_types() {
		$property_definitions = array(
			$this->make_property( 'heading', 'string', 'Page Heading' ),
			$this->make_repeater_definition(
				'items',
				array(
					$this->make_property( 'title', 'string', 'Default' ),
				)
			),
			$this->make_property( 'enabled', 'boolean', true ),
		);

		$instance_attributes = array(
			'items' => array(
				array( 'title' => 'Item 1' ),
			),
		);

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes );

		$this->assertEquals( 'Page Heading', $resolved['heading'] );
		$this->assertIsArray( $resolved['items'] );
		$this->assertCount( 1, $resolved['items'] );
		$this->assertEquals( 'Item 1', $resolved['items'][0]['title'] );
		$this->assertTrue( $resolved['enabled'] );
	}

	/** Dynamic data resolution works for repeater items. */
	public function test_dynamic_expression_in_repeater_item() {
		$property_definitions = array(
			$this->make_repeater_definition(
				'items',
				array(
					$this->make_property( 'title', 'string', null ),
				)
			),
		);

		$instance_attributes = array(
			'items' => '{props.items}',
		);

		$resolved = ComponentPropertyResolver::resolve_properties(
			$property_definitions,
			$instance_attributes,
			array(
				array(
					'key'    => 'props',
					'source' => array(
						'items' => array(
							array( 'title' => 'Dynamic Item' ),
						),
					),
				),
			)
		);

		$this->assertIsArray( $resolved['items'] );
		$this->assertCount( 1, $resolved['items'] );
		$this->assertEquals( 'Dynamic Item', $resolved['items'][0]['title'] );
	}
}
