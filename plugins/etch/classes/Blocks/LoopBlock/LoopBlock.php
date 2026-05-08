<?php
/**
 * Loop Block
 *
 * Renders its inner blocks for each item of a resolved collection.
 * Supports loop presets via LoopHandlerManager and arbitrary targets resolved
 * through dynamic expressions with modifiers. Loop params can be dynamic.
 *
 * @package Etch\Blocks\LoopBlock
 */

namespace Etch\Blocks\LoopBlock;

use Etch\Blocks\Types\LoopAttributes;
use Etch\Blocks\Global\ScriptRegister;
use Etch\Blocks\Global\DynamicContent\DynamicContentEntry;
use Etch\Blocks\Global\DynamicContent\DynamicContextProvider;
use Etch\Blocks\Utilities\Utils;
use Etch\Blocks\Global\Utilities\Modifiers;
use Etch\Blocks\Global\Utilities\DynamicContentProcessor;
use Etch\Blocks\Global\Utilities\ExpressionPath;
use Etch\Blocks\Global\Utilities\ModifierParser;
use Etch\Blocks\Global\Utilities\LoopHandlerManager;

/**
 * LoopBlock class
 */
class LoopBlock {

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
			'etch/loop',
			array(
				'api_version' => '3',
				'attributes' => array(
					'target' => array(
						'type' => 'string',
						'default' => '',
					),
					'itemId' => array(
						'type' => 'string',
						'default' => '',
					),
					'indexId' => array(
						'type' => 'string',
						'default' => '',
					),
					'loopId' => array(
						'type' => 'string',
						'default' => null,
					),
					'loopParams' => array(
						'type' => 'object',
						'default' => null,
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
	 * Two distinct pipelines for loop resolution:
	 * 1. loopId pipeline - Direct loop preset reference, uses loopParams attribute for arguments.
	 *    When loopId is set, target can contain modifiers (e.g., "slice(1)") to apply to the loop data.
	 * 2. target pipeline - Expression-based resolution, params extracted from target string
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content Block content (unused - we render children manually).
	 * @param \WP_Block|null       $block WP_Block instance (provides access to inner blocks and parent).
	 * @return string Rendered HTML for all loop iterations.
	 */
	public function render_block( array $attributes, string $content = '', $block = null ): string {
		$attrs = LoopAttributes::from_array( $attributes );

		ScriptRegister::register_script( $attrs );

		$sources = DynamicContextProvider::get_sources_for_wp_block( $block );

		$resolved_items = $this->resolve_items( $attrs, $sources );

		if ( empty( $resolved_items ) ) {
			return '';
		}

		// Prepare item and index keys
		$item_key = null !== $attrs->itemId && '' !== $attrs->itemId ? $attrs->itemId : 'item';
		$index_key = null !== $attrs->indexId && '' !== $attrs->indexId ? $attrs->indexId : null;

		// Render inner blocks for each item
		$rendered = '';
		$inner_blocks = array();

		$hasInnerBlocks = $block instanceof \WP_Block && isset( $block->parsed_block['innerBlocks'] ) && is_array( $block->parsed_block['innerBlocks'] );
		if ( $hasInnerBlocks ) {
			$inner_blocks = $block->parsed_block['innerBlocks'];
		}

		foreach ( $resolved_items as $index => $item ) {
			DynamicContextProvider::push(
				new DynamicContentEntry(
					'loop',
					$item_key,
					$item,
					array(
						'currentIndex' => $index,
					)
				)
			);
			if ( null !== $index_key ) {
				DynamicContextProvider::push(
					new DynamicContentEntry(
						'loop-index',
						$index_key,
						$index,
						array(
							'currentIndex' => $index,
						)
					)
				);
			}

			foreach ( $inner_blocks as $child ) {
				$rendered .= render_block( $child );
			}

			if ( null !== $index_key ) {
				DynamicContextProvider::pop();
			}
			DynamicContextProvider::pop();
		}

		return $rendered;
	}

	/**
	 * Resolve loop items based on the provided attributes and sources.
	 *
	 * @param LoopAttributes                                $attrs  The loop attributes.
	 * @param array<int, array{key: string, source: mixed}> $sources The current sources for expression resolution.
	 * @return array<int|string, mixed> The resolved loop items.
	 */
	private function resolve_items( LoopAttributes $attrs, array $sources ): array {
		if ( null !== $attrs->loopId && '' !== $attrs->loopId ) {
			return $this->resolve_from_loop_id( $attrs, $sources );
		}

		return $this->parse_loop_target( $attrs->target ?? '', $sources );
	}

	/**
	 * Resolve loop items from the loop ID.
	 *
	 * @param LoopAttributes                                $attrs  The loop attributes.
	 * @param array<int, array{key: string, source: mixed}> $sources The current sources for expression resolution.
	 * @return array<int|string, mixed> The resolved loop items.
	 */
	private function resolve_from_loop_id( LoopAttributes $attrs, array $sources ): array {
		$params   = $this->resolve_loop_params( $attrs->loopParams, $sources );
		$loop_key = LoopHandlerManager::strip_loop_params_from_string( $attrs->loopId ?? '' );

		if ( ! LoopHandlerManager::is_valid_loop_id( $loop_key ) ) {
			return array();
		}

		$items = LoopHandlerManager::get_loop_preset_data( $loop_key, $params );

		if ( null !== $attrs->target && '' !== $attrs->target ) {
			$items = $this->apply_target_modifiers( $items, $attrs->target, $sources );
		}

		return $items;
	}

	/**
	 * Parse a loop target string into loop items.
	 *
	 * @param string                                        $target  The target string.
	 * @param array<int, array{key: string, source: mixed}> $sources The current sources for expression resolution.
	 * @return array<int|string, mixed> The resolved loop items.
	 */
	private function parse_loop_target( string $target, array $sources ): array {
		if ( '' === $target ) {
			return array();
		}

		// Handle JSON arrays/objects directly (inline array loops)
		$trimmed_target = trim( $target );
		$is_potential_json = ( str_starts_with( $trimmed_target, '[' ) && str_ends_with( $trimmed_target, ']' ) ) ||
			( str_starts_with( $trimmed_target, '{' ) && str_ends_with( $trimmed_target, '}' ) );

		if ( $is_potential_json ) {
			$json_value = json_decode( $trimmed_target, true );
			if ( is_array( $json_value ) ) {
				if ( array_is_list( $json_value ) ) {
					return $json_value;
				}

				return array( $json_value );
			}
		}

		// Use standard expression parsing to split the path
		$parts = ExpressionPath::split( $target );

		$current_context = null;

		foreach ( $parts as $i => $part ) {
			// Parse the part to extract method and args
			$parsed = ModifierParser::parse( $part );
			$method = $parsed['method'];
			$args = $parsed['args'];

			if ( ! $method ) {
				$method = $part;
			}

			if ( null === $current_context ) {
				// First part: check sources
				$current_context = $this->resolve_from_sources( $method, $sources );
			} else {
				$modifier_func = Modifiers::get_modifier( $method, array( 'sources' => $sources ) );

				if ( null !== $modifier_func && ModifierParser::is_modifier( $part ) ) {
					// Apply the modifier
					$current_context = Modifiers::apply_modifier(
						$current_context,
						$part,
						array(
							'sources' => $sources,
						)
					);
				} elseif (
					is_object( $current_context ) &&
					property_exists( $current_context, $method )
				) {
					// Property access on object
					$current_context = $current_context->$method;
				} elseif (
					is_array( $current_context ) &&
					isset( $current_context[ $method ] )
				) {
					// Property access on array
					$current_context = $current_context[ $method ];
				}
			}

			// Handle loop prop structure
			if ( $this->is_loop_prop_structure( $current_context ) ) {
				$current_context = $current_context['key'] ?? '';
			}

			// Resolve string values to loop data if they reference a loop
			if ( is_string( $current_context ) ) {
				$loop_key = $current_context;

				// In PHP, we use LoopHandlerManager::is_valid_loop_id instead
				if ( LoopHandlerManager::is_valid_loop_id( $loop_key ) ) {
					$arg_parts = ModifierParser::split_args( $args );
					$loop_args = Utils::parse_keyword_args( $arg_parts, $sources );

					// Get the loop results
					$current_context = LoopHandlerManager::get_loop_preset_data( $loop_key, $loop_args );
				} else {
					// ! TBH 100% i hate this implementation, but ever since Woji added the loop args support the whole
					// ! loop resolving is just an unmaintainable mess (both in PHP and TS)
					$resolved = DynamicContentProcessor::process_expression(
						$current_context,
						array(
							'sources' => $sources,
							'render_context' => 'loop',
						)
					);

					if ( is_array( $resolved ) ) {
						$current_context = $resolved;
					}
				}
			}
		}

		// Final validation
		if ( is_array( $current_context ) ) {
			if ( ! array_is_list( $current_context ) ) {
				$current_context = array( $current_context );
			}
		} else {
			$current_context = array();
		}

		return $current_context;
	}

	/**
	 * Resolve a key from the available sources.
	 * Iterates sources in reverse order for specificity.
	 *
	 * @param string                                        $key     The key to resolve.
	 * @param array<int, array{key: string, source: mixed}> $sources The current sources.
	 * @return mixed|null The resolved value or null if not found.
	 */
	private function resolve_from_sources( string $key, array $sources ) {
		// Iterate sources in reverse (specificity)
		$reversed_sources = array_reverse( $sources );
		foreach ( $reversed_sources as $source_obj ) {
			$source = $source_obj['source'];
			$source_key = $source_obj['key'];

			if ( $key === $source_key ) {
				return $source;
			}

			if ( is_object( $source ) && property_exists( $source, $key ) ) {
				return $source->$key;
			}

			if ( is_array( $source ) && isset( $source[ $key ] ) ) {
				return $source[ $key ];
			}
		}

		return null;
	}

	/**
	 * Resolve loop parameters (process string expressions against sources)
	 * Skips empty string values so loop config defaults ($param ?? default) can work.
	 *
	 * @param array<string, mixed>|null                     $params Loop params.
	 * @param array<int, array{key: string, source: mixed}> $sources Sources for expression resolution.
	 * @return array<string, mixed>
	 */
	private function resolve_loop_params( ?array $params, array $sources ): array {
		if ( empty( $params ) ) {
			return array();
		}

		$resolved = array();
		foreach ( $params as $key => $value ) {
			if ( is_string( $value ) ) {
				$resolved_value = DynamicContentProcessor::process_expression(
					$value,
					array(
						'sources' => $sources,
					)
				);

				if ( null === $resolved_value ) {
					$resolved[ $key ] = $value;
					continue;
				}

				if ( '' === $resolved_value ) {
					continue;
				}

				$resolved[ $key ] = $resolved_value;
			} else {
				$resolved[ $key ] = $value;
			}
		}

		return $resolved;
	}

	/**
	 * Apply modifiers from the target attribute to resolved loop items.
	 * When loopId is used, the target may contain just modifiers (e.g. "slice(1)")
	 * that should be applied to the loop data.
	 *
	 * @param array<int|string, mixed>                      $items   The resolved loop items.
	 * @param string                                        $target  The target string containing modifiers.
	 * @param array<int, array{key: string, source: mixed}> $sources Sources for expression resolution.
	 * @return array<int|string, mixed> The items after applying modifiers.
	 */
	private function apply_target_modifiers( array $items, string $target, array $sources ): array {
		if ( '' === $target ) {
			return $items;
		}

		// Split the target into parts to check for modifiers
		$parts = ExpressionPath::split( $target );

		// Apply each part as a modifier
		$current_data = $items;
		foreach ( $parts as $part ) {
			if ( ModifierParser::is_modifier( $part ) ) {
				$current_data = Modifiers::apply_modifier(
					$current_data,
					$part,
					array(
						'sources' => $sources,
					)
				);
			}
		}

		// Ensure we return an array
		if ( is_array( $current_data ) ) {
			return $current_data;
		}

		return $items;
	}

	/**
	 * Check if a value is a loop prop structure.
	 *
	 * @param mixed $value Value to check.
	 * @return bool
	 */
	private function is_loop_prop_structure( $value ): bool {
		return is_array( $value )
			&& isset( $value['prop-type'] )
			&& 'loop' === $value['prop-type'];
	}
}
