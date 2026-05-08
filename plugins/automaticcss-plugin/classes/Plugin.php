<?php
/**
 * Automatic.css Plugin class file.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS;

use Automatic_CSS\Admin\AdminUIManager;
use Automatic_CSS\CLI\ACSS_CLI;
use Automatic_CSS\CSS_Engine\CSS_Engine;
use Automatic_CSS\Exceptions\Component_Not_Initialized;
use Automatic_CSS\Framework\AssetManager\AssetManager;
use Automatic_CSS\Framework\IntegrationManager\IntegrationsManager;
use Automatic_CSS\Helpers\Container;
use Automatic_CSS\Helpers\DirectoryProtection;
use Automatic_CSS\Helpers\Flag;
use Automatic_CSS\Helpers\FlagsNotInMainFileException;
use Automatic_CSS\Helpers\Logger;
use Automatic_CSS\Helpers\LoggerConfig;
use Automatic_CSS\Helpers\Permissions;
use Automatic_CSS\Helpers\ServiceProvider;
use Automatic_CSS\Helpers\WideEventLogger;
use Automatic_CSS\Helpers\WideEventLoggerInstance;
use Automatic_CSS\Helpers\WordPressOptionsContext;
use Automatic_CSS\Lifecycle\LifecycleManager;
use Automatic_CSS\Lifecycle\UpdateManager;
use Automatic_CSS\Model\Database_Settings;
use Automatic_CSS\Traits\ContainerAwareSingleton;
use Automatic_CSS\UI\Settings_Page\Import_Export;
use Automatic_CSS\UI\Settings_Page\Plugin_Updater;
use Automatic_CSS\UI\Settings_Page\Settings_Page;

/**
 * Plugin class.
 */
class Plugin {

	use ContainerAwareSingleton;

	/**
	 * All of the instances.
	 *
	 * @var array
	 */
	private $components = array();

	/**
	 * Option name for locking the plugin during the database upgrade process.
	 *
	 * @deprecated Use UpdateManager::ACSS_DATABASE_UPGRADE_LOCK_OPTION instead.
	 * @var string
	 */
	public const ACSS_DATABASE_UPGRADE_LOCK_OPTION = 'automaticcss_database_upgrade_lock';

	/**
	 * Option name for locking the plugin during the plugin deletion process.
	 *
	 * @deprecated Use LifecycleManager::ACSS_DATABASE_DELETE_LOCK_OPTION instead.
	 * @var string
	 */
	public const ACSS_DATABASE_DELETE_LOCK_OPTION = 'automaticcss_database_delete_lock';

	/**
	 * Option name for the plugin's database version.
	 *
	 * @deprecated Use UpdateManager::ACSS_DB_VERSION instead.
	 * @var string
	 */
	public const ACSS_DB_VERSION = 'automatic_css_db_version';

	/**
	 * Allowed component keys for the service locator.
	 *
	 * @var array<string>
	 */
	private const ALLOWED_COMPONENT_KEYS = array(
		'framework',
		'settings_page',
		'platforms',
		'permissions',
		'asset_manager',
		'integrations_manager',
	);

	/**
	 * Check if a component is available (initialized and ready to use).
	 *
	 * @param string $key The component key to check.
	 * @return bool True if the component is available, false otherwise.
	 */
	public function has( string $key ): bool {
		if ( ! in_array( $key, self::ALLOWED_COMPONENT_KEYS, true ) ) {
			return false;
		}

		// Check for direct property first (legacy support).
		if ( isset( $this->$key ) ) {
			return true;
		}

		// Check in components array.
		return isset( $this->components[ $key ] );
	}

	/**
	 * Method for getting the instances of other plugin's objects.
	 *
	 * @see https://www.php.net/manual/en/language.oop5.overloading.php#object.get
	 * @param string $key Key.
	 * @return mixed
	 * @throws \Exception If provided key is not allowed.
	 * @throws Component_Not_Initialized If the component has not been initialized yet.
	 */
	public function __get( $key ) {
		if ( ! in_array( $key, self::ALLOWED_COMPONENT_KEYS, true ) ) {
			throw new \Exception( esc_html( "Trying to get a not allowed or not set key {$key} on the Plugin instance" ) );
		}

		// Check for direct property first (legacy support).
		if ( isset( $this->$key ) ) {
			return $this->$key;
		}

		if ( isset( $this->components[ $key ] ) ) {
			return $this->components[ $key ];
		}

		// Fail-fast: throw exception instead of returning null.
		throw new Component_Not_Initialized(
			esc_html(
				sprintf(
					'Component "%s" has not been initialized yet. Check the initialization order in Plugin::init().',
					$key
				)
			)
		);
	}

