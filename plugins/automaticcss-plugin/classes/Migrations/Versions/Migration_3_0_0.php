<?php
/**
 * Migration for ACSS 3.0.0.
 *
 * @package Automatic_CSS\Migrations\Versions
 */

namespace Automatic_CSS\Migrations\Versions;

use Automatic_CSS\Helpers\Color;
use Automatic_CSS\Helpers\Logger;
use Automatic_CSS\Migrations\MigrationInterface;
use Automatic_CSS\Model\Config\UI;

/**
 * Migration_3_0_0 class.
 *
 * Major version upgrade with many variable renames, unit conversions,
 * and new color shade settings.
 */
class Migration_3_0_0 implements MigrationInterface {

	/**
	 * Get the target version for this migration.
	 *
	 * @return string
	 */
	public function get_version(): string {
		return '3.0.0';
	}

	/**
	 * Get a human-readable description of what this migration does.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Major v3.0 upgrade: variable renames, unit conversions, semi-light/semi-dark shades, semantic colors';
	}

	/**
	 * Run the migration on the given settings values.
	 *
	 * @param array<string, mixed> $values The current settings values.
	 * @return array<string, mixed> The migrated settings values.
	 */
	public function run( array $values ): array {
		$this->migrate_renamed_settings( $values );
		$this->convert_rem_settings( $values );
		$this->convert_em_settings( $values );
		$this->append_rem_settings( $values );
		$this->convert_lightness_settings( $values );
		$this->add_shade_variations( $values );
		$this->migrate_semantic_color_toggles( $values );
		$this->set_default_on_settings( $values );
		$this->set_default_off_settings( $values );
		$this->migrate_text_wrap_settings( $values );
		$this->migrate_padding_option( $values );
		$this->add_neutral_trans_settings( $values );

		// Update the timestamp to force the migration to happen.
		$values['timestamp'] = time();

		return $values;
	}

	/**
	 * Migrate renamed settings from old names to new names.
	 *
	 * @param array<string, mixed> $values The settings values (by reference).
	 */
	private function migrate_renamed_settings( array &$values ): void {
		$settings_to_migrate = array(
			'option-cetering'                 => 'option-centering',
			'option-paragraph-fix'            => 'option-smart-spacing',
			'default-paragraph-spacing'       => 'paragraph-spacing',
			'default-list-spacing'            => 'list-spacing',
			'default-list-item-spacing'       => 'list-item-spacing',
			'default-heading-spacing'         => 'heading-spacing',
			'btn-transition-duration'         => 'transition-duration',
			'option-bricks-gallery-thumb-size' => 'option-bricks-template-gallery-enhancements',
			'h2-line-length'                  => 'h2-max-width',
			'h3-line-length'                  => 'h3-max-width',
			'h4-line-length'                  => 'h4-max-width',
			'h5-line-length'                  => 'h5-max-width',
			'h6-line-length'                  => 'h6-max-width',
			'base-text-lh'                    => 'text-m-line-height',
			'heading-line-length'             => 'heading-max-width',
			'h1-lh'                           => 'h1-line-height',
			'h2-lh'                           => 'h2-line-height',
			'h3-lh'                           => 'h3-line-height',
			'h4-lh'                           => 'h4-line-height',
			'h5-lh'                           => 'h5-line-height',
			'h6-lh'                           => 'h6-line-height',
			'text-xxl-length'                 => 'text-xxl-max-width',
			'text-xl-length'                  => 'text-xl-max-width',
			'text-l-length'                   => 'text-l-max-width',
			'text-m-length'                   => 'text-m-max-width',
			'text-s-length'                   => 'text-s-max-width',
			'text-xs-length'                  => 'text-xs-max-width',
			'text-xxl-lh'                     => 'text-xxl-line-height',
			'text-xl-lh'                      => 'text-xl-line-height',
			'text-l-lh'                       => 'text-l-line-height',
			'text-m-lh'                       => 'text-m-line-height',
			'text-s-lh'                       => 'text-s-line-height',
			'text-xs-lh'                      => 'text-xs-line-height',
			'color-scheme-locked-selectors'   => 'colorscheme-locked-selectors',
		);
		foreach ( $settings_to_migrate as $old_var_name => $new_var_name ) {
			if ( array_key_exists( $old_var_name, $values ) ) {
				Logger::log( sprintf( '%s: converting %s to %s', __METHOD__, $old_var_name, $new_var_name ) );
				$values[ $new_var_name ] = $values[ $old_var_name ];
				unset( $values[ $old_var_name ] );
			}
		}
	}

