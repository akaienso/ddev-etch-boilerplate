<?php
/**
 * Automatic.css Logger config file.
 * Used for dependency injection into the Logger class, allowing for unit testing.
 *
 * @package Automatic_CSS
 */

declare(strict_types=1);

namespace Automatic_CSS\Helpers;

use Automatic_CSS\Plugin;
use Automatic_CSS\Helpers\Logger;

/**
 * Class LoggerConfig
 */
class LoggerConfig {

	/**
	 * Whether logging is enabled.
	 *
	 * @var boolean
	 */
	public $enabled = false;

	/**
	 * The enabled log levels.
	 *
	 * @var array<int, boolean>
	 */
	public $enabled_log_levels = array();

	/**
	 * The debug file directory.
	 *
	 * @var string
	 */
	public $debug_file_dir;

	/**
	 * The path to the debug file.
	 *
	 * @var string
	 */
	public $debug_file_path;

	/**
	 * The plugin version.
	 *
	 * @var string
	 */
	public $plugin_version;

	/**
	 * Whether to log the trace level.
	 *
	 * @var boolean
	 * Constructor.
	 *
	 * @param boolean $enabled Whether logging is enabled.
	 * @param Plugin  $plugin The plugin instance.
	 */
	public function __construct( $enabled = false, $plugin = null ) {
		$this->enabled = (bool) $enabled;
		$this->enabled_log_levels = array(
			Logger::LOG_LEVEL_NOW => Flag::is_on( 'ENABLE_DEBUG_LEVEL_NOW' ),
			Logger::LOG_LEVEL_ERROR => Flag::is_on( 'ENABLE_DEBUG_LEVEL_ERROR' ),
			Logger::LOG_LEVEL_WARNING => Flag::is_on( 'ENABLE_DEBUG_LEVEL_WARNING' ),
			Logger::LOG_LEVEL_NOTICE => Flag::is_on( 'ENABLE_DEBUG_LEVEL_NOTICE' ),
			Logger::LOG_LEVEL_INFO => Flag::is_on( 'ENABLE_DEBUG_LEVEL_INFO' ),
		);
		$plugin = $plugin ?? Plugin::get_instance();
		$this->debug_file_dir = $plugin->get_dynamic_css_dir();
		$this->debug_file_path = $this->debug_file_dir . '/debug.log';
		$this->plugin_version = $plugin->get_plugin_version();
	}
}
