<?php
/**
 * Converter for etch/svg blocks.
 *
 * @package Etch
 */

namespace Etch\Blocks\Conversion;

/**
 * Converts etch/svg blocks between Gutenberg and Etch JSON formats.
 */
class SvgBlockConverter extends BlockConverter {

	/**
	 * Convert a Gutenberg etch/svg block to Etch JSON.
	 *
	 * @param array<string, mixed> $gutenberg_block The Gutenberg block array.
	 *
	 * @return array<string, mixed>
	 */
	public function to_etch( array $gutenberg_block ): array {
		$attrs = is_array( $gutenberg_block['attrs'] ) ? $gutenberg_block['attrs'] : array();

		return array_merge(
			array(
				'type'    => 'etch/svg',
				'version' => 1,
			),
			$this->base_to_etch( $attrs ),
			array(
				'children'   => array(),
				'attributes' => is_array( $attrs['attributes'] ?? null ) ? $attrs['attributes'] : array(),
				'styles'     => is_array( $attrs['styles'] ?? null ) ? array_values( $attrs['styles'] ) : array(),
			)
		);
	}

	/**
	 * Convert Etch JSON etch/svg block to Gutenberg format.
	 *
	 * @param array<string, mixed> $etch_json The Etch JSON array.
	 *
	 * @return array<string, mixed>
	 */
	public function to_gutenberg( array $etch_json ): array {
		$attrs               = $this->base_to_gutenberg( $etch_json );
		$attrs['tag']        = 'svg';
		$attrs['attributes'] = is_array( $etch_json['attributes'] ?? null ) ? $etch_json['attributes'] : array();

		$styles = is_array( $etch_json['styles'] ?? null ) ? $etch_json['styles'] : array();
		if ( ! empty( $styles ) ) {
			$attrs['styles'] = $styles;
		}

		return array(
			'blockName'    => 'etch/svg',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => "\n\n",
			'innerContent' => array( "\n", "\n" ),
		);
	}
}
