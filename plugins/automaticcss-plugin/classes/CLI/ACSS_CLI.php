<?php
/**
 * Automatic.css WP-CLI command registration.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\CLI;

/**
 * Main CLI class for registering WP-CLI commands.
 *
 * This class handles the registration of all Automatic.css CLI commands.
 * Commands are only registered when WP-CLI is available.
 */
class ACSS_CLI {

	/**
	 * Register all CLI commands.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! class_exists( 'WP_CLI' ) ) {
			return;
		}

		\WP_CLI::add_command(
			'acss settings',
			Settings_Command::class,
			array(
				'shortdesc' => 'Manage Automatic.css settings.',
				'longdesc'  => 'View, update, export, import, and reset Automatic.css settings from the command line.',
			)
		);

		\WP_CLI::add_command(
			'acss css',
			CSS_Command::class,
			array(
				'shortdesc' => 'Manage Automatic.css stylesheets.',
				'longdesc'  => 'Regenerate Automatic.css stylesheets from the command line.',
			)
		);

		\WP_CLI::add_command(
			'acss status',
			Status_Command::class,
			array(
				'shortdesc' => 'Display Automatic.css plugin status.',
				'longdesc'  => 'Show plugin version, active integrations, CSS files, settings count, and feature flags.',
			)
		);

		\WP_CLI::add_command(
			'acss logs',
			Logs_Command::class,
			array(
				'shortdesc' => 'Manage Automatic.css logs.',
				'longdesc'  => 'View and manage activity and debug log files.',
			)
		);

		\WP_CLI::add_command(
			'acss doctor',
			Doctor_Command::class,
			array(
				'shortdesc' => 'Run diagnostic checks on the plugin.',
				'longdesc'  => 'Check environment, file system, configuration, and CSS files for issues.',
			)
		);

		\WP_CLI::add_command(
			'acss flags',
			Flags_Command::class,
			array(
				'shortdesc' => 'Manage feature flags.',
				'longdesc'  => 'List, get, set, and unset feature flags via flags.user.json.',
			)
		);
	}
}