	/**
	 * Convert settings from numeric to rem units.
	 *
	 * @param array<string, mixed> $values The settings values (by reference).
	 */
	private function convert_rem_settings( array &$values ): void {
		$rem_convert_settings = array( 'btn-border-width', 'btn-outline-border-width' );
		foreach ( $rem_convert_settings as $setting ) {
			if ( array_key_exists( $setting, $values ) && is_numeric( $values[ $setting ] ) ) {
				$old_value = $values[ $setting ];
				$new_value = $old_value / 10 . 'rem';
				Logger::log( sprintf( '%s: converting %s from %s to %s', __METHOD__, $setting, $old_value, $new_value ) );
				$values[ $setting ] = $new_value;
			}
		}
	}

	/**
	 * Append "em" to settings that need it.
	 *
	 * @param array<string, mixed> $values The settings values (by reference).
	 */
	private function convert_em_settings( array &$values ): void {
		$em_append_settings = array( 'col-rule-width-l', 'col-rule-width-m', 'col-rule-width-s' );
		foreach ( $em_append_settings as $setting ) {
			if ( array_key_exists( $setting, $values ) && is_numeric( $values[ $setting ] ) ) {
				$old_value = $values[ $setting ];
				$new_value = $old_value . 'em';
				Logger::log( sprintf( '%s: converting %s from %s to %s', __METHOD__, $setting, $old_value, $new_value ) );
				$values[ $setting ] = $new_value;
			}
		}
	}

	/**
	 * Append "rem" to settings that need it.
	 *
	 * @param array<string, mixed> $values The settings values (by reference).
	 */
	private function append_rem_settings( array &$values ): void {
		$rem_append_settings = array( 'col-width-l', 'col-width-m', 'col-width-s' );
		foreach ( $rem_append_settings as $setting ) {
			if ( array_key_exists( $setting, $values ) && is_numeric( $values[ $setting ] ) ) {
				$old_value = $values[ $setting ];
				$new_value = $old_value . 'rem';
				Logger::log( sprintf( '%s: converting %s from %s to %s', __METHOD__, $setting, $old_value, $new_value ) );
				$values[ $setting ] = $new_value;
			}
		}
	}

	/**
	 * Convert lightness multiplier settings to percentage values.
	 *
	 * @param array<string, mixed> $values The settings values (by reference).
	 */
	private function convert_lightness_settings( array &$values ): void {
		$lightness_convert_settings = array(
			'action-hover-l',
			'action-hover-l-alt',
			'primary-hover-l',
			'primary-hover-l-alt',
			'secondary-hover-l',
			'secondary-hover-l-alt',
			'accent-hover-l',
			'accent-hover-l-alt',
			'base-hover-l',
			'base-hover-l-alt',
			'shade-hover-l',
			'shade-hover-l-alt',
			'neutral-hover-l',
			'neutral-hover-l-alt',
			'success-hover-l',
			'success-hover-l-alt',
			'danger-hover-l',
			'danger-hover-l-alt',
			'info-hover-l',
			'info-hover-l-alt',
			'warning-hover-l',
			'warning-hover-l-alt',
		);
		foreach ( $lightness_convert_settings as $setting ) {
			$color_name = 'color-' . str_replace( '-hover-l', '', $setting );
			if ( array_key_exists( $setting, $values ) && array_key_exists( $color_name, $values ) ) {
				$color     = new Color( $values[ $color_name ] );
				$old_value = $values[ $setting ];
				$new_value = $color->l * $old_value;
				if ( $new_value > 100 ) {
					$new_value = 100;
				} elseif ( $new_value < 0 ) {
					$new_value = 0;
				}
				Logger::log( sprintf( '%s: converting %s from %s to %s', __METHOD__, $setting, $old_value, $new_value ) );
				$values[ $setting ] = $new_value;
			}
		}
	}

