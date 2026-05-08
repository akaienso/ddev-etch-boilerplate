<?php
/**
 * Automatic.css Bricks_Globals_Sync class file.
 *
 * Syncs ACSS classes and colors to Bricks builder globals for autocomplete.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Features\Bricks_Globals_Sync;

use Automatic_CSS\Framework\IntegrationManager\IntegrationInterface;
use Automatic_CSS\Framework\Integrations\Bricks;
use Automatic_CSS\Helpers\Logger;
use Automatic_CSS\Model\Config\Framework;
use Automatic_CSS\Model\Database_Settings;

/**
 * Bricks_Globals_Sync class.
 *
 * Manages syncing ACSS classes and color palettes to Bricks global options
 * for autocomplete functionality in the builder.
 */
class Bricks_Globals_Sync implements IntegrationInterface {

	/**
	 * Used to namespace the global classes array.
	 */
	const CLASS_IMPORT_ID_PREFIX = 'acss_import_';

	/**
	 * Used to namespace the color palette array.
	 */
	const PALETTE_IMPORT_ID_PREFIX = 'acss_import_';

	/**
	 * Name prefix for color palettes.
	 */
	const PALETTE_IMPORT_NAME_PREFIX = 'ACSS ';

	/**
	 * The class category ID.
	 */
	const CLASS_CATEGORY_ID = 'acss';

