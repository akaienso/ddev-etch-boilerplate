<?php
/**
 * Tests for CssProcessor.
 *
 * @package Etch
 */

use Etch\Blocks\Utilities\CssProcessor;
use WP_UnitTestCase;

require_once __DIR__ . '/../CssProcessor.php';



/**
 * Class CssProcessorTest
 *
 * Comprehensive tests for CssProcessor functionality including:
 * - SCSS-like syntax parsing
 * - REM function conversion
 * - Custom media handling
 */
class CssProcessorTest extends WP_UnitTestCase {

	/**
	 * Test parse_scss_like_syntax handles double underscores after &.
	 */
	public function test_parse_scss_like_syntax_handles_double_underscores_when_ampersand_used(): void {
		$css = '&__element { color: red; }';
		$selector = '.test';
		$processed = $this->call_private_method( 'parse_scss_like_syntax', $css, $selector );
		$this->assertEquals( '.test__element { color: red; }', $processed );
	}

	/**
	 * Test parse_scss_like_syntax handles single underscores after &.
	 */
	public function test_parse_scss_like_syntax_handles_single_underscore_when_ampersand_used(): void {
		$css = '&_element { color: red; }';
		$selector = '.test';
		$processed = $this->call_private_method( 'parse_scss_like_syntax', $css, $selector );
		$this->assertEquals( '.test_element { color: red; }', $processed );
	}

	/**
	 * Test parse_scss_like_syntax handles double dashes after &.
	 */
	public function test_parse_scss_like_syntax_handles_double_dash_when_ampersand_used(): void {
		$css = '&--element { color: red; }';
		$selector = '.test';
		$processed = $this->call_private_method( 'parse_scss_like_syntax', $css, $selector );
		$this->assertEquals( '.test--element { color: red; }', $processed );
	}

	/**
	 * Test parse_scss_like_syntax handles single dash after &.
	 */
	public function test_parse_scss_like_syntax_handles_single_dash_when_ampersand_used(): void {
		$css = '&-element { color: red; }';
		$selector = '.test';
		$processed = $this->call_private_method( 'parse_scss_like_syntax', $css, $selector );
		$this->assertEquals( '.test-element { color: red; }', $processed );
	}

	/**
	 * Test parse_scss_like_syntax leaves other &s untouched.
	 */
	public function test_parse_scss_like_syntax_leaves_other_ampersands_untouched(): void {
		$css = '& element, .element &, :has(>&) { color: red; }';
		$selector = '.test';
		$processed = $this->call_private_method( 'parse_scss_like_syntax', $css, $selector );
		$this->assertEquals( '& element, .element &, :has(>&) { color: red; }', $processed );
	}

	/**
	 * Test convert_rem_functions converts to-rem() to rem value in container queries.
	 */
	public function test_convert_rem_functions_converts_to_rem_in_container_queries(): void {
		$css = '@container (width >= to-rem(500px)) { color: red; }';
		$processed = $this->call_private_method( 'convert_rem_functions', $css );
		$this->assertEquals( '@container (width >= 31.25rem) { color: red; }', $processed );
	}

	/**
	 * Test convert_rem_functions converts to-rem() to rem value in property values.
	 */
	public function test_convert_rem_functions_converts_to_rem_in_property_values(): void {
		$css = 'some-property: to-rem(500px)';
		$processed = $this->call_private_method( 'convert_rem_functions', $css );
		$this->assertEquals( 'some-property: 31.25rem', $processed );
	}

	/**
	 * Test convert_rem_functions handles spaces.
	 */
	public function test_convert_rem_functions_handles_spaces(): void {
		$css = 'some-property: to-rem( 500px )';
		$processed = $this->call_private_method( 'convert_rem_functions', $css );
		$this->assertEquals( 'some-property: 31.25rem', $processed );
	}

	/**
	 * Test convert_rem_functions handles decimals.
	 */
	public function test_convert_rem_functions_handles_decimals(): void {
		$css = 'some-property: to-rem( 20.5px )';
		$processed = $this->call_private_method( 'convert_rem_functions', $css );
		$this->assertEquals( 'some-property: 1.2813rem', $processed );
	}

