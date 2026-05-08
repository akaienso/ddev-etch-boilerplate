<?php
/**
 * ServerSideEventsFramesParser.php
 *
 * This file contains the ServerSideEventsFramesParser class which parses SSE frames.
 *
 * PHP version 8.2+
 *
 * @category  Plugin
 * @package   Etch\Services\Ai
 */

declare(strict_types=1);

namespace Etch\Services\Ai;

/**
 * ServerSideEventsFramesParser class.
 */
class ServerSideEventsFramesParser {

	/**
	 * Extract frames from an SSE buffer.
	 *
	 * @param string &$buffer The SSE buffer to extract frames from.
	 * @return array<int, string> Array of the extracted frames.
	 */
	public function extract_frames( string &$buffer ): array {
		$frames = array();

		while ( false !== ( $pos = strpos( $buffer, "\n\n" ) ) ) {
			$frame = substr( $buffer, 0, $pos );
			$buffer = substr( $buffer, $pos + 2 );

			// Extract and filter in one pass
			$data_lines = $this->filter_data_lines( explode( "\n", $frame ) );

			if ( ! empty( $data_lines ) ) {
				$frames[] = trim( implode( "\n", $data_lines ) );
			}
		}

		return $frames;
	}

	/**
	 * Filter data lines from an array of lines.
	 *
	 * @param array<int, string> $lines The lines to filter.
	 * @return array<int, string> Array of the filtered lines.
	 */
	public function filter_data_lines( array $lines ): array {
		$data_lines = array();

		foreach ( $lines as $line ) {
			$line = rtrim( $line, "\r" );
			if ( str_starts_with( $line, 'data:' ) ) {
				$data_lines[] = ltrim( substr( $line, 5 ) );
			}
		}

		return $data_lines;
	}
}
