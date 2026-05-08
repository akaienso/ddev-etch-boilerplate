<?php
/**
 * Automatic.css Service Provider class file.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Helpers;

use Automatic_CSS\CSS_Engine\CSS_Engine;
use Automatic_CSS\CSS_Engine\GenerationOrchestrator;
use Automatic_CSS\Framework\Core\Core;
use Automatic_CSS\Framework\Integrations\Bricks;
use Automatic_CSS\Framework\Integrations\Etch;
use Automatic_CSS\Framework\Integrations\Gutenberg;
use Automatic_CSS\Model\Database_Settings;
use Automatic_CSS\Model\SettingsRepositoryInterface;

/**
 * Registers core service bindings in the DI container.
 *
 * This class sets up the dependency injection container with the core services
 * that the plugin needs. Using a container allows for:
 * - Easy testing with mock implementations
 * - Loose coupling between components
 * - Centralized service configuration
 *
 * @since 4.0.0
 */
class ServiceProvider {

	/**
	 * The DI container instance.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Constructor.
	 *
	 * @param Container $container The DI container instance.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Register all core services.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->register_settings();
		$this->register_css_engine();
		$this->register_generation_orchestrator();
	}

	/**
	 * Register settings services.
	 *
	 * Only registers if not already bound (prevents overwriting during plugin reactivation).
	 * We use bind() with a pre-created instance, not bind_singleton() with a factory,
	 * because these classes use ContainerAwareSingleton which delegates to Container.
	 * Using a factory that calls get_instance() would cause infinite recursion.
	 *
	 * @return void
	 */
	private function register_settings(): void {
		if ( ! $this->container->has( Database_Settings::class ) ) {
			$settings = Database_Settings::create();
			$this->container->bind( SettingsRepositoryInterface::class, $settings );
			$this->container->bind( Database_Settings::class, $settings );
		}
	}

	/**
	 * Register CSS engine service.
	 *
	 * @return void
	 */
	private function register_css_engine(): void {
		if ( ! $this->container->has( CSS_Engine::class ) ) {
			$css_engine = CSS_Engine::create();
			$this->container->bind( CSS_Engine::class, $css_engine );
		}
	}

	/**
	 * Register GenerationOrchestrator with cached component instances.
	 *
	 * Components are registered once here and reused for all CSS generation calls.
	 *
	 * @return void
	 */
	private function register_generation_orchestrator(): void {
		if ( ! $this->container->has( GenerationOrchestrator::class ) ) {
			$orchestrator = GenerationOrchestrator::create()->init();

			// Core is always present and handles settings-to-variables transformation.
			$orchestrator->register_core( new Core() );

			// Register integration components if their page builders are active.
			if ( Bricks::is_active() ) {
				$orchestrator->register_component( Bricks::get_name(), new Bricks() );
			}
			if ( Gutenberg::is_active() ) {
				$orchestrator->register_component( Gutenberg::get_name(), new Gutenberg() );
			}
			if ( Etch::is_active() ) {
				$orchestrator->register_component( Etch::get_name(), new Etch() );
			}

			$this->container->bind( GenerationOrchestrator::class, $orchestrator );
		}
	}
}
