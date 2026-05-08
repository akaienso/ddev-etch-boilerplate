<?php
/**
 * Automatic.css WordPress helper file.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Helpers;

use Automatic_CSS\Helpers\Logger;

/**
 * Automatic.css WordPress helper class
 */
class WordPress {
	/**
	 * A helper function to enqueue a stylesheet.
	 *
	 * @param string $handle The stylesheet handle.
	 * @param array  $options The options (items: 'filename', 'url', 'dependency', optional 'filename-min', 'url-min').
	 * @return void
	 */
	public static function enqueue_stylesheet_helper( $handle, $options ) {
		// 20211115 - MG - by default enqueue the non minified version.
		// Only enqueue the minified version if SCRIPT_DEBUG is set.
		$url = $options['url'];
		$filename = realpath( $options['filename'] );
		$dependency = isset( $options['dependency'] ) ? $options['dependency'] : array();
		if ( array_key_exists( 'filename-min', $options ) && array_key_exists( 'url-min', $options ) && ( ! defined( 'SCRIPT_DEBUG' ) || true !== (bool) SCRIPT_DEBUG ) ) {
			$url = $options['url-min'];
			$filename = realpath( $options['filename-min'] );
		}
		// 20211125 - MG - enqueue the file only if it exists. Log an error otherwise.
		if ( ! file_exists( $filename ) ) {
			Logger::log(
				sprintf(
					'%s: could not enqueue the following stylesheet because the file does not exist: %s',
					__METHOD__,
					$filename
				),
				true
			);
			return;
		}
		wp_enqueue_style(
			$handle,
			$url,
			$dependency,
			strval( filemtime( $filename ) ),
			'all'
		);
	}

	/**
	 * Whether we're on the WordPress frontend.
	 *
	 * @return boolean
	 */
	public static function is_wp_frontend() {
		// First check if this is a system request.
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$excluded_paths = array(
			'/.well-known/',
			'/favicon.ico',
			'/robots.txt',
			'/sitemap.xml',
			'/wp-json/',
			'/wp-cron.php',
			'/wp-includes/',
			'/wp-admin/',
		);

		foreach ( $excluded_paths as $path ) {
			if ( strpos( $request_uri, $path ) === 0 ) {
				return false;
			}
		}
		return ! is_admin() && ! wp_doing_ajax() && ! wp_is_json_request();
		/**
		 * TODO: Figure out why this implementation fails the AssetManagerTest::test_detects_bricks_builder_context_with_bricks_run_parameter() test.
		 * return ! \is_admin() &&
		 * ! \is_robots() &&
		 * ! \is_favicon() &&
		 * ! \is_feed() &&
		 * ! \is_trackback() &&
		 * ! \is_embed() &&
		 * (
		 * \is_front_page() ||
		 * \is_home() ||
		 * \is_singular() ||
		 * \is_archive() ||
		 * \is_search() ||
		 * \is_404()
		 * );
		 */
	}

	/**
	 * Check if we are in local environment or not
	 *
	 * @return bool
	 */
	public static function is_local_environment() {
		return 'local' === wp_get_environment_type();
	}

	/**
	 * Check if the server require HTTP Auth.
	 *
	 * @return bool
	 */
	public static function is_http_auth(): bool {
		return isset( $_SERVER['PHP_AUTH_USER'] )
			|| isset( $_SERVER['HTTP_AUTHORIZATION'] )
			|| isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] )
			|| ! empty( $_SERVER['AUTH_TYPE'] );
	}
}
