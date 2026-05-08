<?php
/**
 * ComponentProperty test class.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Blocks\Types\Tests;

use WP_UnitTestCase;
use Etch\Blocks\Types\ComponentProperty\ComponentProperty;
use Etch\Blocks\Types\ComponentProperty\StringProperty;
use Etch\Blocks\Types\ComponentProperty\LoopProperty;
use Etch\Blocks\Types\ComponentProperty\NumberProperty;
use Etch\Blocks\Types\ComponentProperty\BooleanProperty;
use Etch\Blocks\Types\ComponentProperty\ObjectProperty;
use Etch\Blocks\Types\ComponentProperty\ArrayProperty;
use Etch\Blocks\Types\ComponentProperty\ClassProperty;
use Etch\Blocks\Types\ComponentProperty\GroupProperty;
use Etch\Blocks\Types\ComponentProperty\ConditionProperty;

/**
 * Class ComponentPropertyTest
 *
 * Tests for ComponentProperty::from_array() factory and to_array() serialization.
 */
class ComponentPropertyTest extends WP_UnitTestCase {

	/**
	 * Build a property data array.
	 *
	 * @param string               $key         Property key.
	 * @param string               $primitive   Primitive type.
	 * @param string|null          $specialized Optional specialized type.
	 * @param array<string, mixed> $extra       Additional fields merged into the result.
	 * @return array<string, mixed>
	 */
	private function make_property_data( string $key, string $primitive, ?string $specialized = null, array $extra = array() ): array {
		$type = array( 'primitive' => $primitive );
		if ( null !== $specialized ) {
			$type['specialized'] = $specialized;
		}
		return array_merge(
			array(
				'key'  => $key,
				'type' => $type,
			),
			$extra
		);
	}

	/** From_array returns null when key is missing. */
	public function test_from_array_returns_null_when_key_is_missing() {
		$this->assertNull( ComponentProperty::from_array( array( 'name' => 'Title' ) ) );
	}

	/** From_array returns null when key is empty. */
	public function test_from_array_returns_null_when_key_is_empty() {
		$this->assertNull( ComponentProperty::from_array( array( 'key' => '' ) ) );
	}

	/** From_array returns null when primitive is invalid. */
	public function test_from_array_returns_null_when_primitive_is_invalid() {
		$this->assertNull(
			ComponentProperty::from_array( $this->make_property_data( 'title', 'invalid' ) )
		);
	}

	/**
	 * Data provider for type dispatch.
	 *
	 * @return array<string, array{0: string, 1: string|null, 2: string}>
	 */
	public function type_dispatch_provider(): array {
		return array(
			'string'    => array( 'string', null, StringProperty::class ),
			'loop'      => array( 'string', 'array', LoopProperty::class ),
			'condition' => array( 'string', 'condition', ConditionProperty::class ),
			'number'    => array( 'number', null, NumberProperty::class ),
			'boolean'   => array( 'boolean', null, BooleanProperty::class ),
			'object'    => array( 'object', null, ObjectProperty::class ),
			'array'     => array( 'array', null, ArrayProperty::class ),
			'class'     => array( 'array', 'class', ClassProperty::class ),
			'group'     => array( 'object', 'group', GroupProperty::class ),
		);
	}

	/**
	 * From_array dispatches to correct subclass.
	 *
	 * @dataProvider type_dispatch_provider
	 *
	 * @param string      $primitive   Primitive type.
	 * @param string|null $specialized Specialized type.
	 * @param string      $expected    Expected class name.
	 */
	public function test_from_array_dispatches_to_correct_subclass( string $primitive, ?string $specialized, string $expected ) {
		$prop = ComponentProperty::from_array(
			$this->make_property_data( 'test', $primitive, $specialized )
		);

		$this->assertInstanceOf( $expected, $prop );
		$this->assertEquals( $primitive, $prop->get_primitive() );
	}

	/** From_array populates base fields from input. */
	public function test_from_array_populates_base_fields() {
		$prop = ComponentProperty::from_array(
			$this->make_property_data(
				'title',
				'string',
				null,
				array(
					'name'    => 'Title',
					'default' => 'Hello',
				)
			)
		);

		$this->assertNotNull( $prop );
		$this->assertEquals( 'title', $prop->key );
		$this->assertEquals( 'Title', $prop->name );
		$this->assertEquals( 'Hello', $prop->default );
	}

	/** From_array creates string property with specialized type. */
	public function test_from_array_creates_specialized_string_property() {
		$prop = ComponentProperty::from_array(
			$this->make_property_data( 'color', 'string', 'color' )
		);

		$this->assertInstanceOf( StringProperty::class, $prop );
		$this->assertEquals( 'color', $prop->get_specialized() );
	}

	/** From_array creates string property with select options. */
	public function test_from_array_creates_string_property_with_options() {
		$prop = ComponentProperty::from_array(
			$this->make_property_data(
				'size',
				'string',
				'select',
				array(
					'options'             => array( 'sm', 'md', 'lg' ),
					'selectOptionsString' => 'sm, md, lg',
				)
			)
		);

		$this->assertInstanceOf( StringProperty::class, $prop );
		assert( $prop instanceof StringProperty );
		$this->assertEquals( array( 'sm', 'md', 'lg' ), $prop->options );
		$this->assertEquals( 'sm, md, lg', $prop->selectOptionsString );
	}

