<?php
/**
 * Modifiers class for Blocks dynamic content.
 *
 * @package Etch\Blocks\Global\Utilities
 */

declare(strict_types=1);

namespace Etch\Blocks\Global\Utilities;

use Etch\Blocks\Utilities\EtchTypeAsserter;

/**
 * Modifiers class for handling modifier application in dynamic data.
 */
class Modifiers {
	/**
	 * Parses modifier arguments from a string.
	 *
	 * @param string $arg_string The string to parse.
	 * @return array<string> The parsed arguments.
	 */
	public static function parse_modifier_arguments( string $arg_string ): array {
		return ModifierParser::split_args( $arg_string );
	}

	/**
	 * Check if the string is a modifier pattern
	 *
	 * @param string $part  String to be checked (e.g., 'format("y-m-d")', 'toUpperCase()').
	 * @return bool
	 */
	public static function is_modifier( string $part ): bool {
		return ModifierParser::is_modifier( $part );
	}

	/**
	 * Get a modifier function by name.
	 * Matches TypeScript getModifier() pattern - returns a callable or null.
	 *
	 * @param string               $method The modifier method name.
	 * @param array<string, mixed> $options Options including 'sources' array.
	 * @return callable|null Returns a callable that takes (value, ...args) or null if modifier doesn't exist.
	 */
	public static function get_modifier( string $method, array $options = array() ) {
		$sources = $options['sources'] ?? array();

		switch ( $method ) {
			// Special modifier - applyData
			case 'applyData':
				return function ( $value ) use ( $sources ) {
					return DynamicContentProcessor::apply(
						$value,
						array(
							'sources' => $sources,
						)
					);
				};

			// Date modifiers
			case 'format':  // Alias for dateFormat. Deprecated. TODO: Remove in future.
			case 'dateFormat':
				return function ( $value, ...$args ) {
					if ( ! isset( $args[0] ) || ! is_string( $args[0] ) ) {
						return $value;
					}
					$format = $args[0];
					try {
						if ( is_numeric( $value ) ) {
							$date = new \DateTime( '@' . $value );
						} elseif ( is_string( $value ) ) {
							$date = new \DateTime( $value );
						} else {
							return $value;
						}

						if ( function_exists( 'wp_date' ) ) {
							return wp_date( $format, (int) $date->format( 'U' ) );
						}

						return $date->format( $format );
					} catch ( \Exception $e ) {
						return $value;
					}
				};

			// Number modifiers
			case 'numberFormat':
				return function ( $value, ...$args ) {
					if ( ! is_numeric( $value ) ) {
						return $value;
					}

					$decimals = 0;
					$decimal_point = '.';
					$thousands_separator = ',';

					if ( isset( $args[0] ) && self::is_numeric_type( $args[0] ) ) {
						$decimals = (int) $args[0];
					}
					if ( isset( $args[1] ) && is_string( $args[1] ) ) {
						$decimal_point = $args[1];
					}
					if ( isset( $args[2] ) && is_string( $args[2] ) ) {
						$thousands_separator = $args[2];
					}

					return number_format( (float) $value, $decimals, $decimal_point, $thousands_separator );
				};

			case 'toInt':
				return function ( $value ) {
					if ( is_int( $value ) ) {
						return $value;
					}
					if ( is_numeric( $value ) ) {
						return (int) $value;
					}
					return $value;
				};

			case 'ceil':
				return function ( $value ) {
					if ( ! is_numeric( $value ) ) {
						return $value;
					}
					return (int) ceil( (float) $value );
				};

			case 'round':
				return function ( $value, ...$args ) {
					if ( ! is_numeric( $value ) ) {
						return $value;
					}

					$precision = 0;
					if ( isset( $args[0] ) && is_numeric( $args[0] ) ) {
						$precision = (int) $args[0];
					}

					return round( (float) $value, $precision );
				};

			case 'floor':
				return function ( $value ) {
					if ( ! is_numeric( $value ) ) {
						return $value;
					}
					return (int) floor( (float) $value );
				};

			// String modifiers
			case 'toUppercase':
			case 'toUpperCase':
				return function ( $value ) {
					if ( ! is_string( $value ) ) {
						return $value;
					}
					return mb_strtoupper( $value );
				};

			case 'toLowercase':
			case 'toLowerCase':
				return function ( $value ) {
					if ( ! is_string( $value ) ) {
						return $value;
					}
					return mb_strtolower( $value );
				};

			case 'toString':
				return function ( $value ) {
					return EtchTypeAsserter::to_string( $value );
				};

			case 'indexOf':
				return function ( $value, ...$args ) {
					if ( ! isset( $args[0] ) ) {
						return -1;
					}

					$search_value = $args[0];

					if ( is_string( $value ) ) {
						$pos = strpos( $value, EtchTypeAsserter::to_string( $search_value ) );
						return false === $pos ? -1 : $pos;
					}

					if ( is_array( $value ) ) {
						$index = array_search( $search_value, $value, true );
						return false === $index ? -1 : $index;
					}

					return -1;
				};

			case 'split':
				return function ( $value, ...$args ) {
					if ( ! is_string( $value ) ) {
						return $value;
					}

					$separator = ',';
					if ( isset( $args[0] ) && is_string( $args[0] ) ) {
						$separator = $args[0];
					}

					if ( '' === $separator ) {
						return str_split( $value );
					}

					return explode( $separator, $value );
				};

			case 'join':
				return function ( $value, ...$args ) {
					if ( ! is_array( $value ) ) {
						return $value;
					}

					$separator = ',';
					if ( isset( $args[0] ) && is_string( $args[0] ) ) {
						$separator = $args[0];
					}

					return implode( $separator, $value );
				};

			case 'toBool':
				return function ( $value ) {
					if ( is_bool( $value ) ) {
						return $value;
					}

					if ( is_null( $value ) ) {
						return false;
					}

					if ( is_numeric( $value ) ) {
						return (bool) (int) $value;
					}

					if ( is_string( $value ) ) {
						$normalized = strtolower( trim( $value ) );
						$true_values = array( 'true', '1', 'yes', 'y', 'on' );
						$false_values = array( 'false', '0', 'no', 'n', 'off', '' );
						if ( in_array( $normalized, $true_values, true ) ) {
							return true;
						}
						if ( in_array( $normalized, $false_values, true ) ) {
							return false;
						}
					}
					return (bool) $value;
				};

			// Trimming modifiers
			case 'trim':
				return function ( $value ) {
					if ( ! is_string( $value ) ) {
						return $value;
					}
					return trim( $value );
				};

			case 'ltrim':
				return function ( $value ) {
					if ( ! is_string( $value ) ) {
						return $value;
					}
					return ltrim( $value );
				};

			case 'rtrim':
				return function ( $value ) {
					if ( ! is_string( $value ) ) {
						return $value;
					}
					return rtrim( $value );
				};

			case 'toSlug':
				return function ( $value ) {
					if ( ! is_string( $value ) ) {
						return $value;
					}

					$slug = mb_strtolower( trim( $value ), 'UTF-8' );
					// Transliterate to ASCII (ł→l, ü→u, é→e, etc.)
					$transliterated = transliterator_transliterate( 'Any-Latin; Latin-ASCII', $slug );
					$slug           = false !== $transliterated ? $transliterated : $slug;
					// Replace non-alphanumeric with hyphens
					$slug = preg_replace( '/[^a-z0-9]+/', '-', $slug ) ?? $slug;
					// Remove leading/trailing hyphens
					return trim( $slug, '-' );
				};

			case 'truncateChars':
				return function ( $value, ...$args ) {
					if ( ! is_string( $value ) ) {
						return $value;
					}

					$char_count = 0;
					$ellipses = '...';

					if ( isset( $args[0] ) && is_numeric( $args[0] ) ) {
						$char_count = (int) $args[0];
					}

					if ( isset( $args[1] ) && is_string( $args[1] ) ) {
						$ellipses = $args[1];
					}

					if ( mb_strlen( $value ) <= $char_count ) {
						return $value;
					}

					return mb_substr( $value, 0, $char_count ) . $ellipses;
				};

			case 'truncateWords':
				return function ( $value, ...$args ) {
					if ( ! is_string( $value ) ) {
						return $value;
					}

					$word_count = 0;
					$ellipses = '...';

					if ( isset( $args[0] ) && is_numeric( $args[0] ) ) {
						$word_count = (int) $args[0];
					}

					if ( isset( $args[1] ) && is_string( $args[1] ) ) {
						$ellipses = $args[1];
					}

					$words = explode( ' ', $value );
					if ( count( $words ) <= $word_count ) {
						return $value;
					}

					return implode( ' ', array_slice( $words, 0, $word_count ) ) . $ellipses;
				};

			case 'concat':
				return function ( $value, ...$args ) {
					if ( ! is_string( $value ) ) {
						return $value;
					}

					$concat_str = '';
					foreach ( $args as $arg ) {
						$concat_str .= EtchTypeAsserter::to_string( $arg );
					}

					return $value . $concat_str;
				};

			case 'length':
				return function ( $value ) {
					if ( is_string( $value ) ) {
						return mb_strlen( $value );
					}

					if ( self::is_list_array( $value ) ) {
						return count( $value );
					}
					return $value;
				};

			case 'reverse':
				return function ( $value ) {
					if ( is_string( $value ) ) {
						return strrev( $value );
					}

					if ( is_array( $value ) ) {
						return array_reverse( $value );
					}
					return $value;
				};

			case 'at':
				return function ( $value, ...$args ) {
					if ( ! self::is_list_array( $value ) || ! isset( $args[0] ) ) {
						return $value;
					}

					$index = 0;
					if ( self::is_numeric_type( $args[0] ) ) {
						$index = (int) $args[0];
					}

					$array_length = count( $value );

					if ( $index < 0 ) {
						$index = $array_length + $index;
					}

					if ( isset( $value[ $index ] ) ) {
						return $value[ $index ];
					}

					return null;
				};

			case 'slice':
				return function ( $value, ...$args ) {
					if ( ! self::is_list_array( $value ) || ! isset( $args[0] ) ) {
						return $value;
					}

					$start = 0;
					$array_length = count( $value );
					$end = $array_length;

					if ( self::is_numeric_type( $args[0] ) ) {
						$start = (int) $args[0];
					}

					if ( isset( $args[1] ) && self::is_numeric_type( $args[1] ) ) {
						$end = (int) $args[1];
					}

					if ( $start < 0 ) {
						$start = max( 0, $array_length + $start );
					}

					if ( $end < 0 ) {
						$end = max( 0, $array_length + $end );
					}

					$start = min( $start, $array_length );
					$length = max( 0, $end - $start );

					return array_slice( $value, $start, $length );
				};

			case 'urlEncode':
				return function ( $value ) {
					if ( ! is_string( $value ) ) {
						return $value;
					}
					$revert = array(
						'%21' => '!',
						'%2A' => '*',
						'%27' => "'",
						'%28' => '(',
						'%29' => ')',
					);
					return strtr( rawurlencode( $value ), $revert );
				};

			case 'urlDecode':
				return function ( $value ) {
					if ( ! is_string( $value ) ) {
						return $value;
					}
					return rawurldecode( $value );
				};

			case 'includes':
				return function ( $value, ...$args ) {
					if ( ! isset( $args[0] ) ) {
						return false;
					}

					$search_value = $args[0];
					$true_value  = $args[1] ?? true;
					$false_value = $args[2] ?? false;

					if ( is_string( $value ) ) {
						return str_contains( $value, EtchTypeAsserter::to_string( $search_value ) ) ? $true_value : $false_value;
					}

					if ( is_array( $value ) ) {
						return in_array( $search_value, $value, true ) ? $true_value : $false_value;
					}

					return false;
				};

			case 'startsWith':
				return function ( $value, ...$args ) {
					if ( ! is_string( $value ) || ! isset( $args[0] ) ) {
						return false;
					}

					$search_value = EtchTypeAsserter::to_string( $args[0] );
					$true_value  = $args[1] ?? true;
					$false_value = $args[2] ?? false;

					return str_starts_with( $value, $search_value ) ? $true_value : $false_value;
				};

			case 'endsWith':
				return function ( $value, ...$args ) {
					if ( ! is_string( $value ) || ! isset( $args[0] ) ) {
						return false;
					}

					$search_value = EtchTypeAsserter::to_string( $args[0] );
					$true_value  = $args[1] ?? true;
					$false_value = $args[2] ?? false;

					return str_ends_with( $value, $search_value ) ? $true_value : $false_value;
				};

			case 'less':
				return function ( $value, ...$args ) {
					if ( ! isset( $args[0] ) ) {
						return false;
					}

					$compare_value = $args[0];
					$true_value  = $args[1] ?? true;
					$false_value = $args[2] ?? false;

					if ( self::is_comparable( $value ) &&
					self::is_comparable( $compare_value ) ) {
						return $value < $compare_value ? $true_value : $false_value;
					}

					return $false_value;
				};

			case 'lessOrEqual':
				return function ( $value, ...$args ) {
					if ( ! isset( $args[0] ) ) {
						return false;
					}

					$compare_value = $args[0];
					$true_value  = $args[1] ?? true;
					$false_value = $args[2] ?? false;

					if ( self::is_comparable( $value ) &&
					self::is_comparable( $compare_value ) ) {
						return $value <= $compare_value ? $true_value : $false_value;
					}

					return $false_value;
				};

			case 'greater':
				return function ( $value, ...$args ) {
					if ( ! isset( $args[0] ) ) {
						return false;
					}

					$compare_value = $args[0];
					$true_value  = $args[1] ?? true;
					$false_value = $args[2] ?? false;

					if ( self::is_comparable( $value ) &&
					self::is_comparable( $compare_value ) ) {
						return $value > $compare_value ? $true_value : $false_value;
					}

					return $false_value;
				};

			case 'greaterOrEqual':
				return function ( $value, ...$args ) {
					if ( ! isset( $args[0] ) ) {
						return false;
					}

					$compare_value = $args[0];
					$true_value  = $args[1] ?? true;
					$false_value = $args[2] ?? false;

					if ( self::is_comparable( $value ) &&
					self::is_comparable( $compare_value ) ) {
						return $value >= $compare_value ? $true_value : $false_value;
					}

					return $false_value;
				};

			case 'equal':
				return function ( $value, ...$args ) {
					if ( ! isset( $args[0] ) ) {
						return false;
					}

					$compare_value = $args[0];
					$true_value  = $args[1] ?? true;
					$false_value = $args[2] ?? false;

					return $value === $compare_value ? $true_value : $false_value;
				};

			case 'intersects':
				return function ( $value, ...$args ) {
					if ( ! is_array( $value ) || ! isset( $args[0] ) ) {
						return false;
					}

					$compare_value = $args[0];

					if ( ! is_array( $compare_value ) ) {
						return false;
					}

					$true_value  = $args[1] ?? true;
					$false_value = $args[2] ?? false;

					return array_intersect( $value, $compare_value ) ? $true_value : $false_value;
				};

			case 'stripTags':
				return function ( $value ) {
					if ( ! is_string( $value ) ) {
						return $value;
					}
					return strip_tags( $value );
				};

			case 'pluck':
				return function ( $value, ...$args ) {
					if ( ! self::is_list_array( $value ) || ! isset( $args[0] ) ) {
						return array();
					}

					$property = EtchTypeAsserter::to_string( $args[0] );
					$keys = explode( '.', $property );

					return array_map(
						function ( $item ) use ( $keys ) {
							$current = $item;

							foreach ( $keys as $key ) {
								if ( ! is_object( $current ) && ! is_array( $current ) ) {
									return null;
								}

								if ( is_object( $current ) ) {
									$current = $current->$key ?? null;
								} else {
									$current = $current[ $key ] ?? null;
								}
							}

							return $current;
						},
						$value
					);
				};

			case 'unserializePhp':
			case 'unserializePHP':
				return function ( $value ) {
					if ( ! is_string( $value ) ) {
						return $value;
					}
					$output = maybe_unserialize( $value );
					// prevent unserializing to objects for security
					if ( is_object( $output ) ) {
						return null;
					}
					return $output;
				};

			case 'values':
				return function ( $value ) {
					if ( ! is_array( $value ) ) {
						return $value;
					}
					return array_values( $value );
				};
			case 'keys':
				return function ( $value ) {
					// We need to mimic the JavaScript modifier behavior that when its a non associative array, it returns the original value
					if ( ! is_array( $value ) || array_is_list( $value ) ) {
						return $value;
					}
					return array_keys( $value );
				};

			case 'replace':
				return function ( $value, ...$args ) {
					// Mimics native JS String.replace() behavior
					// https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/String/replace
					$search_value  = $args[0] ?? null;
					$replace_value = $args[1] ?? null;

					if ( ! self::are_all_strings( $value, $search_value, $replace_value ) ) {
						return $value;
					}

					// Only replace the first occurrence to match JS String.replace() behavior
					$pos = strpos( $value, $search_value );
					if ( false === $pos ) {
						return $value;
					}

					return substr_replace( $value, $replace_value, $pos, strlen( $search_value ) );
				};

			case 'replaceAll':
				return function ( $value, ...$args ) {
					// Mimics native JS String.replaceAll() behavior
					// https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/String/replaceAll
					$search_value  = $args[0] ?? null;
					$replace_value = $args[1] ?? null;

					if ( ! self::are_all_strings( $value, $search_value, $replace_value ) ) {
						return $value;
					}

					return str_replace( $search_value, $replace_value, $value );
				};

			default:
				// Unknown modifier
				return null;
		}
	}