	/**
	 * Add semi-light and semi-dark shade variations for all colors.
	 *
	 * @param array<string, mixed> $values The settings values (by reference).
	 */
	private function add_shade_variations( array &$values ): void {
		$all_colors      = array( 'action', 'primary', 'secondary', 'base', 'accent', 'shade', 'neutral', 'success', 'danger', 'info', 'warning' );
		$semantic_colors = array( 'success', 'danger', 'info', 'warning' );

		// Get shade lightness values from global UI settings.
		$ui                     = new UI();
		$global_ui_settings     = $ui->get_globals();
		$global_shade_settings  = $global_ui_settings['color']['shades'] ?? array();
		$semi_light_shade_lightness  = 0;
		$semi_dark_shade_lightness   = 0;
		$ultra_light_shade_lightness = 0;
		$ultra_dark_shade_lightness  = 0;
		$medium_shade_lightness      = 0;

		foreach ( $global_shade_settings as $shade ) {
			if ( 'semi-light' === $shade['name'] ) {
				$semi_light_shade_lightness = $shade['l'];
			}
			if ( 'semi-dark' === $shade['name'] ) {
				$semi_dark_shade_lightness = $shade['l'];
			}
			if ( 'ultra-light' === $shade['name'] ) {
				$ultra_light_shade_lightness = $shade['l'];
			}
			if ( 'ultra-dark' === $shade['name'] ) {
				$ultra_dark_shade_lightness = $shade['l'];
			}
			if ( 'medium' === $shade['name'] ) {
				$medium_shade_lightness = $shade['l'];
			}
		}

		foreach ( $all_colors as $color_name ) {
			// Main color.
			$color_setting = 'color-' . $color_name;
			if ( array_key_exists( $color_setting, $values ) ) {
				$color_obj = new Color( $values[ $color_setting ] );
				// Semi light.
				$values[ $color_name . '-semi-light-h' ] = $color_obj->h;
				$values[ $color_name . '-semi-light-s' ] = $color_obj->s;
				$values[ $color_name . '-semi-light-l' ] = $semi_light_shade_lightness;
				// Semi dark.
				$values[ $color_name . '-semi-dark-h' ] = $color_obj->h;
				$values[ $color_name . '-semi-dark-s' ] = $color_obj->s;
				$values[ $color_name . '-semi-dark-l' ] = $semi_dark_shade_lightness;
				Logger::log(
					sprintf(
						'%s: adding %s with H %d S %d and L %d',
						__METHOD__,
						$color_name . '-semi-light',
						$color_obj->h,
						$color_obj->s,
						$semi_light_shade_lightness
					)
				);
				Logger::log(
					sprintf(
						'%s: adding %s with H %d S %d and L %d',
						__METHOD__,
						$color_name . '-semi-dark',
						$color_obj->h,
						$color_obj->s,
						$semi_dark_shade_lightness
					)
				);
				if ( in_array( $color_name, $semantic_colors, true ) ) {
					// Semantic colors now have the medium, ultra-light and ultra-dark shades.
					$values[ $color_name . '-medium-h' ]      = $color_obj->h;
					$values[ $color_name . '-medium-s' ]      = $color_obj->s;
					$values[ $color_name . '-medium-l' ]      = $medium_shade_lightness;
					$values[ $color_name . '-ultra-light-h' ] = $color_obj->h;
					$values[ $color_name . '-ultra-light-s' ] = $color_obj->s;
					$values[ $color_name . '-ultra-light-l' ] = $ultra_light_shade_lightness;
					$values[ $color_name . '-ultra-dark-h' ]  = $color_obj->h;
					$values[ $color_name . '-ultra-dark-s' ]  = $color_obj->s;
					$values[ $color_name . '-ultra-dark-l' ]  = $ultra_dark_shade_lightness;
				}
			}

			// Alt color.
			$alt_color_setting = 'color-' . $color_name . '-alt';
			if ( array_key_exists( $alt_color_setting, $values ) ) {
				$color_obj = new Color( $values[ $alt_color_setting ] );
				// Semi light.
				$values[ $color_name . '-semi-light-h-alt' ] = $color_obj->h;
				$values[ $color_name . '-semi-light-s-alt' ] = $color_obj->s;
				$values[ $color_name . '-semi-light-l-alt' ] = $semi_light_shade_lightness;
				// Semi dark.
				$values[ $color_name . '-semi-dark-h-alt' ] = $color_obj->h;
				$values[ $color_name . '-semi-dark-s-alt' ] = $color_obj->s;
				$values[ $color_name . '-semi-dark-l-alt' ] = $semi_dark_shade_lightness;
				Logger::log(
					sprintf(
						'%s: adding %s with H %d S %d and L %d',
						__METHOD__,
						$color_name . '-semi-light-alt',
						$color_obj->h,
						$color_obj->s,
						$semi_light_shade_lightness
					)
				);
				Logger::log(
					sprintf(
						'%s: adding %s with H %d S %d and L %d',
						__METHOD__,
						$color_name . '-semi-dark-alt',
						$color_obj->h,
						$color_obj->s,
						$semi_dark_shade_lightness
					)
				);
				if ( in_array( $color_name, $semantic_colors, true ) ) {
					$values[ $color_name . '-medium-h-alt' ]      = $color_obj->h;
					$values[ $color_name . '-medium-s-alt' ]      = $color_obj->s;
					$values[ $color_name . '-medium-l-alt' ]      = $medium_shade_lightness;
					$values[ $color_name . '-ultra-light-h-alt' ] = $color_obj->h;
					$values[ $color_name . '-ultra-light-s-alt' ] = $color_obj->s;
					$values[ $color_name . '-ultra-light-l-alt' ] = $ultra_light_shade_lightness;
					$values[ $color_name . '-ultra-dark-h-alt' ]  = $color_obj->h;
					$values[ $color_name . '-ultra-dark-s-alt' ]  = $color_obj->s;
					$values[ $color_name . '-ultra-dark-l-alt' ]  = $ultra_dark_shade_lightness;
				}
			}
		}
	}

