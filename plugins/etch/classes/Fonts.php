<?php
/**
 * SVG handling class.
 *
 * @package Etch
 */

namespace Etch;

use Etch\Services\SettingsService;

/**
 * Fonts class.
 */
class Fonts {

	/**
	 * Setup the hooks.
	 *
	 * @return void
	 */
	public static function setup_hooks() {
		$settings_service = SettingsService::get_instance();
		if ( ! $settings_service->get_setting( 'allow_font_uploads' ) ) {
			return;
		}

		add_filter( 'upload_mimes', array( __CLASS__, 'add_fonts_to_upload_mimes' ) );
		add_filter( 'wp_check_filetype_and_ext', array( __CLASS__, 'check_font_filetype' ), 10, 4 );
	}

	/**
	 * Allow font uploads for admins
	 *
	 * @param array<string,string> $mimes Mime types keyed by the file extension regex corresponding to those types.
	 * @return array<string,string>
	 */
	public static function add_fonts_to_upload_mimes( $mimes ) {
		if ( current_user_can( 'manage_options' ) ) {
			$mimes['woff'] = 'application/x-font-woff';
			$mimes['woff2'] = 'font/woff2';
			$mimes['ttf'] = 'application/x-font-ttf';
			$mimes['otf'] = 'application/x-font-otf';
			$mimes['eot'] = 'application/vnd.ms-fontobject';
		}

		return $mimes;
	}

	/**
	 * Check the file type and extension for font uploads.
	 *
	 * @param array<string,string> $data File data.
	 * @param string               $file File path.
	 * @param string               $filename File name.
	 * @param array<string,string> $mimes Mime types keyed by the file extension regex corresponding to those types.
	 * @return array<string,string>
	 */
	public static function check_font_filetype( $data, $file, $filename, $mimes ) {
		  // If WordPress already accepted it, don’t interfere
		if ( ! empty( $data['ext'] ) && ! empty( $data['type'] ) ) {
			return $data;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return $data;
		}

		$filetype = wp_check_filetype( $filename, $mimes );

		if ( in_array( $filetype['ext'], array( 'woff', 'woff2', 'ttf', 'otf', 'eot' ), true ) && false !== $filetype['type'] && false !== $filetype['ext'] ) {
			$data['ext']  = $filetype['ext'];
			$data['type'] = $filetype['type'];
		}

		return $data;
	}
}
