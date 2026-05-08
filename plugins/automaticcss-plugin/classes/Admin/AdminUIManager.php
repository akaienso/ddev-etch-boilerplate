<?php
/**
 * Automatic.css Admin UI Manager class file.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Admin;

/**
 * Manages admin UI elements: asset enqueueing, plugin action links, and plugin row meta.
 *
 * @since 4.0.0
 */
class AdminUIManager {

	/**
	 * The main plugin file path.
	 *
	 * @var string
	 */
	private string $plugin_file;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_file The main plugin file path.
	 */
	public function __construct( string $plugin_file ) {
		$this->plugin_file = $plugin_file;
	}

	/**
	 * Initialize the admin UI manager by registering WordPress hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( $this->plugin_file ), array( $this, 'add_action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
	}

	/**
	 * Enqueue admin scripts & styles.
	 *
	 * @param string $hook The current admin page.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		$stylesheets = apply_filters( 'automaticcss_admin_stylesheets', array() );
		foreach ( $stylesheets as $stylesheet => $options ) {
			if (
				! array_key_exists( 'hook', $options )
				|| ( is_string( $options['hook'] ) && $hook === $options['hook'] )
				|| ( is_array( $options['hook'] ) && in_array( $hook, $options['hook'], true ) )
			) {
				$file = isset( $options['filename'] ) ? ACSS_ASSETS_URL . $options['filename'] : $options['url'];
				$version = isset( $options['filename'] ) ? strval( filemtime( ACSS_ASSETS_DIR . $options['filename'] ) ) : $options['version'];
				$dependency = isset( $options['dependency'] ) ? $options['dependency'] : array();
				wp_enqueue_style(
					$stylesheet,
					$file,
					$dependency,
					$version,
					'all'
				);
			}
		}
		$scripts = apply_filters( 'automaticcss_admin_scripts', array() );
		foreach ( $scripts as $script => $options ) {
			if (
				! array_key_exists( 'hook', $options )
				|| ( is_string( $options['hook'] ) && $hook === $options['hook'] )
				|| ( is_array( $options['hook'] ) && in_array( $hook, $options['hook'], true ) )
			) {
				$file = isset( $options['filename'] ) ? ACSS_ASSETS_URL . $options['filename'] : $options['url'];
				$version = isset( $options['filename'] ) ? strval( filemtime( ACSS_ASSETS_DIR . $options['filename'] ) ) : $options['version'];
				$dependency = isset( $options['dependency'] ) ? $options['dependency'] : array();
				wp_enqueue_script(
					$script,
					$file,
					$dependency,
					$version,
					true
				);
				if ( ! empty( $options['localize'] ) && ! empty( $options['localize']['name'] ) && ! empty( $options['localize']['options'] ) ) {
					wp_localize_script( $script, $options['localize']['name'], $options['localize']['options'] );
				}
			}
		}
	}

	/**
	 * Add action links to the plugin's row in the plugins list.
	 *
	 * @see https://codex.wordpress.org/Plugin_API/Filter_Reference/plugin_action_links_(plugin_file_name)
	 * @param array<string> $actions The current links.
	 * @return array<string> The links with the new ones added.
	 */
	public function add_action_links( array $actions ): array {
		$links = array(
			'<a href="' . admin_url( 'admin.php?page=automatic-css' ) . '">Settings</a>',
			'<a href="' . admin_url( 'admin.php?page=automatic-css&tab=license' ) . '">License</a>',
		);
		return array_merge( $links, $actions );
	}

	/**
	 * Add links to the plugin's row in the plugins list.
	 *
	 * @param array<string> $plugin_meta The current links.
	 * @param string        $plugin_file The plugin file.
	 * @return array<string> The links with the new ones added.
	 */
	public function plugin_row_meta( array $plugin_meta, string $plugin_file ): array {
		$acss_plugin_file = plugin_basename( $this->plugin_file );
		if ( $plugin_file === $acss_plugin_file ) {
			$links = array(
				'guide'   => '<a href="https://automaticcss.com/docs/" target="_blank">User Guide</a>',
				'support' => '<a href="https://community.automaticcss.com/" target="_blank">Support</a>',
				'faq'     => '<a href="https://community.automaticcss.com/c/faqs/" target="_blank">FAQs</a>',
			);
			$plugin_meta = array_merge( $plugin_meta, $links );
		}
		return $plugin_meta;
	}
}
