<?php
/**
 * CssProcessor class for Etch plugin
 *
 * This file contains the CssProcessor preprocessing css for output.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Blocks\Utilities;

use Etch\Helpers\Flag;
use Etch\Blocks\Utilities\EtchTypeAsserter;

/**
 * CssProcessor class for handling CSS preprocessing.
 *
 * Supports various CSS preprocessing tasks such as variable replacement,
 * nesting, and other transformations.
 * All methods are static for easy access across the blocks system.
 */
class CssProcessor {

	/**
	 * Preprocess CSS by applying various transformations.
	 *
	 * @param string                $css CSS string to preprocess.
	 * @param string                $selector Base selector for nested rules.
	 * @param array<string, string> $custom_media_definitions Optional array of custom media definitions for replacement.
	 * @return string Preprocessed CSS.
	 */
	public static function preprocess_css( string $css, string $selector, array $custom_media_definitions = array() ): string {
		$css = self::parse_scss_like_syntax( $css, $selector );
		$css = self::replace_custom_media_definitions( $css, $custom_media_definitions );
		$css = self::convert_rem_functions( $css );
		$css = self::sanitize_css( $css );
		return $css;
	}


	/**
	 * Normalize CSS string.
	 *
	 * @param string $css CSS string to sanitize.
	 * @return string Sanitized CSS.
	 */
	private static function sanitize_css( string $css ): string {
		// Strip '</' sequences to prevent breaking out of <style> tags.
		// '</' has no valid use in CSS but is required to form any HTML closing tag (e.g. </style>).
		// This preserves '>' (child combinator) and '<' (media query ranges) which are valid CSS.
		return str_replace( '</', '', $css );
	}

	/**
	 * Parse SCSS-like syntax for BEM elements and modifiers.
	 * Replaces &__ and &-- with the selector followed by __ or --.
	 *
	 * @param string $css CSS to parse.
	 * @param string $selector Base selector.
	 * @return string Parsed CSS.
	 */
	private static function parse_scss_like_syntax( string $css, string $selector ): string {
		// Handle BEM element syntax: &__element -> .selector__element

		$types = array(
			'__',
			'--',
			'_',
			'-',
		);

		$parsed_css = $css;

		foreach ( $types as $type ) {
			$result = preg_replace( '/&' . $type . '([a-zA-Z0-9_-]+)/', $selector . $type . '$1', $parsed_css );
			$parsed_css = EtchTypeAsserter::to_string( $result, $parsed_css );
		}

		return $parsed_css;
	}

	/**
	 * Converts e.g. to-rem(500px) to its corresponding rem value at 100% root font size
	 *
	 * @param string $css CSS to parse.
	 * @return string Parsed CSS.
	 */
	private static function convert_rem_functions( string $css ): string {
		// We are assuming that the user Root Font Size is 100% which make the font-size as 16px
		$base_font_size = 16;
		return preg_replace_callback(
			'/to-rem\(\s*([\d.]+)\s*px\s*\)/',
			function ( $matches ) use ( $base_font_size ) {
				$px_value  = (float) $matches[1];
				$rem_value = $px_value / $base_font_size;

				// Avoid unnecessary trailing zeros (e.g., "1.0rem" → "1rem")
				return rtrim( rtrim( number_format( $rem_value, 4, '.', '' ), '0' ), '.' ) . 'rem';
			},
			$css
		) ?? $css;
	}

	/**
	 * Extracts @custom-media rules from CSS and returns them as an array.
	 *
	 * @param string $css CSS string to extract from.
	 * @return array<string, string> Array of custom media definitions with their names as keys and their definitions as values.
	 */
	public static function extract_custom_media_definitions( string $css ): array {
		$custom_media_definitions = array();
		preg_match_all( '/@custom-media\s+--([a-zA-Z0-9_-]+)\s+([^;]+);/', $css, $matches, PREG_SET_ORDER );
		foreach ( $matches as $match ) {
			$name = '--' . $match[1];
			$query = trim( $match[2] );
			$custom_media_definitions[ $name ] = $query;
		}
		return $custom_media_definitions;
	}

	/**
	 * Replaces custom media definitions in CSS with their corresponding media queries.
	 *
	 * @param string                $css CSS string to process.
	 * @param array<string, string> $custom_media_definitions Array of custom media definitions with their names as keys and their definitions as values.
	 * @return string CSS with custom media definitions replaced by their corresponding media queries.
	 */
	private static function replace_custom_media_definitions( string $css, array $custom_media_definitions ): string {
		foreach ( $custom_media_definitions as $name => $definition ) {
			$css = str_replace( '(' . $name . ')', $definition, $css );
		}
		return $css;
	}
}
