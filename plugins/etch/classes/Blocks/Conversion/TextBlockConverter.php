<?php
/**
 * Converter for etch/text blocks.
 *
 * @package Etch
 */

namespace Etch\Blocks\Conversion;

/**
 * Converts etch/text blocks between Gutenberg and Etch JSON formats.
 */
class TextBlockConverter extends BlockConverter {

	/**
	 * Convert a Gutenberg etch/text block to Etch JSON.
	 *
	 * @param array<string, mixed> $gutenberg_block The Gutenberg block array.
	 *
	 * @return array<string, mixed>
	 */
	public function to_etch( array $gutenberg_block ): array {
		$attrs = is_array( $gutenberg_block['attrs'] ) ? $gutenberg_block['attrs'] : array();

		return array_merge(
			array(
				'type'    => 'etch/text',
				'version' => 1,
			),
			$this->base_to_etch( $attrs ),
			array(
				'children' => array(),
				'text'     => $attrs['content'] ?? '',
			)
		);
	}

	/**
	 * Convert Etch JSON etch/text block to Gutenberg format.
	 *
	 * @param array<string, mixed> $etch_json The Etch JSON array.
	 *
	 * @return array<string, mixed>
	 */
	public function to_gutenberg( array $etch_json ): array {
		$attrs            = $this->base_to_gutenberg( $etch_json );
		$attrs['content'] = $etch_json['text'] ?? '';

		return array(
			'blockName'    => 'etch/text',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);
	}
}
