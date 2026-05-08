<?php
/**
 * Loop Property
 *
 * Represents a loop component property definition (primitive: string, specialized: array).
 *
 * @package Etch\Blocks\Types\ComponentProperty
 */

namespace Etch\Blocks\Types\ComponentProperty;

use Etch\Blocks\Utilities\EtchTypeAsserter;
use Etch\Blocks\Global\Utilities\DynamicContentProcessor;
use Etch\Blocks\Global\Utilities\LoopHandlerManager;

/**
 * LoopProperty class
 */
class LoopProperty extends ComponentProperty {
	/**
	 * Default value
	 *
	 * @var string|null
	 */
	public $default = null;

	/**
	 * Create from property data array.
	 *
	 * @param array<string, mixed> $data Property data array.
	 * @return self
	 */
	public static function from_property_array( array $data ): self {
		$instance = new self();
		self::extract_base( $data, $instance );
		return $instance;
	}

	/**
	 * Resolve the value for this loop property.
	 *
	 * @param mixed                                         $value   The value to resolve.
	 * @param array<int, array{key: string, source: mixed}> $sources Sources for dynamic expression evaluation.
	 * @return array{prop-type: string, key: string, data: array<mixed>}
	 */
	public function resolve_value( $value, array $sources ): mixed {
		$key = $this->resolve_loop_key( $value );
		$data = $this->resolve_loop_data( $value, $sources );

		return array(
			'prop-type' => 'loop',
			'key'       => $key,
			'data'      => $data,
		);
	}

	/**
	 * Resolve the loop key from the given value.
	 *
	 * Extracts expression from {braces} if present, otherwise treats value as string.
	 *
	 * @param mixed $value The value to resolve.
	 * @return string The resolved loop key.
	 */
	private function resolve_loop_key( $value ): string {
		return $this->extract_expression( $value ) ?? '';
	}

	/**
	 * Eagerly resolve loop data from a loop key.
	 *
	 * Checks loop presets by database ID first, then by user-friendly key.
	 * Falls back to parsing the key as a JSON/comma-separated array.
	 *
	 * @param mixed                                         $value The input value.
	 * @param array<int, array{key: string, source: mixed}> $sources Sources for dynamic expression evaluation.
	 * @return array<mixed> The resolved array data.
	 */
	private function resolve_loop_data( mixed $value, array $sources ): array {
		// If already an array, return as is.
		if ( is_array( $value ) ) {
			return $value;
		}

		$key = $this->resolve_loop_key( $value );

		$loop_presets = LoopHandlerManager::get_loop_presets();
		if ( isset( $loop_presets[ $key ] ) ) {
			return LoopHandlerManager::get_loop_preset_data( $key, array() );
		}

		$found_loop_id = LoopHandlerManager::find_loop_by_key( $key );
		if ( $found_loop_id ) {
			return LoopHandlerManager::get_loop_preset_data( $found_loop_id, array() );
		}

		if ( ! empty( $sources ) ) {
			$parsed = DynamicContentProcessor::process_expression(
				$key,
				array(
					'sources' => $sources,
				)
			);
			if ( is_array( $parsed ) ) {
				return $parsed;
			}
		}

		return EtchTypeAsserter::to_array( $key );
	}

	/**
	 * Extract expression string from a value, unwrapping {braces} if present.
	 *
	 * @param mixed $value The value to extract from.
	 * @return string|null The expression string, or null if empty.
	 */
	private function extract_expression( $value ): ?string {
		$string_value = EtchTypeAsserter::to_string( $value );

		if ( '' === $string_value ) {
			return null;
		}

		$trimmed = trim( $string_value );
		if ( str_starts_with( $trimmed, '{' ) && str_ends_with( $trimmed, '}' ) ) {
			return substr( $trimmed, 1, -1 );
		}

		return $string_value;
	}
}
