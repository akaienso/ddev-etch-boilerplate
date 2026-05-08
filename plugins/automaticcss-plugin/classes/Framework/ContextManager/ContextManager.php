<?php
/**
 * ContextManager
 *
 * Manages the context of the current request.
 *
 * @package Automatic_CSS\Framework\ContextManager
 */

namespace Automatic_CSS\Framework\ContextManager;

use Automatic_CSS\Framework\ContextManager\DeterminesContextInterface;
use Automatic_CSS\Framework\Core\Core;
use Automatic_CSS\Framework\Integrations\Gutenberg;

/**
 * Class ContextManager
 */
class ContextManager {

	/**
	 * Context determiners (integrations + core).
	 *
	 * @var array<string, DeterminesContextInterface>
	 */
	private $context_determiners;

	/**
	 * Core instance.
	 *
	 * @var DeterminesContextInterface
	 */
	private $core_instance;

	/**
	 * Gutenberg instance.
	 *
	 * @var DeterminesContextInterface
	 */
	private $gutenberg_instance;

	/**
	 * Constructor.
	 *
	 * @param array<string, DeterminesContextInterface> $context_determiners Context determiners.
	 * @param Core                                      $core_instance Core instance.
	 * @param Gutenberg                                 $gutenberg_instance Gutenberg instance.
	 */
	public function __construct( $context_determiners = null, $core_instance = null, $gutenberg_instance = null ) {
		$this->core_instance = $core_instance ?? new Core();
		$this->gutenberg_instance = $gutenberg_instance ?? new Gutenberg();
		$this->context_determiners = array_merge(
			array_filter(
				$context_determiners,
				function ( $integration ) {
					return $integration instanceof DeterminesContextInterface;
				}
			),
			array( Gutenberg::get_name() => $this->gutenberg_instance ),
			array( Core::get_name() => $this->core_instance ),
		);
	}

	/**
	 * Get the current context.
	 *
	 * @return array<string, Context>
	 */
	public function get_context() {
		$contexts = array(
			'preview' => new Context( Context::PREVIEW ),
			'builder' => new Context( Context::BUILDER ),
			'frontend' => new Context( Context::FRONTEND ),
		);

		// STEP: Check if the preview or builder context is active. They can be active at the same time.
		foreach ( $this->context_determiners as $determiner ) {
			if ( $determiner->is_preview_context() ) {
				$contexts['preview']->activated_by( $determiner::class );
			}
		}

		foreach ( $this->context_determiners as $determiner ) {
			if ( $determiner->is_builder_context() ) {
				$contexts['builder']->activated_by( $determiner::class );
			}
		}

		// STEP: If neither the preview nor builder context is active, check if the frontend context is active.
		if ( ! $contexts['preview']->is_active() && ! $contexts['builder']->is_active() ) {
			foreach ( $this->context_determiners as $determiner ) {
				if ( $determiner->is_frontend_context() ) {
					$contexts['frontend']->activated_by( $determiner::class );
				}
			}
		}

		return $contexts;
	}
}