	/**
	 * Constructor.
	 *
	 * Registers lifecycle hooks for syncing globals.
	 */
	public function __construct() {
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'automaticcss_activate_plugin_end', array( $this, 'sync_globals' ) );
		add_action( 'automaticcss_update_plugin_end', array( $this, 'sync_globals' ) );
		add_action( 'automaticcss_deactivate_plugin_start', array( $this, 'delete_globals' ) );
		add_action( 'automaticcss_settings_after_save', array( $this, 'after_save_settings' ) );
	}

	/**
	 * Whether the integration is active.
	 *
	 * @return boolean
	 */
	public static function is_active() {
		return Bricks::is_active();
	}

	/**
	 * Get the name of the integration.
	 *
	 * @return string
	 */
	public static function get_name() {
		return 'bricks-globals-sync';
	}

	/**
	 * Handle settings save event.
	 *
	 * Refreshes globals if the option to remove deactivated classes is enabled.
	 *
	 * @return void
	 */
	public function after_save_settings() {
		Logger::log( sprintf( '%s: save detected - updating Bricks global classes and palettes', __METHOD__ ) );
		$acss_db = Database_Settings::get_instance();
		$should_refresh = 'on' === $acss_db->get_var( 'option-remove-deactivated-classes-from-globals' );
		if ( ! $should_refresh ) {
			Logger::log( sprintf( '%s: option-remove-deactivated-classes-from-globals is not enabled, skipping', __METHOD__ ) );
			return;
		}
		$this->delete_globals();
		$this->sync_globals();
	}

	/**
	 * Sync ACSS classes and colors to Bricks globals.
	 *
	 * @return void
	 */
	public function sync_globals() {
		Logger::log( sprintf( '%s: adding Automatic.css classes and palettes into Bricks global classes', __METHOD__ ) );
		$this->update_global_classes();
		$this->update_global_colors();
		Logger::log( sprintf( '%s: done', __METHOD__ ) );
	}

	/**
	 * Delete ACSS classes and colors from Bricks globals.
	 *
	 * @return void
	 */
	public function delete_globals() {
		Logger::log( sprintf( '%s: deleting Automatic.css global classes and palettes from Bricks', __METHOD__ ) );
		$this->delete_global_classes();
		$this->delete_global_colors();
		Logger::log( sprintf( '%s: done', __METHOD__ ) );
	}

	/**
	 * Update Bricks global classes and locked classes.
	 *
	 * @return void
	 */
	private function update_global_classes() {
		$acss_db = Database_Settings::get_instance();
		$pro_mode_classes_only = 'on' === $acss_db->get_var( 'option-remove-deactivated-classes-from-globals' );
		$acss_classes = ( new Framework() )->get_classes( $pro_mode_classes_only );
		if ( ! is_array( $acss_classes ) || 0 === count( $acss_classes ) ) {
			return;
		}
		$bricks_class_categories = (array) get_option( 'bricks_global_classes_categories', array() );
		$acss_category_id = array_search( self::CLASS_CATEGORY_ID, array_column( $bricks_class_categories, 'id' ), true );
		if ( false === $acss_category_id ) {
			$bricks_class_categories[] = array(
				'id'   => self::CLASS_CATEGORY_ID,
				'name' => 'Automatic.css',
			);
			update_option( 'bricks_global_classes_categories', $bricks_class_categories );
		}
		$bricks_global_classes = (array) get_option( 'bricks_global_classes', array() );
		$bricks_global_class_names = array_column( $bricks_global_classes, 'name' );
		$bricks_locked_classes = (array) get_option( 'bricks_global_classes_locked', array() );
		foreach ( $acss_classes as $acss_class ) {
			if ( ! in_array( $acss_class, $bricks_global_class_names, true ) ) {
				$bricks_global_classes[] = array(
					'id'       => self::CLASS_IMPORT_ID_PREFIX . $acss_class,
					'name'     => $acss_class,
					'settings' => array(),
					'category' => self::CLASS_CATEGORY_ID,
				);
			}
			if ( ! in_array( self::CLASS_IMPORT_ID_PREFIX . $acss_class, $bricks_locked_classes, true ) ) {
				$bricks_locked_classes[] = self::CLASS_IMPORT_ID_PREFIX . $acss_class;
			}
		}
		update_option( 'bricks_global_classes', $bricks_global_classes, false );
		update_option( 'bricks_global_classes_locked', $bricks_locked_classes, false );
		Logger::log( sprintf( '%s: Bricks classes updated', __METHOD__ ) );
	}

	/**
	 * Update Bricks global colors.
	 *
	 * @return void
	 */
	private function update_global_colors() {
		$bricks_color_palette = (array) get_option( 'bricks_color_palette', array() );
		$acss_db = Database_Settings::get_instance();
		$acss_color_palettes = ( new Framework() )->get_color_palettes(
			array(
				'contextual_colors' => 'on' === $acss_db->get_var( 'option-status-colors' ),
				'deprecated_colors' => 'on' === $acss_db->get_var( 'option-shade-clr' ),
				'pro_active_only'   => true,
			)
		);
		foreach ( $acss_color_palettes as $acss_palette_id => $acss_palette_options ) {
			$acss_colors = array_key_exists( 'colors', $acss_palette_options ) ? $acss_palette_options['colors'] : array();
			$bricks_this_palette_key = array_search( self::PALETTE_IMPORT_ID_PREFIX . $acss_palette_id, array_column( $bricks_color_palette, 'id' ), true );
			if ( false === $bricks_this_palette_key ) {
				$bricks_color_palette[] = array(
					'id'     => self::PALETTE_IMPORT_ID_PREFIX . $acss_palette_id,
					'name'   => self::PALETTE_IMPORT_NAME_PREFIX . $acss_palette_options['name'],
					'colors' => array(),
				);
				$bricks_this_palette_key = array_key_last( $bricks_color_palette );
			}
			$bricks_this_palette_color_ids = array_column( $bricks_color_palette[ $bricks_this_palette_key ]['colors'], 'id' );
			foreach ( $acss_colors as $acss_color_name => $acss_color_value ) {
				if ( ! in_array( self::PALETTE_IMPORT_ID_PREFIX . $acss_color_name, $bricks_this_palette_color_ids, true ) ) {
					$bricks_color_palette[ $bricks_this_palette_key ]['colors'][] = array(
						'id'   => self::PALETTE_IMPORT_ID_PREFIX . $acss_color_name,
						'name' => $acss_color_name,
						'raw'  => $acss_color_value,
					);
				}
			}
		}
		update_option( 'bricks_color_palette', $bricks_color_palette, false );
		Logger::log( sprintf( '%s: Bricks color palette updated', __METHOD__ ) );
	}

	/**
	 * Delete Bricks global classes and locked classes that were imported from ACSS.
	 *
	 * @return void
	 */
	private function delete_global_classes() {
		$acss_classes = ( new Framework() )->get_classes();
		if ( ! is_array( $acss_classes ) || 0 === count( $acss_classes ) ) {
			return;
		}
		$bricks_global_classes = (array) get_option( 'bricks_global_classes', array() );
		$bricks_global_class_ids = array_column( $bricks_global_classes, 'id' );
		$bricks_locked_classes = (array) get_option( 'bricks_global_classes_locked', array() );
		foreach ( $acss_classes as $acss_class ) {
			$global_indexes = array_keys( $bricks_global_class_ids, self::CLASS_IMPORT_ID_PREFIX . $acss_class, true );
			if ( is_array( $global_indexes ) && count( $global_indexes ) > 0 ) {
				foreach ( $global_indexes as $global_index ) {
					unset( $bricks_global_classes[ $global_index ] );
				}
				$locked_indexes = array_keys( $bricks_locked_classes, self::CLASS_IMPORT_ID_PREFIX . $acss_class, true );
				if ( is_array( $locked_indexes ) && count( $locked_indexes ) > 0 ) {
					foreach ( $locked_indexes as $locked_index ) {
						unset( $bricks_locked_classes[ $locked_index ] );
					}
				}
			}
		}
		update_option( 'bricks_global_classes', array_values( $bricks_global_classes ), false );
		update_option( 'bricks_global_classes_locked', array_values( $bricks_locked_classes ), false );
		Logger::log( sprintf( '%s: Bricks classes updated', __METHOD__ ) );
	}

	/**
	 * Delete Bricks global colors that were imported from ACSS.
	 *
	 * @return void
	 */
	private function delete_global_colors() {
		$bricks_color_palette = (array) get_option( 'bricks_color_palette', array() );
		if ( empty( $bricks_color_palette ) ) {
			Logger::log( sprintf( '%s: Bricks color palette updated', __METHOD__ ) );
			return;
		}
		$bricks_color_palette_ids = array_column( $bricks_color_palette, 'id' );
		$acss_color_palettes = ( new Framework() )->get_color_palettes();
		foreach ( $acss_color_palettes as $acss_palette_id => $acss_palette_options ) {
			$bricks_this_palette_key = array_search( self::PALETTE_IMPORT_ID_PREFIX . $acss_palette_id, $bricks_color_palette_ids, true );
			if ( false === $bricks_this_palette_key ) {
				continue;
			}
			$bricks_this_palette_color_ids = array_column( $bricks_color_palette[ $bricks_this_palette_key ]['colors'], 'id' );
			$acss_palette_colors = array_key_exists( 'colors', $acss_palette_options ) ? $acss_palette_options['colors'] : array();
			foreach ( $acss_palette_colors as $acss_color_name => $acss_color_value ) {
				$bricks_this_color_key = array_search( self::PALETTE_IMPORT_ID_PREFIX . $acss_color_name, $bricks_this_palette_color_ids, true );
				if ( false !== $bricks_this_color_key ) {
					unset( $bricks_color_palette[ $bricks_this_palette_key ]['colors'][ $bricks_this_color_key ] );
				}
			}
			if ( empty( $bricks_color_palette[ $bricks_this_palette_key ]['colors'] ) ) {
				unset( $bricks_color_palette[ $bricks_this_palette_key ] );
			} else {
				$bricks_color_palette[ $bricks_this_palette_key ]['colors'] = array_values( $bricks_color_palette[ $bricks_this_palette_key ]['colors'] );
			}
		}
		update_option( 'bricks_color_palette', array_values( $bricks_color_palette ), false );
		Logger::log( sprintf( '%s: Bricks color palette updated', __METHOD__ ) );
	}
}
