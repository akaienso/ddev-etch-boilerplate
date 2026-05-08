<?php
/**
 * CSS_File class - Complete wrapper around WP_Styles for CSS file enqueuing.
 *
 * @package Automatic_CSS\Helpers
 */

namespace Automatic_CSS\Helpers;

/**
 * CSS_File class.
 *
 * A complete wrapper around WP_Styles that handles all stylesheet operations:
 * enqueue, dequeue, register, deregister, inline styles, and processing.
 *
 * Subclasses can override protected hook methods for custom behavior:
 * - should_proceed(): Control whether operations should execute
 * - get_file_url(): Provide dynamic URL computation
 * - get_file_path(): Provide dynamic path computation
 */
class CSS_File {

	/**
	 * The handle of the CSS file.
	 *
	 * @var string
	 */
	protected $handle;

	/**
	 * The URL of the CSS file.
	 *
	 * @var string
	 */
	protected $file_url;

	/**
	 * The path of the CSS file.
	 *
	 * @var string
	 */
	protected $file_path;

	/**
	 * The dependencies of the CSS file.
	 *
	 * @var array
	 */
	protected $deps;

	/**
	 * The media of the CSS file.
	 *
	 * @var string
	 */
	protected $media;

	/**
	 * The style queue.
	 *
	 * @var \WP_Styles
	 */
	protected $style_queue;

	/**
	 * Is this stylesheet registered?
	 *
	 * @var bool
	 */
	protected $is_registered;

	/**
	 * Is this stylesheet enqueued?
	 *
	 * @var bool
	 */
	protected $is_enqueued;

	/**
	 * Constructor.
	 *
	 * @param string     $handle The handle of the CSS file.
	 * @param string     $url The URL of the CSS file.
	 * @param string     $path The path of the CSS file.
	 * @param array      $deps The dependencies of the CSS file.
	 * @param string     $media The media of the CSS file.
	 * @param \WP_Styles $style_queue The style queue.
	 */
	public function __construct( $handle, $url, $path, $deps = array(), $media = 'all', $style_queue = null ) {
		$this->handle = $handle;
		$this->file_url = $url;
		$this->file_path = $path;
		$this->deps = $deps;
		$this->media = $media;
		$this->style_queue = $style_queue;
	}

	// =========================================================================
	// PROTECTED HOOKS - Override in subclasses for custom behavior
	// =========================================================================

	/**
	 * Check if operations should proceed.
	 *
	 * Override in subclasses to add conditions (e.g., is_enabled check).
	 *
	 * @return bool
	 */
	protected function should_proceed(): bool {
		return true;
	}

	/**
	 * Get the file URL.
	 *
	 * Override in subclasses for dynamic URL computation.
	 *
	 * @return string
	 */
	protected function get_file_url(): string {
		return $this->file_url;
	}

	/**
	 * Get the file path.
	 *
	 * Override in subclasses for dynamic path computation.
	 *
	 * @return string
	 */
	protected function get_file_path(): string {
		return $this->file_path;
	}

	// =========================================================================
	// ENQUEUING METHODS - Wrapping WP_Styles
	// =========================================================================

	/**
	 * Enqueue this CSS file
	 *
	 * @return void
	 * @see https://developer.wordpress.org/reference/classes/wp_dependencies/enqueue/
	 */
	public function enqueue_stylesheet() {
		if ( ! $this->should_proceed() ) {
			return;
		}
		if ( $this->is_file_empty() ) {
			return;
		}
		$this->maybe_set_default_queue();
		if ( ! $this->is_registered ) {
			$this->register_stylesheet();
		}
		Logger::log( sprintf( '%s: enqueuing stylesheet %s', __METHOD__, $this->handle ) );
		$this->style_queue->enqueue( $this->handle );
		$this->is_enqueued = $this->style_queue->query( $this->handle, 'enqueued' );
	}

	/**
	 * Dequeue this CSS file
	 *
	 * @return void
	 * @see https://developer.wordpress.org/reference/classes/wp_dependencies/dequeue/
	 */
	public function dequeue_stylesheet() {
		if ( ! $this->should_proceed() ) {
			return;
		}
		$this->maybe_set_default_queue();
		Logger::log( sprintf( '%s: dequeuing stylesheet %s', __METHOD__, $this->handle ) );
		$this->style_queue->dequeue( $this->handle );
		$this->is_enqueued = $this->style_queue->query( $this->handle, 'enqueued' );
	}

	/**
	 * Register this CSS file as a stylesheet in $style_queue
	 *
	 * @return bool
	 * @see https://developer.wordpress.org/reference/classes/wp_dependencies/add/
	 */
	public function register_stylesheet() {
		if ( ! $this->should_proceed() ) {
			return false;
		}
		$this->maybe_set_default_queue();
		Logger::log( sprintf( '%s: registering stylesheet %s', __METHOD__, $this->handle ) );
		$file_path = $this->get_file_path();
		$is_file_exists_check_required = apply_filters( 'acss/css_engine/file_exists_check_required', true, $file_path, $this->get_file_url() );
		if ( $is_file_exists_check_required && ! $this->file_exists() ) {
			Logger::log( sprintf( '%s: CSS file %s does not exist and cannot be registered', __METHOD__, $file_path ), Logger::LOG_LEVEL_ERROR );
			return false;
		}
		$ret = $this->style_queue->add(
			$this->handle,
			$this->get_file_url(),
			$this->deps,
			filemtime( $file_path ),
			$this->media
		);
		$this->is_registered = $this->style_queue->query( $this->handle, 'registered' );
		return $ret;
	}

