<?php
/**
 * Automatic.css CSS Generation Orchestrator.
 *
 * Centralizes all CSS generation entry points into a single orchestrator.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\CSS_Engine;

use Automatic_CSS\Exceptions\CSS_Generation_Failed;
use Automatic_CSS\Framework\Core\Core;
use Automatic_CSS\Framework\IntegrationManager\GeneratesCssFilesInterface;
use Automatic_CSS\Helpers\Logger;
use Automatic_CSS\Helpers\Timer;
use Automatic_CSS\Helpers\WideEventLogger;
use Automatic_CSS\Traits\ContainerAwareSingleton;

/**
 * CSS Generation Orchestrator.
 *
 * Provides a single entry point for all CSS generation, caching component
 * instances and ensuring consistent hook firing across all generation triggers.
 */
class GenerationOrchestrator {

	use ContainerAwareSingleton;

	/**
	 * Cached component instances that can generate CSS files.
	 *
	 * @var array<string, GeneratesCssFilesInterface>
	 */
	private array $components = array();

	/**
	 * The Core component, which handles settings-to-variables transformation.
	 *
	 * @var Core|null
	 */
	private ?Core $core = null;

	/**
	 * Whether the orchestrator has been initialized.
	 *
	 * @var bool
	 */
	private bool $initialized = false;

	/**
	 * Initialize the orchestrator.
	 *
	 * Registers as the handler for the CSS generation filter.
	 *
	 * @return GenerationOrchestrator
	 */
	public function init(): GenerationOrchestrator {
		if ( $this->initialized ) {
			return $this;
		}

		// Register as the handler for the generation filter.
		add_filter( 'automaticcss_generate_css', array( $this, 'handle_generation_request' ), 10, 2 );

		$this->initialized = true;
		return $this;
	}

	/**
	 * Register the Core component.
	 *
	 * Core is special because it handles settings-to-variables transformation
	 * in addition to generating its own CSS files.
	 *
	 * @param Core $core The Core component instance.
	 * @return void
	 */
	public function register_core( Core $core ): void {
		$this->core = $core;
		$this->components['core'] = $core;
	}

	/**
	 * Register a component that can generate CSS files.
	 *
	 * @param string                     $name      Unique name for the component.
	 * @param GeneratesCssFilesInterface $component The component instance.
	 * @return void
	 */
	public function register_component( string $name, GeneratesCssFilesInterface $component ): void {
		$this->components[ $name ] = $component;
	}

	/**
	 * Check if a component is registered.
	 *
	 * @param string $name The component name.
	 * @return bool
	 */
	public function has_component( string $name ): bool {
		return isset( $this->components[ $name ] );
	}

	/**
	 * Get a registered component.
	 *
	 * @param string $name The component name.
	 * @return GeneratesCssFilesInterface|null
	 */
	public function get_component( string $name ): ?GeneratesCssFilesInterface {
		return $this->components[ $name ] ?? null;
	}

	/**
	 * Get all registered components.
	 *
	 * @return array<string, GeneratesCssFilesInterface>
	 */
	public function get_components(): array {
		return $this->components;
	}

	/**
	 * Handle CSS generation request from filter.
	 *
	 * This is the filter callback for 'automaticcss_generate_css'.
	 *
	 * @param array $result   Previous result (for filter chaining, typically empty).
	 * @param array $settings The settings values to generate CSS from.
	 * @return array Generated file handles.
	 */
	public function handle_generation_request( array $result, array $settings ): array {
		return $this->generate( $settings );
	}

	/**
	 * Generate all CSS files from the given settings.
	 *
	 * This is the main entry point for CSS generation. All generation triggers
	 * should ultimately call this method.
	 *
	 * @param array $settings The settings values to generate CSS from.
	 * @return array The handles of the generated CSS files.
	 * @throws CSS_Generation_Failed If Core component is not registered.
	 */
	public function generate( array $settings ): array {
		if ( null === $this->core ) {
			throw new CSS_Generation_Failed(
				'GenerationOrchestrator: Core component must be registered before generating CSS. ' .
				'Call register_core() during plugin initialization.'
			);
		}

		$timer = new Timer();
		Logger::log( sprintf( '%s: starting', __METHOD__ ) );

		// Transform settings to SCSS variables using Core.
		$variables = apply_filters(
			'automaticcss_framework_variables',
			$this->core->get_framework_variables( $settings )
		);

		do_action( 'automaticcss_before_generate_framework_css', $variables );

		Logger::log(
			sprintf( "%s: generating CSS for these variables:\n%s", __METHOD__, print_r( $variables, true ) ),
			Logger::LOG_LEVEL_NOTICE
		);
		WideEventLogger::set( 'save_settings.scss_variables', $variables );

		// Generate CSS for each registered component.
		$generated_files = array();
		foreach ( $this->components as $component_key => $component ) {
			Logger::log( sprintf( '%s: generating CSS file for component %s', __METHOD__, $component_key ) );
			$generated_files = array_merge( $generated_files, $component->generate_own_css_files( $variables ) );
			Logger::log( sprintf( '%s: done generating CSS file for component %s', __METHOD__, $component_key ) );
		}

		do_action( 'automaticcss_after_generate_framework_css', $variables );

		Logger::log( sprintf( '%s: done in %s seconds', __METHOD__, $timer->get_time() ) );
		return $generated_files;
	}

	/**
	 * Delete all CSS files from all registered components.
	 *
	 * @return void
	 */
	public function delete_all_css_files(): void {
		foreach ( $this->components as $component ) {
			$css_files = $component->get_css_files();
			if ( ! empty( $css_files ) ) {
				foreach ( $css_files as $css_file ) {
					if ( is_a( $css_file, 'Automatic_CSS\Helpers\SCSS_File' ) ) {
						$css_file->delete_file();
					}
				}
			}
		}
	}
}
