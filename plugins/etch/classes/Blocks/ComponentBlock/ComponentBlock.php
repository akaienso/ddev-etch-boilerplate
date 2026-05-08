<?php
/**
 * Component Block
 *
 * Renders reusable component patterns (wp_block post type) with dynamic context support.
 * Allows passing props to component instances and resolves dynamic expressions in
 * component attributes before rendering pattern blocks.
 *
 * @package Etch
 */

namespace Etch\Blocks\ComponentBlock;

use Etch\Blocks\Types\ComponentAttributes;
use Etch\Blocks\Types\SlotContentAttributes;
use Etch\Blocks\Global\ScriptRegister;
use Etch\Blocks\Global\ComponentSlotContextProvider;
use Etch\Blocks\Global\CachedPattern;
use Etch\Blocks\Global\DynamicContent\DynamicContentEntry;
use Etch\Blocks\Global\DynamicContent\DynamicContextProvider;
use Etch\Blocks\Global\Utilities\DynamicContentProcessor;
use Etch\Blocks\Utilities\EtchTypeAsserter;
use Etch\Blocks\Utilities\ShortcodeProcessor;
use Etch\Blocks\Utilities\ComponentPropertyResolver;

/**
 * ComponentBlock class
 *
 * Handles rendering of etch/component blocks which reference pattern posts.
 * Resolves dynamic expressions in component attributes and provides component
 * props/slots context to child blocks via DynamicContextProvider.
 */
class ComponentBlock {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_block' ) );
	}

	/**
	 * Register the block
	 *
	 * @return void
	 */
	public function register_block() {
		register_block_type(
			'etch/component',
			array(
				'api_version' => '3',
				'attributes' => array(
					'ref' => array(
						'type' => 'number',
						'default' => null,
					),
					'attributes' => array(
						'type' => 'object',
						'default' => array(),
					),
				),
				'supports' => array(
					'html' => false,
					'className' => false,
					'customClassName' => false,
					'innerBlocks' => true,
				),
				'render_callback' => array( $this, 'render_block' ),
				'skip_inner_blocks' => true,
			)
		);
	}

	/**
	 * Render the block
	 *
	 * Fetches the referenced pattern post (wp_block) and renders its blocks.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content Block content (inner blocks).
	 * @param \WP_Block|null       $block WP_Block instance.
	 * @return string Rendered block HTML.
	 */
	public function render_block( $attributes, $content, $block = null ) {
		// return '<p>TESTING COMPONENT BLOCK</p>';
		$attrs = ComponentAttributes::from_array( $attributes );

		ScriptRegister::register_script( $attrs );

		if ( null === $attrs->ref ) {
			return '';
		}

		if ( ! ( $block instanceof \WP_Block ) ) {
			return '';
		}

		// Parent scope (full incoming stack) for resolving this component instance's attributes.
		$parent_sources = DynamicContextProvider::get_sources_for_wp_block( $block );

		$pattern_blocks = CachedPattern::get_pattern_parsed_blocks( $attrs->ref );

		if ( empty( $pattern_blocks ) ) {
			return '';
		}

		// Extract slot contents from component instance inner blocks.
		// Use parsed_block from WP_Block instances to avoid manual conversion
		$slots_map = ComponentSlotContextProvider::extract_slot_contents( $block );

		// Capture current dynamic entries (non-global) for slot rendering.
		$parent_sources_stack = DynamicContextProvider::get_sources_for_block();
		$parent_dynamic_entries = array_values(
			array_filter(
				DynamicContextProvider::get_stack()->all(),
				static function ( DynamicContentEntry $entry ): bool {
					return 'global' !== $entry->get_type();
				}
			)
		);

		$block->attributes = $attributes;

		// Reset dynamic provider scope to globals-only, then push this component's entries.
		DynamicContextProvider::clear();
		$property_definitions = CachedPattern::get_pattern_properties( $attrs->ref );
		$instance_attributes_raw = EtchTypeAsserter::to_array( $attrs->attributes ?? array() );
		$instance_attributes = array();
		foreach ( $instance_attributes_raw as $attr_key => $attr_value ) {
			if ( is_string( $attr_key ) ) {
				$instance_attributes[ $attr_key ] = $attr_value;
			}
		}

		$component_source_value = array();
		$pattern_post = CachedPattern::get_pattern_post( $attrs->ref );
		$component_name = $pattern_post ? $pattern_post->post_title : '';
		$component_key = CachedPattern::get_pattern_key( $attrs->ref );
		// Resolve every property against the definition with no instance
		// attributes — yields the fully resolved default per prop (class
		// IDs → names, media ID → URL, groups → nested, etc.).
		$resolved_defaults = ComponentPropertyResolver::resolve_properties( $property_definitions, array(), $parent_sources );
		$component_source_value = array(
			'id'       => $attrs->ref,
			'name'     => $component_name,
			'key'      => $component_key,
			'defaults' => $resolved_defaults,
		);

		DynamicContextProvider::push(
			new DynamicContentEntry(
				'component-metadata',
				'component',
				$component_source_value
			)
		);

		$resolved_props = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes, $parent_sources );

		DynamicContextProvider::push(
			new DynamicContentEntry(
				'component',
				'props',
				$resolved_props,
				array(
					'parentDynamicContent' => array_map(
						static function ( DynamicContentEntry $entry ): array {
							return $entry->to_array();
						},
						$parent_dynamic_entries
					),
				)
			)
		);

		$slots_object = array();
		foreach ( $slots_map as $slot_name => $slot_content ) {
			if ( ! is_string( $slot_name ) || '' === $slot_name ) {
				continue;
			}
			$slots_object[ $slot_name ] = array( 'empty' => empty( $slot_content ) );
		}
		DynamicContextProvider::push( new DynamicContentEntry( 'component-slots', 'slots', $slots_object ) );

		// Push slot context onto stack for lazy placeholder rendering
		ComponentSlotContextProvider::push( $slots_map, $block, null, $parent_dynamic_entries, $parent_sources_stack );

		// Render pattern blocks as-is - placeholders will render their slots on-demand
		$rendered = '';
		foreach ( $pattern_blocks as $pattern_block ) {
			if ( ! is_array( $pattern_block ) ) {
				continue;
			}
			$rendered .= render_block( $pattern_block );
		}

		// Pop slot context from stack
		ComponentSlotContextProvider::pop();

		// Restore dynamic provider stack to the parent scope.
		DynamicContextProvider::clear();
		foreach ( $parent_dynamic_entries as $entry ) {
			DynamicContextProvider::push( $entry );
		}

		$rendered = ShortcodeProcessor::process( $rendered, 'etch/component' );

		return $rendered;
	}
}
