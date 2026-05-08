<?php
/**
 * Interface DeterminesContextInterface
 *
 * Provides a contract for detecting the current context (builder, preview, frontend, unknown).
 *
 * @package Automatic_CSS\Framework\Context
 */

namespace Automatic_CSS\Framework\ContextManager;

interface DeterminesContextInterface {
	/**
	 * Returns true if the current context is a builder.
	 *
	 * @return bool
	 */
	public function is_builder_context();

	/**
	 * Returns true if the current context is a preview.
	 *
	 * @return bool
	 */
	public function is_preview_context();

	/**
	 * Returns true if the current context is the frontend.
	 *
	 * @return bool
	 */
	public function is_frontend_context();

	/**
	 * Get the name of the context determiner.
	 *
	 * @return string
	 */
	public static function get_name();
}
