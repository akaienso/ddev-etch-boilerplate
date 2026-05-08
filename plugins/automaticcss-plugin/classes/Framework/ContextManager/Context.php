<?php
/**
 * Class Context
 *
 * Defines valid context values for the system.
 *
 * @package Automatic_CSS\Framework\ContextManager
 */

namespace Automatic_CSS\Framework\ContextManager;

/**
 * Class Context
 *
 * Provides constants and validation for context values.
 */
class Context {
	public const PREVIEW = 'preview';
	public const BUILDER = 'builder';
	public const FRONTEND = 'frontend';
	public const UNKNOWN = 'unknown';

	/**
	 * The context.
	 *
	 * @var string
	 */
	private $context = null;

	/**
	 * Whether the context is active.
	 *
	 * @var boolean
	 */
	private $is_active;

	/**
	 * The determiners that are active for this context.
	 *
	 * @var array<string>
	 */
	private $determiners;

	/**
	 * Constructor.
	 *
	 * @param string        $context The context.
	 * @param boolean       $is_active Whether the context is active.
	 * @param array<string> $determiners The determiners that are active for this context.
	 */
	public function __construct( $context, $is_active = false, $determiners = array() ) {
		if ( self::is_valid( $context ) ) {
			$this->context = $context;
		} else {
			$this->context = self::UNKNOWN;
		}
		$this->is_active = $is_active;
		$this->determiners = $determiners;
	}

	/**
	 * Get the context.
	 *
	 * @return string
	 */
	public function get_context() {
		return $this->context;
	}

	/**
	 * Whether the context is active.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return $this->is_active;
	}

	/**
	 * Add a determiner to the context.
	 *
	 * @param string $determiner The determiner to add.
	 */
	public function activated_by( $determiner ) {
		$this->is_active = true;
		$this->determiners[] = $determiner; // Make sure the array is unique.
		$this->determiners = array_unique( $this->determiners );
	}

	/**
	 * Get the determiners for the context.
	 *
	 * @return array<string>
	 */
	public function get_determiners() {
		return $this->determiners;
	}

	/**
	 * Whether the context was determined by a specific determiner.
	 *
	 * @param string $determiner The determiner to check.
	 * @return boolean
	 */
	public function is_determined_by( $determiner ) {
		return in_array( $determiner, $this->determiners );
	}

	/**
	 * Validate if a value is a valid context.
	 *
	 * @param string $value The value to validate as a context.
	 * @return bool
	 */
	public static function is_valid( $value ) {
		return in_array(
			$value,
			array(
				self::PREVIEW,
				self::BUILDER,
				self::FRONTEND,
			),
			true
		);
	}
}
