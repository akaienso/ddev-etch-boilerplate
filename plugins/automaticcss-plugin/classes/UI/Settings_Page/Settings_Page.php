<?php
/**
 * Automatic.css Settings_Page UI file.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\UI\Settings_Page;

use Automatic_CSS\Model\Database_Settings;
use Automatic_CSS\Helpers\Locale;
use Automatic_CSS\Traits\ContainerAwareSingleton;

/**
 * Settings_Page UI class.
 */
class Settings_Page {

	use ContainerAwareSingleton;

	/**
	 * Capability needed to operate the plugin
	 *
	 * @var string
	 */
	private $capability = 'manage_options';

	/**
	 * Initialize the Settings_Page class
	 *
	 * @return Settings_Page
	 */
	public function init() {
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_item' ), 500 );
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
			add_filter( 'automaticcss_admin_stylesheets', array( $this, 'enqueue_admin_styles' ) );
			add_filter( 'automaticcss_admin_scripts', array( $this, 'enqueue_admin_scripts' ) );
		}
		return $this;
	}

	/**
	 * Render the plugin's settings page
	 *
	 * @return void
	 */
	public function render() {
		Locale::fix_locale();
		$tab_get = filter_input( INPUT_GET, 'tab' );
		$tab = null === $tab_get ? false : sanitize_text_field( $tab_get );
		?>
		<div class="wrap acss-wrapper">
			<h1>Welcome to the ACSS settings page</h1>

			<nav class="nav-tab-wrapper">
				<a href="?page=automatic-css&tab=welcome" class="nav-tab<?php echo ( false === $tab || 'welcome' === $tab ) ? ' nav-tab-active' : ''; ?>">Welcome</a>
				<a href="?page=automatic-css&tab=license" class="nav-tab<?php echo 'license' === $tab ? ' nav-tab-active' : ''; ?>">License</a>
				<a href="?page=automatic-css&tab=import-export" class="nav-tab<?php echo 'import-export' === $tab ? ' nav-tab-active' : ''; ?>">Import & Export</a>
				<a href="?page=automatic-css&tab=activity-log" class="nav-tab<?php echo 'activity-log' === $tab ? ' nav-tab-active' : ''; ?>">Activity Log</a>
				<a href="?page=automatic-css&tab=support" class="nav-tab<?php echo 'support' === $tab ? ' nav-tab-active' : ''; ?>">Support</a>
				<a href="?page=automatic-css&tab=dashboard" class="nav-tab<?php echo ( false === $tab || 'dashboard' === $tab ) ? ' nav-tab-active' : ''; ?>">Dashboard</a>
			</nav>

			<div class="tab-content">
		<?php
		switch ( $tab ) :
			case 'license':
				$plugin_updater = Plugin_Updater::get_instance();
				$plugin_updater->settings_page();
				break;
			case 'import-export':
				Import_Export::settings_page();
				break;
			case 'activity-log':
				Activity_Log::settings_page();
				break;
			case 'dashboard':
				Dashboard::settings_page();
				break;
			case 'support':
				Support::settings_page();
				break;
			case 'welcome':
			default:
				Welcome::settings_page();
				break;
			endswitch;
		?>
			</div>
		</div> <!-- .acss-wrapper -->
		<?php
		Locale::restore_locale();
	}

	/**
	 * Enqueue admin styles
	 *
	 * @param array $styles The existing styles.
	 * @return array
	 */
	public function enqueue_admin_styles( $styles ) {
		$styles['automaticcss-admin'] = array(
			'url'   => ACSS_CLASSES_URL . '/UI/Settings_Page/css/acss-settings-page.css',
			'version' => filemtime( ACSS_CLASSES_DIR . '/UI/Settings_Page/css/acss-settings-page.css' ),
			'hook'       => array( 'toplevel_page_automatic-css' ),
		);
		return $styles;
	}

	/**
	 * Enqueue admin scripts
	 *
	 * @param array $scripts The existing scripts.
	 * @return array
	 */
	public function enqueue_admin_scripts( $scripts ) {
		return $scripts;
	}

	/**
	 * Add admin bar item
	 *
	 * @param \WP_Admin_Bar $admin_bar The Admin Bar object.
	 * @return void
	 */
	public function add_admin_bar_item( \WP_Admin_Bar $admin_bar ) {
		if ( ! current_user_can( $this->capability ) ) {
			return;
		}
		$model = Database_Settings::get_instance();
		$admin_bar_option = $model->get_var( 'admin-bar-enabled' );
		$admin_bar_enabled = null === $admin_bar_option ? true : ( 'on' === $admin_bar_option ? true : false );
		if ( ! $admin_bar_enabled ) {
			return;
		}
		$admin_bar_url = is_admin() ? home_url( '/?acssOpenDashboard=1' ) : admin_url( 'admin.php?page=automatic-css' );
		$admin_bar->add_menu(
			array(
				'id'    => 'automatic-css-admin-bar',
				'parent' => null,
				'group'  => null,
				'title' => 'Automatic.css', // you can use img tag with image link. it will show the image icon Instead of the title.
				'href'  => $admin_bar_url,
			)
		);
	}

	/**
	 * Add the plugin's settings page to the menu
	 *
	 * @return void
	 */
	public function add_plugin_page() {
		$model = Database_Settings::get_instance();
		$admin_position_option = $model->get_var( 'admin-link-position' );
		$admin_position = null === $admin_position_option ? 90 : $admin_position_option;
		add_menu_page(
			'Automatic CSS', // page_title.
			'Automatic CSS', // menu_title.
			$this->capability, // capability.
			'automatic-css', // menu_slug.
			array( $this, 'render' ), // function.
			'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTYiIGhlaWdodD0iMTYiIHZpZXdCb3g9IjAgMCAxNiAxNiIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGcgY2xpcC1wYXRoPSJ1cmwoI2NsaXAwXzE0NzI5Xzg3NjgyKSI+CjxwYXRoIGQ9Ik0xMi45MjM2IDAuNjE1MjM0QzE0LjYyMyAwLjYxNTEzMiAxNS45OTk3IDEuOTkyOTggMTUuOTk5OCAzLjY5MjM4VjEyLjMwNzZDMTUuOTk5OCAxNC4wMDcgMTQuNjIyMSAxNS4zODQ4IDEyLjkyMjYgMTUuMzg0OEgyLjk2OTUxQzAuNzY2NTQ0IDE1LjM4NDcgLTAuNjY2MzE0IDEzLjA2MDEgMC4zMTQyMzQgMTEuMDgyTDQuMzA3NCAyLjI2NTYzQzQuODA5NDEgMS4yNTU3NCA1LjkyNjggMC42MTUyMzQgNi45NjM2NSAwLjYxNTIzNEM3LjU3MDczIDAuNjE1MjM0IDEwLjU0OTYgMC42MTUzNzcgMTIuOTIzNiAwLjYxNTIzNFpNOC42MDUyNSAzLjIyMDdDNS45MzI1NSAzLjIyMDkgMy45ODU0IDUuMTY4MjggMy45ODUxMyA3Ljk2NDg0QzMuOTg1MTMgMTAuNzQ0IDUuOTMyMzUgMTIuODMyOCA4LjYwNTI1IDEyLjgzM0M5Ljk1MDUgMTIuODMzIDExLjEyMyAxMi4zMTg5IDExLjYxNSAxMC41NTQ3SDExLjgyNUMxMS44MjMgMTAuNTg4NSAxMS43NDM0IDExLjk2MzkgMTEuOTUxOSAxMi42MjIxSDE0LjEzNjVDMTMuNzc1MSAxMC4zODA0IDEzLjc3NTIgMTAuMTA2NSAxMy43NzUyIDguMTI1VjMuMjkxOTlDMTMuNzc1MiAzLjI5MTk5IDEyLjEzMDggMy4yOTI4NyAxMS42ODYzIDMuMjkxOTlDMTEuNjg2MyA0LjA5NDQzIDExLjgwODkgNC45NDcwNCAxMS44MTIzIDQuOTcwN0gxMS42ODYzQzEwLjk2NjcgMy43MzUyMiA5Ljk1MDU4IDMuMjIwNzEgOC42MDUyNSAzLjIyMDdaTTguOTA3MDEgNS4wNzkxQzEwLjUxNzcgNS4wNzkyNyAxMS42MTQ4IDYuMjQ3OTggMTEuNjE1IDcuOTY0ODRDMTEuNjE1IDkuNjY0MTkgMTAuNTE3NyAxMC44NTA1IDguOTA3MDEgMTAuODUwNkM3LjM2NzA0IDEwLjg1MDQgNi4yMzQxNiA5LjYyODc4IDYuMjM0MTYgNy45NjQ4NEM2LjIzNDMxIDYuMzAxMDkgNy4zNjcxNCA1LjA3OTI4IDguOTA3MDEgNS4wNzkxWiIgZmlsbD0iYmxhY2siLz4KPC9nPgo8ZGVmcz4KPGNsaXBQYXRoIGlkPSJjbGlwMF8xNDcyOV84NzY4MiI+CjxyZWN0IHdpZHRoPSIxNiIgaGVpZ2h0PSIxNiIgZmlsbD0id2hpdGUiLz4KPC9jbGlwUGF0aD4KPC9kZWZzPgo8L3N2Zz4K', // icon_url.
			$admin_position // position.
		);
	}
}
