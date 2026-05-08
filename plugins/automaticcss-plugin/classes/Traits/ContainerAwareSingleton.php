<?php
/**
 * Automatic.css ContainerAwareSingleton trait file.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Traits;

use Automatic_CSS\Helpers\Container;

/**
 * A singleton trait that delegates to the DI Container when available.
 *
 * This trait provides backward compatibility with the traditional Singleton pattern
 * while enabling proper dependency injection. When a class is registered in the
 * Container, get_instance() will return the Container's instance. Otherwise, it
 * falls back to creating a traditional singleton.
 *
 * Usage:
 * 1. Use this trait in your class
 * 2. Register your class in the Container during bootstrap
 * 3. Existing get_instance() calls will automatically use the Container
 *
 * Benefits:
 * - Enables unit testing by allowing test doubles to be registered in Container
 * - Maintains backward compatibility with existing get_instance() calls
 * - Supports gradual migration from singletons to full DI
 *
 * @see Container For how to register classes
 */
trait ContainerAwareSingleton {

	/**
	 * Stores instances per class when not using Container (fallback mode).
	 *
	 * Uses an array keyed by class name to ensure each class using this trait
	 * has its own separate instance (late static binding).
	 *
	 * @var array<string, static>
	 */
	private static array $instances = array();

	/**
	 * Get the singleton instance.
	 *
	 * Resolution order:
	 * 1. Check if class is registered in Container - return Container's instance
	 * 2. Fall back to traditional singleton pattern (per-class instances)
	 *
	 * @return static
	 */
	public static function get_instance() {
		$container = Container::get_instance();
		if ( $container->has( static::class ) ) {
			return $container->get( static::class );
		}
		// Fallback to traditional singleton for backward compatibility.
		// Use static::class as key to ensure each class has its own instance.
		if ( ! isset( self::$instances[ static::class ] ) ) {
			// @phpstan-ignore new.static (intentional: singleton pattern relies on late static binding)
			self::$instances[ static::class ] = new static();
		}
		return self::$instances[ static::class ];
	}

	/**
	 * Reset the singleton instance.
	 *
	 * This method is intended for testing purposes only.
	 * It clears the static instance for the calling class, allowing tests to start fresh.
	 * Note: This does NOT clear the Container binding - use Container::clear() for that.
	 *
	 * @return void
	 */
	public static function reset_instance(): void {
		unset( self::$instances[ static::class ] );
	}

	/**
	 * Create a new instance for registration in the Container.
	 *
	 * This method allows creating an instance to register in the Container
	 * without going through get_instance(), which would cause infinite recursion
	 * if the Container factory called get_instance() while the class was already
	 * registered.
	 *
	 * Usage in bootstrap:
	 * ```php
	 * $container->bind(MyClass::class, MyClass::create());
	 * ```
	 *
	 * @return static
	 */
	public static function create() {
		// @phpstan-ignore new.static (intentional: singleton pattern relies on late static binding)
		return new static();
	}

	/**
	 * Singletons should not be cloneable.
	 */
	final protected function __clone() {}

	/**
	 * Singletons should not be restorable from strings.
	 *
	 * @throws \Exception Cannot unserialize a singleton.
	 */
	final public function __wakeup() {
		throw new \Exception( 'Cannot unserialize a singleton.' );
	}
}