	/**
	 * Initialize the Plugin.
	 *
	 * @return void
	 */
	public function init() {
		// Initialize the feature flags first (needed for Logger configuration).
		$flag_error_message = ''; // Need to catch an Exception to store the error message because Logger isn't available yet.
		try {
			Flag::init();
			$flag_error_message = '';
		} catch ( FlagsNotInMainFileException $e ) {
			$flag_error_message = $e->getMessage();
		}
		// Initialize logger config.
		$is_logger_enabled = Flag::is_on( 'ENABLE_DEBUG_LOG' );
		$logger_config = new LoggerConfig( $is_logger_enabled, $this );
		Logger::set_config( $logger_config );
		// Start logging.
		Logger::log_header();
		Logger::log( sprintf( '%s: flags: %s', __METHOD__, print_r( Flag::get_flags(), true ) ) );
		if ( ! empty( $flag_error_message ) ) {
			Logger::log( $flag_error_message );
		}
		// Initialize wide events logger for production observability.
		// Use 100% sampling in dev mode (when debug log is on), 1% in production.
		$wide_events_sample_rate = $is_logger_enabled ? 1.0 : 0.01;
		$wide_event_logger = new WideEventLoggerInstance(
			array(
				'sample_rate' => $wide_events_sample_rate,
			)
		);
		$wide_event_logger->init();
		WideEventLogger::set_instance( $wide_event_logger );
		WideEventLogger::set( 'request.uri', Logger::get_redacted_uri() );
		WideEventLogger::set( 'request.method', self::get_request_method() );
		WideEventLogger::set( 'request.trigger', self::get_request_trigger() );
		WideEventLogger::set( 'plugin_version', self::get_plugin_version() );
		register_shutdown_function( array( WideEventLogger::class, 'emit' ) );

		// Register core services in the DI container using ServiceProvider.
		$service_provider = new ServiceProvider( Container::get_instance() );
		$service_provider->register();

		// Initialize lifecycle manager for activation/deactivation hooks.
		$options_context = new WordPressOptionsContext();
		$lifecycle_manager = new LifecycleManager(
			$options_context,
			fn() => Database_Settings::get_instance()
		);
		register_activation_hook( ACSS_PLUGIN_FILE, array( $lifecycle_manager, 'activate' ) );
		register_deactivation_hook( ACSS_PLUGIN_FILE, array( $lifecycle_manager, 'deactivate' ) );

		// Initialize update manager for version upgrades and migrations.
		$update_manager = new UpdateManager( $options_context, self::get_plugin_version() );
		add_action( 'automaticcss_activate_plugin_start', array( $update_manager, 'maybe_update' ) );

		// Run migrations for admin users in any context (admin area or frontend dashboard).
		// Uses 'init' rather than 'admin_init' so migrations complete before the frontend
		// dashboard reads settings during 'wp_head'.
		// Priority 20: Run AFTER plugin integrations (like Frames at priority 11) have
		// registered their UI filters, preventing settings loss during migrations.
		add_action(
			'init',
			function () use ( $update_manager ) {
				if ( is_admin() || current_user_can( Database_Settings::CAPABILITY ) ) {
					$update_manager->maybe_update();
				}
			},
			20
		);

		// Admin-specific hooks.
		if ( is_admin() ) {
			// Initialize admin UI manager for asset enqueueing and plugin links.
			$admin_ui_manager = new AdminUIManager( ACSS_PLUGIN_FILE );
			$admin_ui_manager->init();
		}

		// @since 2.7.1 - MG - trigger the stylesheet regeneration after WP's auto updates too.
		// add_action( 'automatic_updates_complete', array( $update_manager, 'maybe_autoupdate' ) );

		// Register WP-CLI commands.
		ACSS_CLI::register();

		// Initialize components.
		$this->components['model'] = Database_Settings::get_instance()->init();
		$this->components['css_engine'] = CSS_Engine::get_instance()->init();
		$this->components['settings_page'] = Settings_Page::get_instance()->init();
		$this->components['automatic_updater'] = Plugin_Updater::get_instance()->init();
		$this->components['import_export'] = Import_Export::get_instance()->init();
		$this->components['permissions'] = new Permissions();
		// We need this to happen in 'init', otherwise WP doesn't know about the user yet.
		add_action(
			'init',
			function () {
				$this->components['permissions']->init();
			}
		);

		// We need this to happen in 'setup_theme', otherwise it trigger a warning in 'Doing it wrong' because of is_feed.
		add_action(
			'setup_theme',
			function () {
				Logger::log( sprintf( '%s: activating asset management', __METHOD__ ) );
				$this->components['integrations_manager'] = new IntegrationsManager( $this->components['permissions'] );
				$this->components['integrations_manager']->init();
				$this->components['asset_manager'] = new AssetManager( $this->components['integrations_manager'] );
				$this->components['asset_manager']->init();
			}
		);

		// Log flags in use and any that were ignored.
		WideEventLogger::set( 'flags.active', Flag::get_flags() );
		$ignored_flags = Flag::get_ignored_flags();
		if ( ! empty( $ignored_flags ) ) {
			WideEventLogger::set( 'flags.ignored', $ignored_flags );
		}
	}