	/**
	 * Checks if a value is a numeric type (int or float), excluding numeric strings.
	 *
	 * @param mixed $value The value to check.
	 * @return bool
	 */
	private static function is_numeric_type( $value ): bool {
		return is_numeric( $value ) && ! is_string( $value );
	}

	/**
	 * Checks if a value is comparable (numeric or string).
	 *
	 * @param mixed $value The value to check.
	 * @return bool
	 */
	private static function is_comparable( $value ): bool {
		return is_numeric( $value ) || is_string( $value );
	}

	/**
	 * Checks if a value is a list array (numeric keys only).
	 *
	 * @param mixed $value The value to check.
	 * @return bool
	 */
	private static function is_list_array( $value ): bool {
		return is_array( $value ) && array_is_list( $value );
	}

	/**
	 * Checks if all provided values are strings.
	 *
	 * @param mixed ...$values The values to check.
	 * @return bool
	 */
	private static function are_all_strings( ...$values ): bool {
		foreach ( $values as $val ) {
			if ( ! is_string( $val ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Check if a modifier exists.
	 * Matches TypeScript getModifier() pattern - returns true if modifier exists.
	 *
	 * @param string $method The modifier method name (e.g., 'slice', 'toUpperCase').
	 * @return bool
	 */
	public static function has_modifier( string $method ): bool {
		return null !== self::get_modifier( $method );
	}

	/**
	 * Apply a modifier to a value.
	 *
	 * Options:
	 * - sources: array<int, array{key: string, source: mixed}>
	 *
	 * @param mixed                $value Value to modify.
	 * @param string               $modifier Modifier string like `applyData()`.
	 * @param array<string, mixed> $options Modifier options.
	 * @return mixed
	 */
	public static function apply_modifier( $value, string $modifier, array $options = array() ) {
		$modifier = trim( $modifier );
		if ( '' === $modifier || ! self::is_modifier( $modifier ) ) {
			return $value;
		}

		$sources = $options['sources'] ?? array();

		$parsed = ModifierParser::parse( $modifier );
		$method = $parsed['method'];
		$args_string = $parsed['args'];

		// Get the modifier function
		$modifier_func = self::get_modifier( $method, array( 'sources' => $sources ) );

		if ( null === $modifier_func ) {
			// Unknown modifier - return null to indicate it doesn't exist
			return null;
		}

		// Parse and resolve arguments
		$parsed_args = '' !== $args_string ? self::parse_modifier_arguments( $args_string ) : array();
		$resolved_args = array_map(
			static function ( $arg ) use ( $sources ) {
				return DynamicContentProcessor::process_expression(
					$arg,
					array(
						'sources' => $sources,
					)
				);
			},
			$parsed_args
		);

		// Call the modifier function with value and resolved args
		return $modifier_func( $value, ...$resolved_args );
	}
}
