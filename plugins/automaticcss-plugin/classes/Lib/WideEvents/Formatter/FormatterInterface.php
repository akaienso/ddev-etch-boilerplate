<?php
/**
 * FormatterInterface - Contract for formatting wide events.
 *
 * @package Automatic_CSS\Lib\WideEvents\Formatter
 */

namespace Automatic_CSS\Lib\WideEvents\Formatter;

/**
 * FormatterInterface.
 *
 * Defines how event data should be formatted for output.
 */
interface FormatterInterface {

	/**
	 * Format event data into a string.
	 *
	 * @param array<string, mixed> $data The event data to format.
	 * @return string The formatted output.
	 */
	public function format( array $data ): string;
}
