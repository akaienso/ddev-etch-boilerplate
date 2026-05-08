<?php
/**
 * Plugin Name: UARTF Functionality
 * Description: Site-specific functionality for uartf.org
 * Version:     1.0.0
 * Author:      UARTF
 *
 * Note: EtchWP does not support child themes. Custom PHP code lives here
 * or in wPCodebox2 snippets (preferred for smaller additions).
 */

defined( 'ABSPATH' ) || exit;

define( 'UARTF_FUNC_PATH', plugin_dir_path( __FILE__ ) );
define( 'UARTF_FUNC_URL',  plugin_dir_url( __FILE__ ) );

// ── ACF JSON ──────────────────────────────────────────────────────────────────
// ACF field group JSON is saved here so it can be committed to version control.

add_filter( 'acf/settings/save_json', function () {
	return UARTF_FUNC_PATH . 'acf-json';
} );

add_filter( 'acf/settings/load_json', function ( $paths ) {
	$paths[] = UARTF_FUNC_PATH . 'acf-json';
	return $paths;
} );

// ── Includes ──────────────────────────────────────────────────────────────────

foreach ( glob( UARTF_FUNC_PATH . 'includes/*.php' ) as $file ) {
	require_once $file;
}
