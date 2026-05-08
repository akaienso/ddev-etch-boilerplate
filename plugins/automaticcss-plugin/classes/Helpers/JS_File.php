<?php
/**
 * JS_File class.
 *
 * @package Automatic_CSS\Helpers
 */

namespace Automatic_CSS\Helpers;

/**
 * JS_File class.
 */
class JS_File {

	/**
	 * The filename of the JS file.
	 *
	 * @var string
	 */
	private $filename;

	/**
	 * The handle of the JS file.
	 *
	 * @var string
	 */
	private $handle;

	/**
	 * The URL of the JS file.
	 *
	 * @var string
	 */
	private $url;

	/**
	 * The path of the JS file.
	 *
	 * @var string
	 */
	private $path;

	/**
	 * The version of the JS file.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * The dependencies of the JS file.
	 *
	 * @var array
	 */
	private $dependencies;

	/**
	 * Whether the JS file should be enqueued in the footer.
	 *
	 * @var boolean
	 */
	private $in_footer;

	/**
	 * The key to localize the JS file.
	 *
	 * @var string
	 */
	private $localize_key;

	/**
	 * The data to localize the JS file.
	 *
	 * @var array
	 */
	private $localize_data;

	/**
	 * Constructor.
	 *
	 * @param string  $filename The filename of the JS file.
	 * @param string  $url The URL of the JS file.
	 * @param string  $path The path of the JS file.
	 * @param array   $dependencies The dependencies of the JS file.
	 * @param boolean $in_footer Whether the JS file should be enqueued in the footer.
	 * @param string  $localize_key The key to localize the JS file.
	 * @param array   $localize_data The data to localize the JS file.
	 */
	public function __construct( $filename, $url, $path = null, $dependencies = array(), $in_footer = true, $localize_key = null, $localize_data = array() ) {
		$this->filename = $filename;
		$this->handle = 'acss-' . $filename;
		$this->url = $url;
		$this->path = $path;
		$this->dependencies = $dependencies;
		$this->in_footer = $in_footer;
		$this->version = null !== $path && file_exists( $path ) ? filemtime( $path ) : null;
		$this->set_localize( $localize_key, $localize_data );
	}

	/**
	 * Enqueue the JS file.
	 */
	public function enqueue() {
		wp_enqueue_script( $this->handle, $this->url, $this->dependencies, $this->version, $this->in_footer );
	}

	/**
	 * Localize the JS file.
	 *
	 * @param string $object_name The name of the object to localize.
	 * @param array  $data The data to localize.
	 */
	public function localize( $object_name = null, $data = null ) {
		$object_name = $object_name ?? $this->localize_key;
		$data = $data ?? $this->localize_data;
		if ( null === $object_name || null === $data ) {
			return;
		}
		wp_localize_script( $this->handle, $object_name, $data );
	}

	/**
	 * Set the localize key and data.
	 *
	 * @param string $localize_key The key to localize the JS file.
	 * @param array  $localize_data The data to localize the JS file.
	 */
	public function set_localize( $localize_key, $localize_data ) {
		$this->localize_key = $localize_key;
		$this->localize_data = $localize_data;
	}

	/**
	 * Magic getter for read-only access to certain properties.
	 *
	 * @param string $key Key to search for.
	 * @return mixed
	 * @throws \Exception If provided key is not allowed.
	 */
	public function __get( $key ) {
		$allowed_keys = array( 'handle', 'filename' );
		if ( in_array( $key, $allowed_keys, true ) ) {
			return $this->$key;
		}
		throw new \Exception( esc_html( "Trying to get a not allowed key {$key} on a JS_File instance" ) );
	}
}
