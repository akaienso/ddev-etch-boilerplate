<?php
/**
 * AiProviderFactoryTest.php
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Services\Ai\AgentProviders\Tests;

use Etch\Services\Ai\AgentProviders\AiProviderFactory;
use Etch\Services\Ai\AiMode;
use Etch\Services\Ai\AiProviderInterface;
use WP_Error;
use WP_UnitTestCase;

/**
 * Class AiProviderFactoryTest
 *
 * Tests that the factory returns the correct provider based on its predicate.
 */
class AiProviderFactoryTest extends WP_UnitTestCase {

	/**
	 * Build a no-op AI provider double for factory dispatch tests.
	 *
	 * @return AiProviderInterface
	 */
	private function make_provider_double(): AiProviderInterface {
		return new class() implements AiProviderInterface {
			/**
			 * No-op stream implementation for factory dispatch tests.
			 *
			 * @param array<int, array{role: string, content: string|list<array<string, mixed>>}>       $messages  The messages.
			 * @param AiMode                                                                            $mode      The mode.
			 * @param array{on_delta: callable, on_error?: callable|null, on_reasoning?: callable|null} $callbacks The callbacks.
			 * @param array<string, mixed>                                                              $tools Optional tools.
			 *
			 * @return ?WP_Error
			 */
			public function stream_ai_response( array $messages, AiMode $mode, array $callbacks, array $tools = array() ): ?WP_Error {
				return null;
			}

			/**
			 * No-op continue implementation for factory dispatch tests.
			 *
			 * @param array<string, mixed>                                                                                                    $body      Continuation context.
			 * @param array{on_delta: callable, on_error?: callable|null, on_reasoning?: callable|null, on_client_tool_calls?: callable|null} $callbacks The callbacks.
			 *
			 * @return ?WP_Error
			 */
			public function stream_ai_continue( array $body, array $callbacks ): ?WP_Error {
				return null;
			}
		};
	}

	/**
	 * Test that middleware provider is returned when predicate is true.
	 */
	public function test_middleware_provider_is_returned_when_predicate_is_true(): void {
		$middleware = $this->make_provider_double();
		$openai     = $this->make_provider_double();

		$factory = new AiProviderFactory( $middleware, $openai, fn(): bool => true );

		$this->assertSame( $middleware, $factory->create() );
	}

	/**
	 * Test that openai provider is returned when predicate is false.
	 */
	public function test_openai_provider_is_returned_when_predicate_is_false(): void {
		$middleware = $this->make_provider_double();
		$openai     = $this->make_provider_double();

		$factory = new AiProviderFactory( $middleware, $openai, fn(): bool => false );

		$this->assertSame( $openai, $factory->create() );
	}

	/**
	 * Test that predicate is re-evaluated on each create call.
	 */
	public function test_predicate_is_re_evaluated_on_each_create_call(): void {
		$middleware = $this->make_provider_double();
		$openai     = $this->make_provider_double();
		$flag       = true;

		$factory = new AiProviderFactory(
			$middleware,
			$openai,
			function () use ( &$flag ): bool {
				return $flag;
			}
		);

		$first  = $factory->create();
		$flag   = false;
		$second = $factory->create();

		$this->assertSame( $middleware, $first );
		$this->assertSame( $openai, $second );
	}
}
