<?php
/**
 * Converter for etch/post-content blocks.
 *
 * @package Etch
 */

namespace Etch\Blocks\Conversion;

/**
 * Converts etch/post-content blocks between Gutenberg (core/post-content) and Etch JSON formats.
 */
class PostContentBlockConverter extends BlockConverter {

	/**
	 * Returns the Gutenberg blockName for this converter.
	 *
	 * @return string
	 */
	public function gutenberg_block_name(): string {
		return 'core/post-content';
	}

	/**
	 * Convert a Gutenberg core/post-content block to Etch JSON.
	 *
	 * @param array<string, mixed> $gutenberg_block The Gutenberg block array.
	 *
	 * @return array<string, mixed>
	 */
	public function to_etch( array $gutenberg_block ): array {
		$attrs = is_array( $gutenberg_block['attrs'] ) ? $gutenberg_block['attrs'] : array();

		return array_merge(
			array(
				'type'    => 'etch/post-content',
				'version' => 1,
			),
			$this->base_to_etch( $attrs ),
			array( 'children' => array() )
		);
	}

	/**
	 * Convert Etch JSON etch/post-content block to Gutenberg format.
	 *
	 * @param array<string, mixed> $etch_json The Etch JSON array.
	 *
	 * @return array<string, mixed>
	 */
	public function to_gutenberg( array $etch_json ): array {
		$attrs           = $this->base_to_gutenberg( $etch_json );
		$attrs['align']  = 'full';
		$attrs['layout'] = array( 'type' => 'default' );

		return array(
			'blockName'    => 'core/post-content',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);
	}
}
