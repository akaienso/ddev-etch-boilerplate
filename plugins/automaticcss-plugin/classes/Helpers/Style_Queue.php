<?php
/**
 * Style queue
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Helpers;

/**
 * Style queue
 */
class Style_Queue {

	/**
	 * Queue
	 *
	 * @var \WP_Styles
	 */
	private $queue;

	/**
	 * Constructor
	 *
	 * @param \WP_Styles $queue The queue to use.
	 */
	public function __construct( $queue ) {
		// Do not create a new WP_Styles object here.
		// Style_Queue_Factory should be the one to do it, as it properly resets the queue.
		$this->queue = $queue;
	}

	/**
	 * Get the queue
	 *
	 * @return \WP_Styles
	 */
	public function get_queue() {
		return $this->queue;
	}

	/**
	 * Process the queue
	 */
	public function process() {
		if ( ! $this->queue || ! $this->queue->registered ) {
			return;
		}
		foreach ( $this->queue->registered as $handle => $style ) {
			// Check if this style has inline CSS data.
			$css = $this->queue->get_data( $handle, 'inline-css' );
			if ( $css ) {
				// Ensure we can't do </style><script>alert('XSS')</script> attacks. Not sure why phpcs is complaining about this.
				echo '<style id="' . esc_attr( $handle . '-css' ) . '">' . wp_strip_all_tags( $css ) . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} else {
				$this->queue->do_item( $handle );
			}
		}
	}
}
