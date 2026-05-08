<?php
/**
 * Style Queue Factory.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Helpers;

use WP_Styles;

/**
 * Factory for creating and managing style queues.
 */
class Style_Queue_Factory {
	/**
	 * The singleton instance.
	 *
	 * @var Style_Queue_Factory|null
	 */
	private static $instance = null;

	/**
	 * The default queue instance.
	 *
	 * @var Style_Queue|null
	 */
	private $default_queue = null;

	/**
	 * Private constructor to prevent direct instantiation.
	 */
	private function __construct() {}

	/**
	 * Get the singleton instance.
	 *
	 * @return Style_Queue_Factory
	 */
	public static function get_instance(): Style_Queue_Factory {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Create a new style queue.
	 *
	 * @param WP_Styles|null $queue The WP_Styles instance to use.
	 * @return Style_Queue
	 */
	public function create_queue( ?WP_Styles $queue = null ): Style_Queue {
		return new Style_Queue( $queue );
	}

	/**
	 * Get or create the default queue.
	 *
	 * @return Style_Queue
	 */
	public function get_default_queue(): Style_Queue {
		if ( null === $this->default_queue ) {
			$queue = new \WP_Styles();
			// Clear any default styles that WordPress might have registered.
			$queue->registered = array(); // Without this, ALL WordPress styles are registered.
			$queue->queue = array();
			$queue->done = array();
			$queue->to_do = array();
			$this->default_queue = $this->create_queue( $queue );
		}
		return $this->default_queue;
	}

	/**
	 * Set the default queue.
	 *
	 * @param Style_Queue $queue The queue to set as default.
	 * @return void
	 */
	public function set_default_queue( Style_Queue $queue ): void {
		$this->default_queue = $queue;
	}
}
