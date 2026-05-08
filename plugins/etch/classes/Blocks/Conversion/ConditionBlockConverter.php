<?php
/**
 * Converter for etch/condition blocks.
 *
 * @package Etch
 */

namespace Etch\Blocks\Conversion;

use Etch\Utilities\ConditionResolver;

/**
 * Converts etch/condition blocks between Gutenberg and Etch JSON formats.
 */
class ConditionBlockConverter extends BlockConverter {

	/**
	 * Convert a Gutenberg etch/condition block to Etch JSON.
	 *
	 * @param array<string, mixed> $gutenberg_block The Gutenberg block array.
	 *
	 * @return array<string, mixed>
	 */
	public function to_etch( array $gutenberg_block ): array {
		$attrs = is_array( $gutenberg_block['attrs'] ) ? $gutenberg_block['attrs'] : array();

		return array_merge(
			array(
				'type'    => 'etch/condition',
				'version' => 1,
			),
			$this->base_to_etch( $attrs ),
			array(
				'children'   => array(),
				'conditionString' => $attrs['conditionString'] ?? '',
			)
		);
	}

	/**
	 * Convert Etch JSON etch/condition block to Gutenberg format.
	 *
	 * @param array<string, mixed> $etch_json The Etch JSON array.
	 *
	 * @return array<string, mixed>
	 */
	public function to_gutenberg( array $etch_json ): array {
		$attrs = $this->base_to_gutenberg( $etch_json );
		$attrs['condition'] = is_array( $etch_json['attributes'] ?? null ) ? $etch_json['attributes'] : array();
		$attrs['conditionString'] = $etch_json['conditionString'] ?? '';

		$condition_string = isset( $etch_json['conditionString'] ) && is_string( $etch_json['conditionString'] ) ? $etch_json['conditionString'] : '';
		if ( '' !== $condition_string ) {
			$attrs['condition']        = ConditionResolver::parse_logical_condition_string( $condition_string );
			$attrs['conditionString']  = $condition_string;
		}

		return array(
			'blockName'    => 'etch/condition',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => "\n\n",
			'innerContent' => array( "\n", "\n" ),
		);
	}
}
