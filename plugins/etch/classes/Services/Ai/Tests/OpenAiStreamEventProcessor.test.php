<?php
/**
 * OpenAiStreamEventProcessorTest.php
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Services\Ai\Tests;

use Etch\Services\Ai\AgentProviders\OpenAiStreamEventProcessor;
use WP_UnitTestCase;

/**
 * Class OpenAiStreamEventProcessorTest
 *
 * Tests the OpenAiStreamEventProcessor class.
 */
class OpenAiStreamEventProcessorTest extends WP_UnitTestCase {

	/**
	 * Test that on_delta is called when text delta event received.
	 */
	public function test_on_delta_is_called_when_text_delta_event_received(): void {
		$processor = new OpenAiStreamEventProcessor();
		$received = array();

		$processor->process(
			array(
				'type'  => 'response.output_text.delta',
				'delta' => 'Hello world',
			),
			array(
				'on_delta' => function ( string $delta ) use ( &$received ) {
					$received[] = $delta;
				},
			)
		);

		$this->assertSame( array( 'Hello world' ), $received );
	}

	/**
	 * Test that empty delta is ignored when text delta has empty string.
	 */
	public function test_empty_delta_is_ignored_when_text_delta_has_empty_string(): void {
		$processor = new OpenAiStreamEventProcessor();
		$received = array();

		$processor->process(
			array(
				'type'  => 'response.output_text.delta',
				'delta' => '',
			),
			array(
				'on_delta' => function ( string $delta ) use ( &$received ) {
					$received[] = $delta;
				},
			)
		);

		$this->assertEmpty( $received );
	}

	/**
	 * Test that on_reasoning is called when reasoning delta event received.
	 */
	public function test_on_reasoning_is_called_when_reasoning_delta_event_received(): void {
		$processor = new OpenAiStreamEventProcessor();
		$received = array();

		$processor->process(
			array(
				'type'  => 'response.reasoning_summary_text.delta',
				'delta' => 'Thinking',
			),
			array(
				'on_delta'     => function () {},
				'on_reasoning' => function ( $reasoning ) use ( &$received ) {
					$received[] = $reasoning;
				},
			)
		);

		$this->assertSame( array( 'Thinking' ), $received );
	}

	/**
	 * Test that on_reasoning is called with null when reasoning done event received.
	 */
	public function test_on_reasoning_receives_null_when_reasoning_done_event_received(): void {
		$processor = new OpenAiStreamEventProcessor();
		$received = array();

		$processor->process(
			array( 'type' => 'response.reasoning_summary_text.done' ),
			array(
				'on_delta'     => function () {},
				'on_reasoning' => function ( $reasoning ) use ( &$received ) {
					$received[] = $reasoning;
				},
			)
		);

		$this->assertSame( array( null ), $received );
	}

	/**
	 * Test that response is captured when response completed event received.
	 */
	public function test_response_is_captured_when_response_completed_event_received(): void {
		$processor = new OpenAiStreamEventProcessor();
		$received = array();

		$response_data = array(
			'id'     => 'resp_123',
			'output' => array(
				array(
					'type'      => 'function_call',
					'call_id'   => 'call_1',
					'name'      => 'rag_search',
					'arguments' => '{"query":"test"}',
				),
			),
		);

		$processor->process(
			array(
				'type'     => 'response.completed',
				'response' => $response_data,
			),
			array(
				'on_delta'              => function () {},
				'on_response_completed' => function ( array $response ) use ( &$received ) {
					$received[] = $response;
				},
			)
		);

		$this->assertSame( array( $response_data ), $received );
	}

	/**
	 * Test that on_error is called when error event received.
	 */
	public function test_on_error_is_called_when_error_event_received(): void {
		$processor = new OpenAiStreamEventProcessor();
		$received = array();

		$event = array(
			'type'  => 'response.error',
			'error' => array( 'message' => 'API error' ),
		);

		$processor->process(
			$event,
			array(
				'on_delta'  => function () {},
				'on_error'  => function ( $error ) use ( &$received ) {
					$received[] = $error;
				},
			)
		);

		$this->assertSame( array( $event ), $received );
	}
}
