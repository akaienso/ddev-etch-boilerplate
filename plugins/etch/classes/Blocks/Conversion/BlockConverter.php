<?php
/**
 * Abstract base class for block converters.
 *
 * @package Etch
 */

namespace Etch\Blocks\Conversion;

/**
 * Defines the per-block contract for gutenberg-etch conversion.
 *
 * Subclasses handle only their own block properties — the registry
 * handles children/innerBlocks recursion automatically. Shared fields
 * (context, script, options) are handled by base helpers.
 *
 * @phpstan-type GutenbergBlock array{blockName: string, attrs: array<string, mixed>, innerBlocks: list<array<string, mixed>>, innerHTML?: string, innerContent?: list<string>}
 * @phpstan-type EtchBlock array{type: string, children: list<array<string, mixed>>, version?: int, context?: array<string, mixed>}
 */
abstract class BlockConverter {

	/**
	 * Convert a Gutenberg block to Etch JSON (excluding children).
	 *
	 * @phpstan-param GutenbergBlock $gutenberg_block
	 *
	 * @param array<string, mixed> $gutenberg_block The Gutenberg block array.
	 *
	 * @return array<string, mixed>
	 */
	abstract public function to_etch( array $gutenberg_block ): array;

	/**
	 * Convert Etch JSON to a Gutenberg block (excluding innerBlocks).
	 *
	 * @phpstan-param EtchBlock $etch_json
	 *
	 * @param array<string, mixed> $etch_json The Etch JSON array.
	 *
	 * @return array<string, mixed>
	 */
	abstract public function to_gutenberg( array $etch_json ): array;

	/**
	 * Returns the Gutenberg blockName if it differs from the etch type.
	 *
	 * @return string|null Null means the etch type is used as the blockName.
	 */
	public function gutenberg_block_name(): ?string {
		return null;
	}

	/**
	 * Extract shared base fields from Gutenberg attrs into Etch format.
	 *
	 * Returns context, and optionally script and options.
	 *
	 * @param array<string, mixed> $attrs The Gutenberg block attrs.
	 *
	 * @return array<string, mixed>
	 */
	protected function base_to_etch( array $attrs ): array {
		$context = array();

		$metadata = is_array( $attrs['metadata'] ?? null ) ? $attrs['metadata'] : array();
		$name     = $metadata['name'] ?? null;
		if ( null !== $name ) {
			$context['name'] = $name;
		}

		if ( ! empty( $attrs['hidden'] ) ) {
			$context['hidden'] = true;
		}

		$result = array( 'context' => $context );

		if ( isset( $attrs['script'] ) && is_array( $attrs['script'] ) ) {
			$result['script'] = $attrs['script'];
		}

		if ( ! empty( $attrs['options'] ) && is_array( $attrs['options'] ) ) {
			$result['options'] = $attrs['options'];
		}

		return $result;
	}

	/**
	 * Build shared base Gutenberg attrs from Etch JSON fields.
	 *
	 * Returns the attrs array with metadata, and optionally hidden, script, options.
	 *
	 * @param array<string, mixed> $etch_json The Etch JSON array.
	 *
	 * @return array<string, mixed>
	 */
	protected function base_to_gutenberg( array $etch_json ): array {
		$context = is_array( $etch_json['context'] ?? null ) ? $etch_json['context'] : array();
		$name    = $context['name'] ?? null;
		$attrs   = array();

		if ( null !== $name ) {
			$attrs['metadata'] = array( 'name' => $name );
		}

		if ( ! empty( $context['hidden'] ) ) {
			$attrs['hidden'] = true;
		}

		if ( isset( $etch_json['script'] ) && is_array( $etch_json['script'] ) ) {
			$attrs['script'] = $etch_json['script'];
		}

		if ( ! empty( $etch_json['options'] ) && is_array( $etch_json['options'] ) ) {
			$attrs['options'] = $etch_json['options'];
		}

		return $attrs;
	}
}
