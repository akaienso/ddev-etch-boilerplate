<?php
/**
 * Automatic.css CSS Generation Failed exception file.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Exceptions;

/**
 * CSS Generation Failed exception class.
 *
 * Thrown when SCSS compilation or CSS file generation fails.
 */
class CSS_Generation_Failed extends \Exception {

	/**
	 * Array of generation errors that occurred.
	 *
	 * @var array<array{file: string, message: string}>
	 */
	private array $errors;

	/**
	 * Constructor.
	 *
	 * @param string                                      $message Main error message.
	 * @param array<array{file: string, message: string}> $errors  Array of errors with file and message.
	 */
	public function __construct( string $message, array $errors = array() ) {
		parent::__construct( $message );
		$this->errors = $errors;
	}

	/**
	 * Get the array of generation errors.
	 *
	 * @return array<array{file: string, message: string}>
	 */
	public function get_errors(): array {
		return $this->errors;
	}
}