	/**
	 * Test extract_custom_media_definitions parses custom media definitions.
	 */
	public function test_extract_custom_media_definitions_parses_definitions(): void {
		$css = <<<CSS
@custom-media --small-screen (max-width: 600px);
@custom-media --large-screen (min-width: 1200px);
CSS;
		$definitions = CssProcessor::extract_custom_media_definitions( $css );
		$this->assertEquals(
			array(
				'--small-screen' => '(max-width: 600px)',
				'--large-screen' => '(min-width: 1200px)',
			),
			$definitions
		);
	}

	/**
	 * Test replace_custom_media_definitions does not replace when not defined.
	 */
	public function test_replace_custom_media_definitions_does_not_replace_when_not_defined(): void {
		$css = '@media (--custom-media) { color: red; }';
		$processed = $this->call_private_method( 'replace_custom_media_definitions', $css, array() );
		$this->assertEquals( '@media (--custom-media) { color: red; }', $processed );

		$css2 = '@container (--custom-media) { color: red; }';
		$processed2 = $this->call_private_method( 'replace_custom_media_definitions', $css2, array() );
		$this->assertEquals( '@container (--custom-media) { color: red; }', $processed2 );
	}

	/**
	 * Test replace_custom_media_definitions replaces when defined.
	 */
	public function test_replace_custom_media_definitions_replaces_when_defined(): void {
		$css = '@media (--custom-media) { color: red; }';
		$defs = array( '--custom-media' => '(min-width: 500px)' );
		$processed = $this->call_private_method( 'replace_custom_media_definitions', $css, $defs );
		$this->assertEquals( '@media (min-width: 500px) { color: red; }', $processed );

		$css2 = '@container (--custom-media) { color: red; }';
		$processed2 = $this->call_private_method( 'replace_custom_media_definitions', $css2, $defs );
		$this->assertEquals( '@container (min-width: 500px) { color: red; }', $processed2 );
	}

	/**
	 * Test replace_custom_media_definitions replaces complex definitions.
	 */
	public function test_replace_custom_media_definitions_replaces_complex_definitions(): void {
		$css = '@container (--custom-media) { color: red; }';
		$defs = array( '--custom-media' => 'screen and (min-width: 500px) and (orientation: landscape)' );
		$processed = $this->call_private_method( 'replace_custom_media_definitions', $css, $defs );
		$this->assertEquals( '@container screen and (min-width: 500px) and (orientation: landscape) { color: red; }', $processed );
	}

	/**
	 * Test sanitize_css strips closing style tag attempts.
	 */
	public function test_sanitize_css_strips_style_closing_tag(): void {
		$css = 'color: red; </style><script>alert(1)</script>';
		$processed = $this->call_private_method( 'sanitize_css', $css );
		$this->assertEquals( 'color: red; style><script>alert(1)script>', $processed );
	}

	/**
	 * Test sanitize_css strips case-insensitive style closing tags.
	 */
	public function test_sanitize_css_strips_case_insensitive_closing_tags(): void {
		$css = 'color: red; </STYLE>';
		$processed = $this->call_private_method( 'sanitize_css', $css );
		$this->assertEquals( 'color: red; STYLE>', $processed );
	}

	/**
	 * Test sanitize_css preserves child combinator selector.
	 */
	public function test_sanitize_css_preserves_child_combinator(): void {
		$css = '.parent > .child { color: red; }';
		$processed = $this->call_private_method( 'sanitize_css', $css );
		$this->assertEquals( '.parent > .child { color: red; }', $processed );
	}

	/**
	 * Test sanitize_css preserves media query range syntax.
	 */
	public function test_sanitize_css_preserves_media_query_range_syntax(): void {
		$css = '@media (400px < width < 800px) { color: red; }';
		$processed = $this->call_private_method( 'sanitize_css', $css );
		$this->assertEquals( '@media (400px < width < 800px) { color: red; }', $processed );
	}

	/**
	 * Test sanitize_css runs after other transformations in preprocess_css.
	 */
	public function test_sanitize_css_catches_injection_after_transformations(): void {
		$css = '</style><script>alert(1)</script>';
		$processed = CssProcessor::preprocess_css( $css, '.test' );
		$this->assertStringNotContainsString( '</', $processed );
	}

	/**
	 * Helper to call private/protected static methods.
	 *
	 * @param string $method Method name.
	 * @param mixed  ...$args Arguments to pass to the method.
	 * @return mixed Method return value.
	 */
	private function call_private_method( string $method, ...$args ) {
		$ref = new ReflectionClass( CssProcessor::class );
		$m = $ref->getMethod( $method );
		return $m->invokeArgs( null, $args );
	}
}
