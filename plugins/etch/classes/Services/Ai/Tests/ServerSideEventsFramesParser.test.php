<?php
/**
 * ServerSideEventsFramesParser.test.php
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Services\Ai\Tests;

use Etch\Services\Ai\ServerSideEventsFramesParser;
use Etch\Services\Ai\AgentProviders\OpenAiProvider;
use WP_UnitTestCase;

/**
 * Class ServerSideEventsFramesParserTest
 *
 * Tests the ServerSideEventsFramesParser class.
 */
class ServerSideEventsFramesParserTest extends WP_UnitTestCase {

	/**
	 * Test extracts complete frame when buffer has double newline.
	 */
	public function test_extracts_complete_frame_when_buffer_has_double_newline(): void {
		$parser = new ServerSideEventsFramesParser();
		$buffer = "data: hello\n\n";

		$frames = $parser->extract_frames( $buffer );

		$this->assertEquals( array( 'hello' ), $frames );
	}

	/**
	 * Test leaves incomplete frame when buffer missing double newline.
	 */
	public function test_leaves_incomplete_frame_when_buffer_missing_double_newline(): void {
		$parser = new ServerSideEventsFramesParser();
		$buffer = 'data: incomplete';

		$parser->extract_frames( $buffer );

		$this->assertEquals( 'data: incomplete', $buffer );
	}

	/**
	 * Test extracts multiple frames when buffer has multiple complete frames.
	 */
	public function test_extracts_multiple_frames_when_buffer_has_multiple_complete_frames(): void {
		$parser = new ServerSideEventsFramesParser();
		$buffer = "data: first\n\ndata: second\n\n";

		$frames = $parser->extract_frames( $buffer );

		$this->assertEquals( array( 'first', 'second' ), $frames );
	}

	/**
	 * Test combines multiline data when multiple data lines in frame.
	 */
	public function test_combines_multiline_data_when_multiple_data_lines_in_frame(): void {
		$parser = new ServerSideEventsFramesParser();
		$buffer = "data: line1\ndata: line2\n\n";

		$frames = $parser->extract_frames( $buffer );

		$this->assertEquals( array( "line1\nline2" ), $frames );
	}

	/**
	 * Test filters out non-data lines when mixed SSE lines present.
	 */
	public function test_filters_out_non_data_lines_when_mixed_sse_lines_present(): void {
		$parser = new ServerSideEventsFramesParser();
		$lines = array( 'event: delta', 'data: payload', 'id: 123' );

		$result = $parser->filter_data_lines( $lines );

		$this->assertEquals( array( 'payload' ), $result );
	}

	/**
	 * Test removes carriage returns when CRLF line endings.
	 */
	public function test_removes_carriage_returns_when_crlf_line_endings(): void {
		$parser = new ServerSideEventsFramesParser();
		$lines = array( "data: hello\r" );

		$result = $parser->filter_data_lines( $lines );

		$this->assertEquals( array( 'hello' ), $result );
	}

	/**
	 * Test trims leading whitespace when space after data colon.
	 */
	public function test_trims_leading_whitespace_when_space_after_data_colon(): void {
		$parser = new ServerSideEventsFramesParser();
		$lines = array( 'data:   hello' );

		$result = $parser->filter_data_lines( $lines );

		$this->assertEquals( array( 'hello' ), $result );
	}

	/**
	 * Test returns empty array when no data lines present.
	 */
	public function test_returns_empty_array_when_frame_has_no_data_lines(): void {
		$parser = new ServerSideEventsFramesParser();
		$lines = array( 'event: error', 'id: 123' );

		$result = $parser->filter_data_lines( $lines );

		$this->assertEquals( array(), $result );
	}
}