	/**
	 * Deregister this CSS file from $style_queue
	 *
	 * @return void
	 * @see https://developer.wordpress.org/reference/classes/wp_dependencies/remove/
	 */
	public function deregister_stylesheet() {
		if ( ! $this->should_proceed() ) {
			return;
		}
		$this->maybe_set_default_queue();
		Logger::log( sprintf( '%s: deregistering stylesheet %s', __METHOD__, $this->handle ) );
		$this->style_queue->remove( $this->handle );
		$this->is_registered = $this->style_queue->query( $this->handle, 'registered' );
	}

	/**
	 * Enqueue the CSS file inline.
	 *
	 * @param string $handle The handle to use for the inline style.
	 * @return void
	 */
	public function enqueue_inline( $handle = '' ) {
		if ( ! $this->should_proceed() ) {
			return;
		}
		$file_path = $this->get_file_path();
		if ( ! file_exists( $file_path ) ) {
			Logger::log( sprintf( '%s: CSS file %s does not exist and cannot be enqueued inline', __METHOD__, $file_path ), Logger::LOG_LEVEL_ERROR );
			return;
		}
		if ( $this->is_file_empty() ) {
			return;
		}
		$this->maybe_set_default_queue();
		$handle = '' === $handle ? $this->handle : $handle;
		$css = file_get_contents( $file_path );
		// WordPress's add_inline_style() has a limitation: it forces inline styles to appear AFTER their target stylesheet.
		// To inline before a registered handle, you need to register and enqueue a fake stylesheet first.
		// This is a hacky workaround, and the side effect is an extra HTTP request for a stylesheet you don't need.
		// So, we use a custom approach:
		// 1. The CSS_File sends the custom CSS to the queue with a custom data key.
		// 2. The Style_Queue processes it by outputting a <style> tag directly.
		// 3. The Integrations enqueuing the stylesheets control the order of the stylesheets (first enqueued, first served).
		$this->style_queue->add( $handle, '' ); // Breaks if you don't register the handle first.
		$this->style_queue->add_data( $handle, 'inline-css', $css );
	}

	/**
	 * Enqueue this CSS file via add_editor_style
	 *
	 * @param string $local_path The local path to the CSS file. If empty, the file_url will be used.
	 * @return void
	 */
	public function enqueue_stylesheet_via_add_editor_style( $local_path = '' ) {
		if ( ! $this->should_proceed() ) {
			return;
		}
		$style_path = ! empty( $local_path ) ? $local_path . $this->get_filename() : $this->get_file_url();
		WideEventLogger::append( 'enqueue_assets.gutenberg.add_editor_style_enqueues', $this->handle );
		add_editor_style( $style_path );
	}

	/**
	 * Process this stylesheet
	 *
	 * @return mixed
	 */
	public function process_stylesheet() {
		if ( ! $this->should_proceed() ) {
			return;
		}
		$this->maybe_set_default_queue();
		return $this->style_queue->do_item( $this->handle );
	}

	/**
	 * Process all stylesheets in $this->style_queue
	 *
	 * @return mixed
	 */
	public function process_stylesheets() {
		if ( ! $this->should_proceed() ) {
			return;
		}
		$this->maybe_set_default_queue();
		return $this->style_queue->do_items();
	}

	// =========================================================================
	// QUEUE MANAGEMENT
	// =========================================================================

	/**
	 * Change the stylesheet's queue, if it was not registered yet.
	 *
	 * @param \WP_Styles $queue The new queue.
	 *
	 * @return \WP_Styles|null
	 */
	public function set_queue( \WP_Styles $queue ) {
		if ( ! $this->should_proceed() ) {
			return null;
		}
		Logger::log( sprintf( '%s: setting new queue for stylesheet %s', __METHOD__, $this->handle ) );
		if ( $this->is_registered ) {
			Logger::log( sprintf( '%s: trying to change queue of registered stylesheet %s', __METHOD__, $this->handle ) );
			$this->deregister_stylesheet();
		}
		$this->style_queue = $queue;
		return $this->style_queue;
	}

	/**
	 * Set the default queue if it was not set yet.
	 *
	 * @return \WP_Styles|null
	 */
	protected function maybe_set_default_queue() {
		if ( null === $this->style_queue ) {
			$container = Container::get_instance();
			$style_queue = $container->has( 'style_queue' )
				? $container->get( 'style_queue' )
				: Style_Queue_Factory::get_instance()->get_default_queue();

			Logger::log( sprintf( '%s: setting default queue for stylesheet %s', __METHOD__, $this->handle ) );
			return $this->set_queue( $style_queue->get_queue() );
		}
		return null;
	}

	// =========================================================================
	// FILE CHECKS
	// =========================================================================

	/**
	 * Check if CSS file exists.
	 *
	 * @return bool
	 */
	public function file_exists() {
		return file_exists( $this->get_file_path() );
	}

	/**
	 * Check if the file is empty
	 *
	 * @return bool
	 */
	public function is_file_empty() {
		if ( ! $this->file_exists() ) {
			return true;
		}

		if ( '' == file_get_contents( $this->get_file_path() ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get the filename from the path.
	 *
	 * @return string
	 */
	protected function get_filename(): string {
		return basename( $this->get_file_path() );
	}

	/**
	 * Magic getter for read-only access to certain properties.
	 *
	 * @param string $key Key to search for.
	 * @return mixed
	 * @throws \Exception If provided key is not allowed.
	 */
	public function __get( $key ) {
		$allowed_keys = array( 'handle' );
		if ( in_array( $key, $allowed_keys, true ) ) {
			return $this->$key;
		}
		throw new \Exception( esc_html( "Trying to get a not allowed key {$key} on a CSS_File instance" ) );
	}
}
