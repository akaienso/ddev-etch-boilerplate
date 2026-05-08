<?php
/**
 * Plugin Name: Custom Site Functionality
 * Description: Site-specific functionality. Custom PHP code lives here or in wPCodebox2 snippets (preferred for smaller additions). Note: EtchWP does not support child themes.
 * Version:     1.0.0
 */

defined( 'ABSPATH' ) || exit;

define( 'CSF_PATH', plugin_dir_path( __FILE__ ) );
define( 'CSF_URL',  plugin_dir_url( __FILE__ ) );

// ── ACF JSON ──────────────────────────────────────────────────────────────────
// ACF field group JSON is saved here so it can be committed to version control.

add_filter( 'acf/settings/save_json', function () {
	return CSF_PATH . 'acf-json';
} );

add_filter( 'acf/settings/load_json', function ( $paths ) {
	$paths[] = CSF_PATH . 'acf-json';
	return $paths;
} );

// ── Includes ──────────────────────────────────────────────────────────────────

foreach ( glob( CSF_PATH . 'includes/*.php' ) as $file ) {
	require_once $file;
}
