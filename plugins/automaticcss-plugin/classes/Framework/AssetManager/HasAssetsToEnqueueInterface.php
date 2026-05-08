<?php
/**
 * Interface HasAssetsToEnqueueInterface
 *
 * Provides a contract for enqueuing assets based on context.
 *
 * @package Automatic_CSS\Framework\AssetManager
 */

namespace Automatic_CSS\Framework\AssetManager;

use Automatic_CSS\Framework\ContextManager\Context;

interface HasAssetsToEnqueueInterface {
	/**
	 * Enqueue assets for the specified context.
	 *
	 * @param Context $context The context in which to enqueue assets.
	 * @return void
	 */
	public function enqueue_assets( Context $context ): void;

	/**
	 * Get assets to enqueue for the specified context.
	 *
	 * @param Context $context The context in which to get assets to enqueue.
	 * @return array<CSS_File|JS_File> The assets to enqueue for the specified context.
	 */
	public function get_assets_to_enqueue( Context $context ): array;
}
