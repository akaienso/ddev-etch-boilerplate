<?php
/**
 * AiProviderFactory.php
 *
 * Selects the AI provider implementation based on an injected predicate.
 *
 * PHP version 8.2+
 *
 * @category  Plugin
 * @package   Etch\Services\Ai\AgentProviders
 */

declare(strict_types=1);

namespace Etch\Services\Ai\AgentProviders;

use Etch\Helpers\Flag;
use Etch\Services\Ai\AiProviderInterface;

/**
 * AiProviderFactory
 *
 * @package Etch\Services\Ai\AgentProviders
 */
class AiProviderFactory {

	/**
	 * Predicate returning true when the middleware provider should be used.
	 *
	 * @var callable(): bool
	 */
	private $is_middleware_enabled;

	/**
	 * Constructor.
	 *
	 * @param AiProviderInterface $middleware_provider   The middleware provider instance.
	 * @param AiProviderInterface $openai_provider       The OpenAI provider instance.
	 * @param callable(): bool    $is_middleware_enabled Predicate returning true when the middleware provider should be selected.
	 */
	public function __construct(
		private AiProviderInterface $middleware_provider,
		private AiProviderInterface $openai_provider,
		callable $is_middleware_enabled
	) {
		$this->is_middleware_enabled = $is_middleware_enabled;
	}

	/**
	 * Return the provider selected by the current predicate.
	 *
	 * @return AiProviderInterface
	 */
	public function create(): AiProviderInterface {
		return ( $this->is_middleware_enabled )()
			? $this->middleware_provider
			: $this->openai_provider;
	}

	/**
	 * Build a factory with the real providers and the production flag binding.
	 *
	 * @return self
	 */
	public static function default(): self {
		return new self(
			new MiddlewareProvider(),
			new OpenAiProvider(),
			static fn(): bool => Flag::is_on( 'ENABLE_AI_USE_MIDDLEWARE' )
		);
	}
}
