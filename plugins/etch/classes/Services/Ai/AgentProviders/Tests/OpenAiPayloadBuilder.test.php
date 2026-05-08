<?php
/**
 * OpenAiPayloadBuilderTest.php
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Services\Ai\AgentProviders\Tests;

use DigitalGravy\FeatureFlag\FeatureFlag;
use DigitalGravy\FeatureFlag\FeatureFlagStore;
use Etch\Services\Ai\AgentProviders\OpenAiPayloadBuilder;
use WP_UnitTestCase;

/**
 * Class OpenAiPayloadBuilderTest
 *
 * Tests the OpenAiPayloadBuilder class.
 */
class OpenAiPayloadBuilderTest extends WP_UnitTestCase {

	/**
	 * Original flag store, saved before each test.
	 *
	 * @var mixed
	 */
	private $original_flag_store;

	/**
	 * Save the current flag store before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->original_flag_store = self::get_flag_store();
	}

	/**
	 * Restore the original flag store after each test.
	 */
	protected function tearDown(): void {
		self::set_flag_store( $this->original_flag_store );
		parent::tearDown();
	}

	/**
	 * Get the current flag store via reflection.
	 *
	 * @return mixed
	 */
	private static function get_flag_store() {
		$prop = new \ReflectionProperty( \Etch\Helpers\Flag::class, 'flag_store' );
		$prop->setAccessible( true );
		return $prop->getValue( null );
	}

	/**
	 * Set the flag store via reflection.
	 *
	 * @param mixed $store The flag store to set.
	 */
	private static function set_flag_store( $store ): void {
		$prop = new \ReflectionProperty( \Etch\Helpers\Flag::class, 'flag_store' );
		$prop->setAccessible( true );
		$prop->setValue( null, $store );
	}

	/**
	 * Test that follow up includes previous response id when built.
	 */
	public function test_follow_up_includes_previous_response_id_when_built(): void {
		$builder = new OpenAiPayloadBuilder();

		$payload = $builder->build_follow_up( 'resp_123', array() );

		$this->assertSame( 'resp_123', $payload['previous_response_id'] );
	}

	/**
	 * Test that follow up includes web search tool when web search fallback is true.
	 */
	public function test_follow_up_includes_web_search_tool_when_web_search_fallback_is_true(): void {
		$builder = new OpenAiPayloadBuilder();

		$payload = $builder->build_follow_up( 'resp_123', array(), array( 'web_search_fallback' => true ) );

		$tool_types = array_column( $payload['tools'], 'type' );
		$this->assertContains( 'web_search', $tool_types );
	}

	/**
	 * Test that follow up has no tools when web search fallback is false.
	 */
	public function test_follow_up_has_no_tools_when_web_search_fallback_is_false(): void {
		$builder = new OpenAiPayloadBuilder();

		$payload = $builder->build_follow_up( 'resp_123', array(), array( 'web_search_fallback' => false ) );

		$this->assertArrayNotHasKey( 'tools', $payload );
	}

	/**
	 * Test that follow up includes input as provided when built.
	 */
	public function test_follow_up_includes_input_as_provided_when_built(): void {
		$input = array(
			array(
				'type'    => 'function_call_output',
				'call_id' => 'call_abc',
				'output'  => '{"results":[]}',
			),
		);

		$builder = new OpenAiPayloadBuilder();

		$payload = $builder->build_follow_up( 'resp_123', $input );

		$this->assertSame( $input, $payload['input'] );
	}

	/**
	 * Test that follow-up includes reasoning effort and summary when flag is on and reasoning is enabled.
	 */
	public function test_follow_up_includes_reasoning_with_summary_when_flag_on_and_reasoning_enabled(): void {
		self::set_flag_store(
			new FeatureFlagStore(
				array( new FeatureFlag( 'ENABLE_AI_KEEP_REASONING_EFFORT', 'on' ) )
			)
		);

		$builder = new OpenAiPayloadBuilder();
		$payload = $builder->build_follow_up( 'resp_123', array(), array( 'reasoning_enabled' => true ) );

		$this->assertArrayHasKey( 'reasoning', $payload );
		$this->assertSame( 'low', $payload['reasoning']['effort'] );
		$this->assertSame( 'detailed', $payload['reasoning']['summary'] );
	}

	/**
	 * Test that follow-up omits reasoning when flag is on but reasoning is disabled.
	 */
	public function test_follow_up_omits_reasoning_when_flag_on_but_reasoning_disabled(): void {
		self::set_flag_store(
			new FeatureFlagStore(
				array( new FeatureFlag( 'ENABLE_AI_KEEP_REASONING_EFFORT', 'on' ) )
			)
		);

		$builder = new OpenAiPayloadBuilder();
		$payload = $builder->build_follow_up( 'resp_123', array(), array( 'reasoning_enabled' => false ) );

		$this->assertArrayNotHasKey( 'reasoning', $payload );
	}

	/**
	 * Test that follow-up omits reasoning when flag is off, even if reasoning_enabled is passed.
	 */
	public function test_follow_up_omits_reasoning_when_flag_is_off(): void {
		// Flag store is null in tests by default → Flag::is_on() returns false.
		$builder = new OpenAiPayloadBuilder();
		$payload = $builder->build_follow_up( 'resp_123', array(), array( 'reasoning_enabled' => true ) );

		$this->assertArrayNotHasKey( 'reasoning', $payload );
	}
}
