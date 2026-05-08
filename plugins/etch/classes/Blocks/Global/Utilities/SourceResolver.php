<?php
/**
 * SourceResolver
 *
 * Sources-based resolver for dynamic expressions.
 *
 * This mirrors the Builder/TS behavior where a list of `{key, source}` entries
 * is searched in reverse order (last pushed wins).
 *
 * NOTE: This is introduced as a building block and is not yet wired into
 * production rendering paths.
 *
 * @package Etch\Blocks\Global\Utilities
 */

declare(strict_types=1);

namespace Etch\Blocks\Global\Utilities;

use Etch\Blocks\Global\StylesRegister;

/**
 * SourceResolver class.
 */
class SourceResolver {

	/**
	 * Resolve an expression against a list of sources.
	 *
	 * @param string            $expression Expression string (e.g. `item.title.applyData()`).
	 * @param array<int, mixed> $sources Sources list.
	 * @param string|null       $render_context Optional render context.
	 * @return mixed|null
	 */
	public static function resolve_from_sources( string $expression, array $sources, ?string $render_context = null ) {
		if ( '' === $expression ) {
			return $expression;
		}

		if ( empty( $sources ) ) {
			return null;
		}

		// Reverse iteration: last pushed source wins.
		foreach ( array_reverse( $sources ) as $source_entry ) {
			if ( ! is_array( $source_entry ) ) {
				continue;
			}

			$key = 'item';
			if ( isset( $source_entry['key'] ) && is_string( $source_entry['key'] ) && '' !== $source_entry['key'] ) {
				$key = $source_entry['key'];
			}

			if ( ! array_key_exists( 'source', $source_entry ) ) {
				continue;
			}

			$source = $source_entry['source'];

			if ( $expression !== $key && ! str_starts_with( $expression, $key . '.' ) && ! str_starts_with( $expression, $key . '[' ) ) {
				continue;
			}

			$parts = ExpressionPath::split( $expression );
			if ( empty( $parts ) ) {
				continue;
			}

			$result = $source;
			foreach ( array_slice( $parts, 1 ) as $part ) {
				$result = self::handle_special_props( $result, $render_context );

				if ( is_array( $result ) && array_key_exists( $part, $result ) ) {
					$result = $result[ $part ];
					continue;
				}

				if ( is_object( $result ) && property_exists( $result, $part ) ) {
					$result = $result->$part;
					continue;
				}

				if ( ModifierParser::is_modifier( $part ) ) {
					$result = Modifiers::apply_modifier(
						$result,
						$part,
						array(
							'sources' => $sources,
						)
					);
					continue;
				}

				$result = null;
				break;
			}

			if ( null === $result ) {
				continue;
			}

			return self::handle_special_props( $result, $render_context, true );
		}

		return null;
	}

	/**
	 * Handle special properties for loop and style structures.
	 *
	 * @param mixed  $value The result to check.
	 * @param string $render_context Optional render context.
	 * @param bool   $is_final_value Whether this is the final resolved value (affects loop prop handling).
	 * @return mixed|null
	 */
	private static function handle_special_props( $value, ?string $render_context = null, bool $is_final_value = false ) {
		if ( ! is_array( $value ) || ! isset( $value['prop-type'] ) ) {
			return $value;
		}

		if ( self::is_loop_prop_structure( $value ) ) {
			// If the final resolution is loop context, return the key.
			return ( 'loop' === $render_context && $is_final_value ) ? ( $value['key'] ?? null ) : ( $value['data'] ?? null );
		}

		return $value;
	}

	/**
	 * Check if a value is a loop prop structure.
	 *
	 * @param mixed $value Value to check.
	 * @return bool
	 * @phpstan-assert-if-true array{prop-type: 'loop', key?: string, data?: mixed} $value
	 */
	private static function is_loop_prop_structure( $value ): bool {
		return is_array( $value )
		&& isset( $value['prop-type'] )
		&& 'loop' === $value['prop-type'];
	}
}
