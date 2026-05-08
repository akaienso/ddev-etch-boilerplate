<?php
/**
 * Automatic.css Expansions config PHP file.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Model\Config;

use Automatic_CSS\Exceptions\Missing_ExpandsTo_Key;
use Automatic_CSS\Helpers\Logger;
use Automatic_CSS\Model\Database_Settings;
use Automatic_CSS\Model\SettingsRepositoryInterface;

/**
 * Automatic.css Expansions config class.
 */
final class Expansions {

	/**
	 * Stores the config file
	 *
	 * @var mixed
	 */
	private $config;

	/**
	 * The settings repository for accessing plugin settings.
	 *
	 * @var SettingsRepositoryInterface
	 */
	private $settings;

	/**
	 * Cache the expansions for repeat calls
	 *
	 * @var array
	 */
	private $expansions;

	/**
	 * Constructor.
	 *
	 * @param Config_Contents|null             $config_dir_or_contents The config directory or the config contents.
	 * @param SettingsRepositoryInterface|null $settings The settings repository for plugin settings.
	 */
	public function __construct( ?Config_Contents $config_dir_or_contents = null, ?SettingsRepositoryInterface $settings = null ) {
		$this->config = $config_dir_or_contents ?? new Config_Contents( 'utility-expansions/all-expansions.json' );
		$this->settings = $settings ?? Database_Settings::get_instance();
	}

	/**
	 * Load the 'tabs' item from the ui.json file.
	 *
	 * @return array
	 * @throws \Exception If it can't load the file or it doesn't have the right structure.
	 * @throws \Automatic_CSS\Exceptions\NoExpansionsDefined If the expansions file is empty.
	 * @throws \Automatic_CSS\Exceptions\Missing_ExpandsTo_Key If the expansion file is missing the 'expandTo' key.
	 */
	public function load() {
		// STEP: load the file and check if it has the right structure.
		$contents = $this->config->load(); // contents stored in $contents.
		if ( ! is_array( $contents['expansions'] ) || empty( $contents['expansions'] ) ) {
			throw new \Automatic_CSS\Exceptions\NoExpansionsDefined( esc_html( 'The Expansions config file has an empty or non-array "expansions" key.' ) );
		}
		// STEP: iterate the expansions and load their content.
		Logger::log( sprintf( '%s: loading expansions', __METHOD__ ), Logger::LOG_LEVEL_NOTICE );
		$contents['content'] = array();
		foreach ( $contents['expansions'] as $expansion ) {
			Logger::log( sprintf( '%s: loading expansion %s', __METHOD__, $expansion ), Logger::LOG_LEVEL_NOTICE );
			$expansion_json = ( new Expansion( $expansion ) )->load();
			if ( ! is_array( $expansion_json ) || empty( $expansion_json ) ) {
				throw new \Exception( esc_html( sprintf( 'The Expansion config file "%s" has an empty or non-array "content" key.', $expansion ) ) );
			}
			if ( ! isset( $expansion_json['expansions'] ) ) {
				throw new \Exception( esc_html( sprintf( 'The Expansion config file "%s" is missing the "expansions" key.', $expansion ) ) );
			}
			foreach ( $expansion_json['expansions'] as $expansion_name => $expansion_data ) {
				if ( ! is_array( $expansion_data ) || empty( $expansion_data ) ) {
					throw new Missing_ExpandsTo_Key( esc_html( sprintf( 'The Expansion config file "%s" has an empty or non-array "expansions" key.', $expansion_name ) ) );
				}
				if ( ! isset( $expansion_data['expandTo'] ) ) {
					throw new \Exception( esc_html( sprintf( 'The Expansion config file "%s" is missing the "expandTo" key.', $expansion_name ) ) );
				}

				$expandTo = $expansion_data['expandTo'];

				// STEP: apply the wrapIn if exists.
				if ( isset( $expansion_data['wrapIn'] ) ) {
					$wrapperName = $expansion_data['wrapIn'];

					if ( ! isset( $expansion_json['wrappers'][ $wrapperName ] ) ) {
						throw new \Exception( esc_html( sprintf( 'The Expansion config file "%s" is missing the "wrapper" key "%s".', $expansion_name, $wrapperName ) ) );
					}

					$wrapper = $expansion_json['wrappers'][ $wrapperName ];
					$expandTo = str_replace( '@slot@', $expandTo, $wrapper );
				}

				// STEP: replace all [breakpoint-*] occurrences if exists.
				preg_match_all( '/\[(breakpoint-.+?)\]/m', $expandTo, $breakpoints_matches );
				if ( ! empty( $breakpoints_matches[1] ) > 0 ) {
					foreach ( $breakpoints_matches[1] as $breakpoint ) {
						$breakpoint_value = $this->settings->get_var( $breakpoint );
						if ( ! empty( $breakpoint_value ) ) {
							$string_to_find = "[$breakpoint]";
							$string_to_replace = $breakpoint_value;
							$expandTo = str_replace( $string_to_find, $string_to_replace, $expandTo );
						}
					}
				}

				$contents['content'][ $expansion_name ] = $expandTo;
			}
		}
		Logger::log( sprintf( '%s: expansion contents: %s', __METHOD__, print_r( $contents, true ) ), Logger::LOG_LEVEL_INFO );
		$contents = apply_filters( "acss/config/{$this->config->get_filename()}/after_load", $contents );
		return $contents['content'];
	}

	/**
	 * Get all the expansions.
	 *
	 * @return array
	 */
	public function get_all_expansions() {
		if ( ! isset( $this->expansions ) ) {
			$this->expansions = $this->load();
		}
		return $this->expansions ?? array();
	}
}
