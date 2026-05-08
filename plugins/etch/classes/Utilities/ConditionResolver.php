<?php
/**
 * Resolver for parsing and evaluating condition strings.
 *
 * @package Etch
 */

namespace Etch\Utilities;

/**
 * Parses and evaluates logical condition strings into structured condition objects.
 */
class ConditionResolver {

	const COMPARISON_OPERATORS = array(
		'===',
		'==',
		'!==',
		'!=',
		'<=',
		'>=',
		'<',
		'>',
	);

	/**
	 * Parse a logical condition string into a structured condition object.
	 *
	 * @param string                 $condition_string The condition string to parse.
	 * @param array<string, mixed>[] $temp_conditions  Temporary conditions map (for recursion).
	 *
	 * @return array<string, mixed> The parsed condition object with keys: leftHand, operator, rightHand.
	 *
	 * @throws \Exception If parenthesis extraction exceeds 100 iterations.
	 */
	public static function parse_logical_condition_string( string $condition_string, array &$temp_conditions = array() ): array {
		$condition_string = self::extract_grouped_parentheses( $condition_string, $temp_conditions );

		return self::parse_logical_operators( $condition_string, $temp_conditions );
	}

	/**
	 * Replace grouped parentheses with temp placeholders, recursing into each group.
	 *
	 * @param string                 $condition_string The condition string.
	 * @param array<string, mixed>[] $temp_conditions  Temporary conditions map.
	 *
	 * @return string The condition string with grouped parentheses replaced by temp IDs.
	 *
	 * @throws \Exception If extraction exceeds 100 iterations.
	 */
	private static function extract_grouped_parentheses( string $condition_string, array &$temp_conditions ): string {
		$iteration = 0;

		while ( true ) {
			$group = self::find_outermost_group( $condition_string );

			if ( null === $group ) {
				break;
			}

			$bracket_condition = self::parse_logical_condition_string( $group['content'], $temp_conditions );
			$temp_id           = '__TEMP__' . uniqid( '', true ) . '__';
			$temp_conditions[ $temp_id ] = $bracket_condition;

			$condition_string = str_replace( $group['match'], $temp_id, $condition_string );

			$iteration++;
			if ( $iteration > 100 ) {
				throw new \Exception( 'Too many iterations in parse_logical_condition_string' );
			}
		}

		return $condition_string;
	}

	/**
	 * Find the first outermost grouping parenthesis (not a function call).
	 *
	 * @param string $condition_string The condition string to search.
	 *
	 * @return array{content: string, match: string}|null The group content and full match, or null if none found.
	 */
	private static function find_outermost_group( string $condition_string ): ?array {
		$depth       = 0;
		$start_index = -1;
		$length      = strlen( $condition_string );

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $condition_string[ $i ];

			if ( '(' === $char ) {
				$depth = self::handle_open_paren( $condition_string, $i, $depth, $start_index );
			} elseif ( ')' === $char ) {
				$depth--;
			} else {
				continue;
			}

			if ( -1 !== $start_index && 0 === $depth ) {
				return array(
					'content' => substr( $condition_string, $start_index + 1, $i - $start_index - 1 ),
					'match'   => substr( $condition_string, $start_index, $i - $start_index + 1 ),
				);
			}
		}

		return null;
	}

	/**
	 * Handle an opening parenthesis: track grouping start and increment depth.
	 *
	 * @param string $condition_string The condition string.
	 * @param int    $index            Current character index.
	 * @param int    $depth            Current nesting depth.
	 * @param int    $start_index      Start index of the current group (updated by reference).
	 *
	 * @return int The new depth.
	 */
	private static function handle_open_paren( string $condition_string, int $index, int $depth, int &$start_index ): int {
		$prev_char   = $index > 0 ? $condition_string[ $index - 1 ] : '';
		$is_grouping = ! preg_match( '/[a-zA-Z_$0-9)]/', $prev_char );

		if ( $is_grouping && 0 === $depth ) {
			$start_index = $index;
		}

		return $depth + 1;
	}

	/**
	 * Parse logical operators (||, &&) with correct precedence.
	 *
	 * @param string                 $condition_string The condition string (parentheses already extracted).
	 * @param array<string, mixed>[] $temp_conditions  Temporary conditions map.
	 *
	 * @return array<string, mixed> The parsed condition object.
	 */
	private static function parse_logical_operators( string $condition_string, array &$temp_conditions ): array {
		$or_conditions = array_map( 'trim', explode( '||', $condition_string ) );
		if ( count( $or_conditions ) > 1 ) {
			return array(
				'leftHand'  => self::parse_logical_condition_string( $or_conditions[0], $temp_conditions ),
				'operator'  => '||',
				'rightHand' => self::parse_logical_condition_string( implode( '||', array_slice( $or_conditions, 1 ) ), $temp_conditions ),
			);
		}

		$and_conditions = array_map( 'trim', explode( '&&', $condition_string ) );
		if ( count( $and_conditions ) > 1 ) {
			return array(
				'leftHand'  => self::parse_logical_condition_string( $and_conditions[0], $temp_conditions ),
				'operator'  => '&&',
				'rightHand' => self::parse_logical_condition_string( implode( '&&', array_slice( $and_conditions, 1 ) ), $temp_conditions ),
			);
		}

		return self::parse_comparison_condition_string( trim( $condition_string ), $temp_conditions );
	}

	/**
	 * Parse a comparison condition string.
	 *
	 * @param string                 $condition_string The condition string.
	 * @param array<string, mixed>[] $temp_conditions  Temporary conditions map.
	 *
	 * @return array<string, mixed> The parsed condition object.
	 */
	private static function parse_comparison_condition_string( string $condition_string, array &$temp_conditions ): array {
		foreach ( self::COMPARISON_OPERATORS as $operator ) {
			$operator_pattern = ' ' . $operator . ' ';
			$operator_index   = strpos( $condition_string, $operator_pattern );

			if ( false !== $operator_index ) {
				$left_hand  = substr( $condition_string, 0, $operator_index );
				$right_hand = substr( $condition_string, $operator_index + strlen( $operator_pattern ) );

				return array(
					'leftHand'  => self::resolve_operand_value( trim( $left_hand ) ),
					'operator'  => $operator,
					'rightHand' => self::resolve_operand_value( trim( $right_hand ) ),
				);
			}
		}

		if ( 0 === strpos( $condition_string, '!' ) ) {
			$left_hand = trim( substr( $condition_string, 1 ) );

			return array(
				'leftHand'  => $temp_conditions[ $left_hand ] ?? self::resolve_operand_value( $left_hand ),
				'operator'  => 'isFalsy',
				'rightHand' => null,
			);
		}

		return array(
			'leftHand'  => $temp_conditions[ $condition_string ] ?? self::resolve_operand_value( $condition_string ),
			'operator'  => 'isTruthy',
			'rightHand' => null,
		);
	}

	/**
	 * Resolve an operand string to a typed value.
	 *
	 * @param string $operand The operand string.
	 *
	 * @return mixed The resolved operand value.
	 */
	private static function resolve_operand_value( string $operand ) {
		$json_decoded = json_decode( $operand, true );
		if ( null !== $json_decoded || 'null' === $operand ) {
			return $json_decoded;
		}

		if ( is_numeric( $operand ) ) {
			return (float) $operand;
		}

		return "\"$operand\"";
	}
}
