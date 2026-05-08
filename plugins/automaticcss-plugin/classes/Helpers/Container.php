<?php
/**
 * Dependency Injection Container.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Helpers;

/**
 * Simple DI Container for managing dependencies.
 *
 * Supports:
 * - Simple value bindings (bind/get)
 * - Factory bindings for lazy instantiation (bind_factory)
 * - Singleton bindings that are instantiated once (bind_singleton)
 * - Interface to implementation mapping
 *
 * Usage:
 * ```php
 * $container = Container::get_instance();
 *
 * // Bind an interface to implementation
 * $container->bind_singleton(
 *     SettingsRepositoryInterface::class,
 *     fn() => Database_Settings::get_instance()
 * );
 *
 * // Resolve the dependency
 * $settings = $container->get( SettingsRepositoryInterface::class );
 * ```
 */
class Container {
	/**
	 * The singleton instance.
	 *
	 * @var Container|null
	 */
	private static $instance = null;

	/**
	 * The container's bindings (resolved values).
	 *
	 * @var array<string, mixed>
	 */
	private $bindings = array();

	/**
	 * Factory functions for lazy instantiation.
	 *
	 * @var array<string, callable>
	 */
	private $factories = array();

	/**
	 * Tracks which factories should only be called once.
	 *
	 * @var array<string, bool>
	 */
	private $singletons = array();

	/**
	 * Private constructor to prevent direct instantiation.
	 */
	private function __construct() {}

	/**
	 * Get the singleton instance.
	 *
	 * @return Container
	 */
	public static function get_instance(): Container {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Reset the container instance.
	 *
	 * This method is intended for testing purposes only.
	 * It clears all bindings and factories.
	 *
	 * @return void
	 */
	public static function reset_instance(): void {
		self::$instance = null;
	}

	/**
	 * Bind a value directly to the container.
	 *
	 * @param string $id The identifier (typically a class or interface name).
	 * @param mixed  $value The value to bind.
	 * @return void
	 */
	public function bind( string $id, $value ): void {
		$this->bindings[ $id ] = $value;
	}

	/**
	 * Bind a factory function for lazy instantiation.
	 *
	 * The factory will be called each time the dependency is resolved.
	 *
	 * @param string   $id The identifier.
	 * @param callable $factory A function that returns the dependency.
	 * @return void
	 */
	public function bind_factory( string $id, callable $factory ): void {
		$this->factories[ $id ] = $factory;
		$this->singletons[ $id ] = false;
	}

	/**
	 * Bind a singleton factory.
	 *
	 * The factory will be called only once, and the result cached.
	 *
	 * @param string   $id The identifier.
	 * @param callable $factory A function that returns the dependency.
	 * @return void
	 */
	public function bind_singleton( string $id, callable $factory ): void {
		$this->factories[ $id ] = $factory;
		$this->singletons[ $id ] = true;
	}

	/**
	 * Get a value from the container.
	 *
	 * Resolution order:
	 * 1. Check direct bindings
	 * 2. Check factories (and cache if singleton)
	 * 3. Throw exception if not found
	 *
	 * @param string $id The identifier.
	 * @return mixed
	 * @throws \Exception If the binding doesn't exist.
	 */
	public function get( string $id ) {
		// Check direct bindings first.
		if ( isset( $this->bindings[ $id ] ) ) {
			return $this->bindings[ $id ];
		}

		// Check for a factory.
		if ( isset( $this->factories[ $id ] ) ) {
			$instance = call_user_func( $this->factories[ $id ], $this );

			// Cache singleton instances.
			if ( $this->singletons[ $id ] ?? false ) {
				$this->bindings[ $id ] = $instance;
			}

			return $instance;
		}

		throw new \Exception( esc_html( "No binding found for {$id}" ) );
	}

	/**
	 * Check if a binding or factory exists.
	 *
	 * @param string $id The identifier.
	 * @return bool
	 */
	public function has( string $id ): bool {
		return isset( $this->bindings[ $id ] ) || isset( $this->factories[ $id ] );
	}

	/**
	 * Get a value from the container, or null if not found.
	 *
	 * This is a convenience method for cases where you want to check
	 * if a binding exists without throwing an exception.
	 *
	 * @param string $id The identifier.
	 * @return mixed|null The resolved value, or null if not found.
	 */
	public function get_or_null( string $id ) {
		return $this->has( $id ) ? $this->get( $id ) : null;
	}

	/**
	 * Clear all bindings and factories.
	 *
	 * Useful for testing to reset the container state.
	 *
	 * @return void
	 */
	public function clear(): void {
		$this->bindings = array();
		$this->factories = array();
		$this->singletons = array();
	}
}