	/**
	 * Get the plugin's Version
	 *
	 * @return string
	 */
	public static function get_plugin_version() {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugin_data = get_plugin_data( ACSS_PLUGIN_FILE, true, false );
		$version = $plugin_data['Version'];
		return $version;
	}

	/**
	 * Get the plugin's Author
	 *
	 * @return string
	 */
	public static function get_plugin_author() {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugin_data = get_plugin_data( ACSS_PLUGIN_FILE, true, false );
		$author = $plugin_data['Author'];
		return $author;
	}

	/**
	 * Determine what triggered the current request.
	 *
	 * Returns a string identifying the request type:
	 * - 'cli': WP-CLI command
	 * - 'cron': WordPress cron job
	 * - 'rest': REST API request
	 * - 'ajax': AJAX request
	 * - 'xmlrpc': XML-RPC request
	 * - 'web': Regular HTTP/HTTPS request
	 *
	 * @since 3.4.0
	 * @return string
	 */
	public static function get_request_trigger(): string {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return 'cli';
		}
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return 'cron';
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return 'rest';
		}
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return 'ajax';
		}
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return 'xmlrpc';
		}
		return 'web';
	}

	/**
	 * Get the HTTP request method for the current request.
	 *
	 * Returns 'CLI' for WP-CLI commands, otherwise the HTTP method (GET, POST, etc.).
	 *
	 * @since 3.4.0
	 * @return string
	 */
	public static function get_request_method(): string {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return 'CLI';
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- REQUEST_METHOD is safe and doesn't need unslash
		return isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( $_SERVER['REQUEST_METHOD'] ) : 'UNKNOWN';
	}

	/**
	 * Get the directory where we store the dynamic CSS files.
	 * If it doesn't exist, create it and add protection files.
	 *
	 * This was added to support plugins like S3 Offload that alter the uploads_dir.
	 *
	 * @since 2.6.0
	 * @return string
	 */
	public static function get_dynamic_css_dir() {
		$wp_upload_dir = wp_upload_dir();
		$acss_uploads_dir = trailingslashit( $wp_upload_dir['basedir'] ) . 'automatic-css';
		if ( ! file_exists( $acss_uploads_dir ) ) {
			wp_mkdir_p( $acss_uploads_dir );
		}
		// Ensure directory is protected from direct HTTP access.
		self::ensure_directory_protection( $acss_uploads_dir );
		return $acss_uploads_dir;
	}

	/**
	 * Ensure directory is protected from direct HTTP access.
	 *
	 * Creates .htaccess and index.php files if they don't exist.
	 * Safe to call on every request - only creates files if missing.
	 *
	 * @since 3.4.0
	 * @param string $directory Absolute path to the directory to protect.
	 * @return void
	 */
	private static function ensure_directory_protection( string $directory ): void {
		static $protection = null;
		if ( null === $protection ) {
			$protection = new DirectoryProtection();
		}
		$protection->protect( $directory );
	}

	/**
	 * Get the URL where we store the dynamic CSS files.
	 *
	 * This was added to support plugins like S3 Offload that alter the uploads_dir.
	 *
	 * @since 2.6.0
	 * @return string
	 */
	public static function get_dynamic_css_url() {
		$wp_upload_dir = wp_upload_dir();
		return trailingslashit( set_url_scheme( $wp_upload_dir['baseurl'] ) ) . 'automatic-css';
	}
}
