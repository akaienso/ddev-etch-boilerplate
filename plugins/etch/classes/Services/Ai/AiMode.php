<?php
/**
 * AiMode.php
 *
 * The mode of the AI chat.
 *
 * @package Etch\Services\Ai
 */

declare(strict_types=1);

namespace Etch\Services\Ai;

/**
 * AiMode
 *
 * @package Etch\Services\Ai
 */
enum AiMode: string {
	case Ask = 'ask';
	case Build = 'build';

	/**
	 * Convert a string to an AiMode.
	 *
	 * @param mixed $raw The string to convert.
	 * @param self  $default The default mode to return if the string is not a valid mode.
	 * @return self The AiMode.
	 */
	public static function from_string( mixed $raw, self $default = self::Ask ): self {
		if ( ! is_string( $raw ) ) {
			return $default;
		}

		$raw = strtolower( trim( $raw ) );

		return self::tryFrom( $raw ) ?? $default;
	}
}
