<?php
/**
 * Cached Pattern
 *
 * Handles caching of parsed pattern blocks to avoid redundant parsing
 * when the same component pattern is used multiple times.
 *
 * @package Etch
 */

namespace Etch\Blocks\Global;

/**
 * CachedPattern class
 *
 * @phpstan-type PatternData array{post: \WP_Post, parsed_blocks: array<mixed>, properties: array<mixed>, key: string}
 */
class CachedPattern {

	/**
	 * Cache of parsed patterns
	 *
	 * @var array<int, PatternData|null>
	 */
	private static $cache = array();

	/**
	 * Load pattern data for a given reference ID
	 *
	 * Checks the static cache first. If not found, fetches the post,
	 * validates it's a wp_block, parses blocks, loads properties, and caches everything.
	 *
	 * @param int $ref The post ID of the pattern (wp_block).
	 * @return PatternData|null Pattern data, or null if invalid.
	 */
	private static function load_pattern( $ref ) {
		if ( empty( $ref ) ) {
			return null;
		}
		// Check cache first
		if ( array_key_exists( $ref, self::$cache ) ) {
			return self::$cache[ $ref ];
		}

		$pattern_post = get_post( $ref );

		if ( ! $pattern_post || 'wp_block' !== $pattern_post->post_type ) {
			// Cache null to avoid re-fetching invalid refs
			self::$cache[ $ref ] = null;
			return null;
		}

		$property_definitions = get_post_meta( $pattern_post->ID, 'etch_component_properties', true );
		$raw_key              = get_post_meta( $pattern_post->ID, 'etch_component_html_key', true );

		$pattern_data = array(
			'post'          => $pattern_post,
			'parsed_blocks' => parse_blocks( $pattern_post->post_content ),
			'properties'    => is_array( $property_definitions ) ? $property_definitions : array(),
			'key'           => is_string( $raw_key ) ? $raw_key : '',
		);

		self::$cache[ $ref ] = $pattern_data;

		return $pattern_data;
	}

	/**
	 * Get parsed pattern blocks for a given reference ID
	 *
	 * @param int $ref The post ID of the pattern (wp_block).
	 * @return array<mixed> The parsed blocks array, or empty array if not found/invalid.
	 */
	public static function get_pattern_parsed_blocks( $ref ) {
		return self::load_pattern( $ref )['parsed_blocks'] ?? array();
	}

	/**
	 * Get full pattern data for a given reference ID
	 *
	 * @param int $ref The post ID of the pattern (wp_block).
	 * @return PatternData|null Pattern data, or null if invalid.
	 */
	public static function get_pattern( $ref ) {
		return self::load_pattern( $ref );
	}

	/**
	 * Get pattern properties for a given reference ID
	 *
	 * @param int $ref The post ID of the pattern (wp_block).
	 * @return array<mixed> The property definitions array, or empty array if not found/invalid.
	 */
	public static function get_pattern_properties( $ref ) {
		return self::load_pattern( $ref )['properties'] ?? array();
	}

	/**
	 * Get the component html key for a given reference ID
	 *
	 * @param int $ref The post ID of the pattern (wp_block).
	 * @return string The component html key, or empty string if not found/invalid.
	 */
	public static function get_pattern_key( $ref ) {
		return self::load_pattern( $ref )['key'] ?? '';
	}

	/**
	 * Get pattern post object for a given reference ID
	 *
	 * @param int $ref The post ID of the pattern (wp_block).
	 * @return \WP_Post|null The post object, or null if not found/invalid.
	 */
	public static function get_pattern_post( $ref ) {
		return self::load_pattern( $ref )['post'] ?? null;
	}

	/**
	 * Clear the pattern cache
	 *
	 * @return void
	 */
	public static function clear_cache() {
		self::$cache = array();
	}
}