	/**
	 * Migrate semantic color toggles to follow the contextual colors toggle.
	 *
	 * @param array<string, mixed> $values The settings values (by reference).
	 */
	private function migrate_semantic_color_toggles( array &$values ): void {
		$semantic_color_toggles  = array( 'option-success-clr', 'option-danger-clr', 'option-warning-clr', 'option-info-clr' );
		$contextual_colors_value = array_key_exists( 'option-contextual-colors', $values ) ? $values['option-contextual-colors'] : 'off';
		foreach ( $semantic_color_toggles as $semantic_color_toggle ) {
			$values[ $semantic_color_toggle ] = $contextual_colors_value;
			Logger::log( sprintf( '%s: setting %s to %s', __METHOD__, $semantic_color_toggle, $contextual_colors_value ) );
		}
	}

	/**
	 * Set settings that need to be on by default in upgraded installs.
	 *
	 * @param array<string, mixed> $values The settings values (by reference).
	 */
	private function set_default_on_settings( array &$values ): void {
		$default_on_settings = array( 'option-radius-sizes', 'option-medium-shade', 'option-comp-colors', 'option-auto-object-fit' );
		foreach ( $default_on_settings as $default_on_setting ) {
			$values[ $default_on_setting ] = 'on';
			Logger::log( sprintf( '%s: setting %s to on', __METHOD__, $default_on_setting ) );
		}
	}

	/**
	 * Set settings that need to be off by default in upgraded installs.
	 *
	 * @param array<string, mixed> $values The settings values (by reference).
	 */
	private function set_default_off_settings( array &$values ): void {
		$default_off_settings = array( 'option-auto-radius' );
		foreach ( $default_off_settings as $default_off_setting ) {
			$values[ $default_off_setting ] = 'off';
			Logger::log( sprintf( '%s: setting %s to off', __METHOD__, $default_off_setting ) );
		}
	}

	/**
	 * Migrate text wrap settings from 2.x options to 3.x values.
	 *
	 * @param array<string, mixed> $values The settings values (by reference).
	 */
	private function migrate_text_wrap_settings( array &$values ): void {
		$text_wrap_settings = array(
			'option-balance-text'     => 'text-wrap',
			'option-balance-headings' => 'heading-text-wrap',
		);
		foreach ( $text_wrap_settings as $old_setting => $new_setting ) {
			$new_value = 'balance';
			if ( array_key_exists( $old_setting, $values ) && 'on' === $values[ $old_setting ] ) {
				$values[ $new_setting ] = $new_value;
				Logger::log( sprintf( '%s: setting %s to %s', __METHOD__, $new_setting, $new_value ) );
			}
		}
	}

	/**
	 * Migrate the padding option to the deprecated padding option.
	 *
	 * @param array<string, mixed> $values The settings values (by reference).
	 */
	private function migrate_padding_option( array &$values ): void {
		if ( array_key_exists( 'option-padding', $values ) && 'on' === $values['option-padding'] ) {
			$values['option-deprecated-padding'] = 'on';
			Logger::log( sprintf( '%s: setting option-deprecated-padding to on', __METHOD__ ) );
		}
	}

	/**
	 * Add neutral -trans options which didn't exist in 2.x.
	 *
	 * @param array<string, mixed> $values The settings values (by reference).
	 */
	private function add_neutral_trans_settings( array &$values ): void {
		$neutral_option_value   = array_key_exists( 'option-neutral-clr', $values ) ? $values['option-neutral-clr'] : 'off';
		$neutral_trans_settings = array(
			'option-neutral-main-trans',
			'option-neutral-light-trans',
			'option-neutral-dark-trans',
			'option-neutral-ultra-dark-trans',
		);
		foreach ( $neutral_trans_settings as $neutral_setting ) {
			$values[ $neutral_setting ] = $neutral_option_value;
			Logger::log( sprintf( '%s: setting %s to %s', __METHOD__, $neutral_setting, $neutral_option_value ) );
		}
	}
}