	/** From_array creates group property with nested properties. */
	public function test_from_array_creates_group_property_with_nested_properties() {
		$prop = ComponentProperty::from_array(
			$this->make_property_data(
				'settings',
				'object',
				'group',
				array(
					'properties' => array(
						$this->make_property_data( 'title', 'string', null, array( 'default' => 'Hello' ) ),
						$this->make_property_data( 'count', 'number', null, array( 'default' => 5 ) ),
					),
				)
			)
		);

		$this->assertInstanceOf( GroupProperty::class, $prop );
		assert( $prop instanceof GroupProperty );
		$this->assertCount( 2, $prop->properties );
		$this->assertInstanceOf( StringProperty::class, $prop->properties[0] );
		$this->assertEquals( 'title', $prop->properties[0]->key );
		$this->assertInstanceOf( NumberProperty::class, $prop->properties[1] );
		$this->assertEquals( 'count', $prop->properties[1]->key );
	}

	/** From_array uses key as name when name is missing. */
	public function test_from_array_uses_key_as_name_when_name_is_missing() {
		$prop = ComponentProperty::from_array(
			$this->make_property_data( 'title', 'string' )
		);

		$this->assertNotNull( $prop );
		$this->assertEquals( 'title', $prop->name );
	}

	/** To_array round-trips a string property. */
	public function test_to_array_round_trips_string_property() {
		$input = $this->make_property_data(
			'title',
			'string',
			null,
			array(
				'name'    => 'Title',
				'default' => 'Hello',
			)
		);

		$prop   = ComponentProperty::from_array( $input );
		$output = $prop->to_array();

		$this->assertEquals( $input, $output );
	}

	/**
	 * Data provider for round-trip tests of properties with nested children.
	 *
	 * @return array<string, array{0: string, 1: string, 2: string|null}>
	 */
	public function nested_property_round_trip_provider(): array {
		return array(
			'group'     => array( 'settings', 'object', 'group' ),
			'condition' => array( 'show_if', 'string', 'condition' ),
		);
	}

	/**
	 * To_array round-trips a property with nested children.
	 *
	 * @dataProvider nested_property_round_trip_provider
	 *
	 * @param string      $key         Property key.
	 * @param string      $primitive   Primitive type.
	 * @param string|null $specialized Specialized type.
	 */
	public function test_to_array_round_trips_property_with_children( string $key, string $primitive, ?string $specialized ) {
		$input = $this->make_property_data(
			$key,
			$primitive,
			$specialized,
			array(
				'name'       => ucfirst( $key ),
				'default'    => 'condition' === $specialized ? 'this.isVisible' : null,
				'properties' => array(
					$this->make_property_data(
						'title',
						'string',
						null,
						array(
							'name'    => 'Title',
							'default' => 'Hello',
						)
					),
				),
			)
		);

		$prop   = ComponentProperty::from_array( $input );
		$output = $prop->to_array();

		$this->assertEquals( $key, $output['key'] );
		$this->assertArrayHasKey( 'properties', $output );
		$this->assertCount( 1, $output['properties'] );
		$this->assertEquals( 'title', $output['properties'][0]['key'] );
	}

	/** To_array omits null fields. */
	public function test_to_array_omits_null_fields() {
		$prop   = ComponentProperty::from_array(
			$this->make_property_data( 'title', 'string' )
		);
		$output = $prop->to_array();

		$this->assertArrayNotHasKey( 'default', $output );
		$this->assertArrayNotHasKey( 'options', $output );
		$this->assertArrayNotHasKey( 'selectOptionsString', $output );
		$this->assertArrayNotHasKey( 'properties', $output );
		$this->assertArrayNotHasKey( 'keyTouched', $output );
	}

	/** From_array creates condition property with nested properties. */
	public function test_from_array_creates_condition_property_with_nested_properties() {
		$prop = ComponentProperty::from_array(
			$this->make_property_data(
				'show_if',
				'string',
				'condition',
				array(
					'default'    => 'this.isVisible',
					'properties' => array(
						$this->make_property_data( 'title', 'string', null, array( 'default' => 'Hello' ) ),
						$this->make_property_data( 'count', 'number', null, array( 'default' => 5 ) ),
					),
				)
			),
		);

		$this->assertInstanceOf( ConditionProperty::class, $prop );
		assert( $prop instanceof ConditionProperty );
		$this->assertEquals( 'this.isVisible', $prop->default );
		$this->assertCount( 2, $prop->properties );
		$this->assertInstanceOf( StringProperty::class, $prop->properties[0] );
		$this->assertInstanceOf( NumberProperty::class, $prop->properties[1] );
	}

	/** From_array creates condition property with empty properties. */
	public function test_from_array_creates_condition_property_with_empty_properties() {
		$prop = ComponentProperty::from_array(
			$this->make_property_data(
				'show_if',
				'string',
				'condition',
				array(
					'default'    => 'this.active',
					'properties' => array(),
				)
			),
		);

		$this->assertInstanceOf( ConditionProperty::class, $prop );
		assert( $prop instanceof ConditionProperty );
		$this->assertCount( 0, $prop->properties );
	}

	/** To_array includes keyTouched when set. */
	public function test_to_array_includes_key_touched_when_set() {
		$prop   = ComponentProperty::from_array(
			$this->make_property_data( 'title', 'string', null, array( 'keyTouched' => true ) )
		);
		$output = $prop->to_array();

		$this->assertArrayHasKey( 'keyTouched', $output );
		$this->assertTrue( $output['keyTouched'] );
	}
}
