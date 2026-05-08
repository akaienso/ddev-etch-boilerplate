<?php
/**
 * Migration for ACSS 2.5.0.
 *
 * @package Automatic_CSS\Migrations\Versions
 */

namespace Automatic_CSS\Migrations\Versions;

use Automatic_CSS\Helpers\Logger;
use Automatic_CSS\Migrations\MigrationInterface;

/**
 * Migration_2_5_0 class.
 *
 * Renames form styling variables from f-light-* to f-* prefix.
 */
class Migration_2_5_0 implements MigrationInterface {

	/**
	 * Get the target version for this migration.
	 *
	 * @return string
	 */
	public function get_version(): string {
		return '2.5.0';
	}

	/**
	 * Get a human-readable description of what this migration does.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Rename form styling variables from f-light-* to f-* prefix';
	}

	/**
	 * Run the migration on the given settings values.
	 *
	 * @param array<string, mixed> $values The current settings values.
	 * @return array<string, mixed> The migrated settings values.
	 */
	public function run( array $values ): array {
		$migration = array(
			// old variable => new variable.
			'f-light-label-size-min'               => 'f-label-size-min',
			'f-light-label-size-max'               => 'f-label-size-max',
			'f-light-label-font-weight'            => 'f-label-font-weight',
			'f-light-label-padding-x'              => 'f-label-padding-x',
			'f-light-label-padding-y'              => 'f-label-padding-y',
			'f-light-label-margin-bottom'          => 'f-label-margin-bottom',
			'f-light-label-text-transform'         => 'f-label-text-transform',
			'f-light-legend-text-weight'           => 'f-legend-text-weight',
			'f-light-legend-size-min'              => 'f-legend-size-min',
			'f-light-legend-size-max'              => 'f-legend-size-max',
			'f-light-legend-margin-bottom'         => 'f-legend-margin-bottom',
			'f-light-legend-line-height'           => 'f-legend-line-height',
			'f-light-help-size-min'                => 'f-help-size-min',
			'f-light-help-size-max'                => 'f-help-size-max',
			'f-light-help-line-height'             => 'f-help-line-height',
			'f-light-field-margin-bottom'          => 'f-field-margin-bottom',
			'f-light-fieldset-margin-bottom'       => 'f-fieldset-margin-bottom',
			'f-light-grid-gutter'                  => 'f-grid-gutter',
			'f-light-input-border-top-size'        => 'f-input-border-top-size',
			'f-light-input-border-right-size'      => 'f-input-border-right-size',
			'f-light-input-border-bottom-size'     => 'f-input-border-bottom-size',
			'f-light-input-border-left-size'       => 'f-input-border-left-size',
			'f-light-input-border-radius'          => 'f-input-border-radius',
			'f-light-input-text-size-min'          => 'f-input-text-size-min',
			'f-light-input-text-size-max'          => 'f-input-text-size-max',
			'f-light-input-font-weight'            => 'f-input-font-weight',
			'f-light-input-height'                 => 'f-input-height',
			'f-light-input-padding-x'              => 'f-input-padding-x',
			'f-light-btn-border-style'             => 'f-btn-border-style',
			'f-light-btn-margin-top'               => 'f-btn-margin-top',
			'f-light-btn-padding-y'                => 'f-btn-padding-y',
			'f-light-btn-padding-x'                => 'f-btn-padding-x',
			'f-light-btn-border-width'             => 'f-btn-border-width',
			'f-light-btn-border-radius'            => 'f-btn-border-radius',
			'f-light-btn-text-size-min'            => 'f-btn-text-size-min',
			'f-light-btn-text-size-max'            => 'f-btn-text-size-max',
			'f-light-btn-font-weight'              => 'f-btn-font-weight',
			'f-light-btn-line-height'              => 'f-btn-line-height',
			'f-light-btn-text-transform'           => 'f-btn-text-transform',
			'f-light-btn-text-decoration'          => 'f-btn-text-decoration',
			'f-light-option-label-font-weight'     => 'f-option-label-font-weight',
			'f-light-option-label-font-size-min'   => 'f-option-label-font-size-min',
			'f-light-option-label-font-size-max'   => 'f-option-label-font-size-max',
			'f-light-progress-height'              => 'f-progress-height',
			'f-light-tab-border-style'             => 'f-tab-border-style',
			'f-light-tab-padding-y'                => 'f-tab-padding-y',
			'f-light-tab-padding-x'                => 'f-tab-padding-x',
			'f-light-tab-margin-x'                 => 'f-tab-margin-x',
			'f-light-tab-border-size'              => 'f-tab-border-size',
			'f-light-tab-active-border-color'      => 'f-dark-tab-border-color',
			'f-light-tab-border-radius'            => 'f-tab-border-radius',
			'f-light-tab-text-size-min'            => 'f-tab-text-size-min',
			'f-light-tab-text-size-max'            => 'f-tab-text-size-max',
			'f-light-tab-text-weight'              => 'f-tab-text-weight',
			'f-light-tab-active-text-weight'       => 'f-tab-active-text-weight',
			'f-light-tab-text-line-height'         => 'f-tab-text-line-height',
			'f-light-tab-text-transform'           => 'f-tab-text-transform',
			'f-light-tab-text-align'               => 'f-tab-text-align',
			'f-light-tab-text-decoration'          => 'f-tab-text-decoration',
			'f-light-tab-active-border-bottom-size' => 'f-tab-active-border-bottom-size',
			'f-light-tab-group-padding-y'          => 'f-tab-group-padding-y',
			'f-light-tab-group-padding-x'          => 'f-tab-group-padding-x',
			'f-light-tab-group-border-bottom-size' => 'f-tab-group-border-bottom-size',
			'f-light-tab-group-border-bottom-style' => 'f-tab-group-border-bottom-style',
			'f-light-tab-group-margin-bottom'      => 'f-tab-group-margin-bottom',
		);
		foreach ( $migration as $old_var_name => $new_var_name ) {
			if ( array_key_exists( $old_var_name, $values ) ) {
				Logger::log( sprintf( '%s: converting %s to %s', __METHOD__, $old_var_name, $new_var_name ) );
				$values[ $new_var_name ] = $values[ $old_var_name ];
				unset( $values[ $old_var_name ] );
			}
		}
		return $values;
	}
}
