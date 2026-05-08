<?php
/**
 * Registry for block converters.
 *
 * @package Etch
 */

namespace Etch\Blocks\Conversion;

/**
 * Dispatches gutenberg-etch conversion to registered per-block converters.
 *
 * Handles children/innerBlocks recursion so individual converters don't have to.
 *
 * @phpstan-import-type GutenbergBlock from BlockConverter
 * @phpstan-import-type EtchBlock from BlockConverter
 */
class BlockConverterRegistry {

	/**
	 * Converters keyed by Gutenberg blockName.
	 *
	 * @var array<string, BlockConverter>
	 */
	private array $gutenberg_map = array();

	/**
	 * Converters keyed by Etch block type.
	 *
	 * @var array<string, BlockConverter>
	 */
	private array $etch_map = array();

	/**
	 * Register a converter for a block type.
	 *
	 * @param string         $type      The Etch block type (e.g. 'etch/element').
	 * @param BlockConverter $converter The converter instance.
	 *
	 * @return self
	 */
	public function register( string $type, BlockConverter $converter ): self {
		$gutenberg_name                        = $converter->gutenberg_block_name() ?? $type;
		$this->gutenberg_map[ $gutenberg_name ] = $converter;
		$this->etch_map[ $type ]                = $converter;
		return $this;
	}

	/**
	 * Convert a Gutenberg block tree to Etch JSON.
	 *
	 * @phpstan-param GutenbergBlock $gutenberg_block
	 *
	 * @param array<string, mixed> $gutenberg_block The Gutenberg block array.
	 *
	 * @return array<string, mixed>
	 *
	 * @throws \InvalidArgumentException When the block type has no registered converter.
	 */
	public function gutenberg_to_etch( array $gutenberg_block ): array {
		$block_name = $gutenberg_block['blockName'];

		if ( ! isset( $this->gutenberg_map[ $block_name ] ) ) {
			throw new \InvalidArgumentException( 'Unknown block type: ' . esc_html( $block_name ) );
		}

		$result = $this->gutenberg_map[ $block_name ]->to_etch( $gutenberg_block );

		$result['children'] = array_map(
			fn( array $child ) => $this->gutenberg_to_etch( $child ), // @phpstan-ignore argument.type (recursive — inner blocks have the same shape)
			$gutenberg_block['innerBlocks']
		);

		return $result;
	}

	/**
	 * Convert an Etch JSON block tree to Gutenberg format.
	 *
	 * @phpstan-param EtchBlock $etch_json
	 *
	 * @param array<string, mixed> $etch_json The Etch JSON array.
	 *
	 * @return array<string, mixed>
	 *
	 * @throws \InvalidArgumentException When the block type has no registered converter.
	 */
	public function etch_to_gutenberg( array $etch_json ): array {
		$type = $etch_json['type'];

		if ( ! isset( $this->etch_map[ $type ] ) ) {
			throw new \InvalidArgumentException( 'Unknown block type: ' . esc_html( $type ) );
		}

		$result = $this->etch_map[ $type ]->to_gutenberg( $etch_json );

		$result['innerBlocks'] = array_map(
			fn( array $child ) => $this->etch_to_gutenberg( $child ), // @phpstan-ignore argument.type (recursive — children have the same shape)
			$etch_json['children']
		);

		return $result;
	}
}
