<?php
/**
 * Automatic.css Framework's Context Core file.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Framework\Core;

use Automatic_CSS\Helpers\Container;
use Automatic_CSS\Helpers\Logger;
use Automatic_CSS\Helpers\SCSS_File;
use Automatic_CSS\Helpers\Timer;
use Automatic_CSS\Framework\AssetManager\HasAssetsToEnqueueInterface;
use Automatic_CSS\Framework\ContextManager\Context;
use Automatic_CSS\Framework\ContextManager\DeterminesContextInterface;
use Automatic_CSS\Framework\Generation\Transformers\ColorTransformer;
use Automatic_CSS\Framework\Generation\Transformers\CssVarTransformer;
use Automatic_CSS\Framework\Generation\Transformers\DependentColorTransformer;
use Automatic_CSS\Framework\Generation\Transformers\ScaleFallbackTransformer;
use Automatic_CSS\Framework\Generation\Transformers\UnitTransformer;
use Automatic_CSS\Framework\IntegrationManager\GeneratesCssFilesInterface;
use Automatic_CSS\Framework\IntegrationManager\GeneratesCssFilesTrait;
use Automatic_CSS\Framework\Integrations\Gutenberg;
use Automatic_CSS\Helpers\WordPress;
use Automatic_CSS\Model\Config\UI;
use Automatic_CSS\Model\SettingsRepositoryInterface;
use Automatic_CSS\Plugin;

/**
 * Automatic.css Framework's Context Core class.
 */
class Core implements DeterminesContextInterface, HasAssetsToEnqueueInterface, GeneratesCssFilesInterface {

	use GeneratesCssFilesTrait;

	/**
	 * CSS files managed by this class.
	 *
	 * @var CoreCssFiles
	 */
	private $css_files;

	/**
	 * Whether the option-inline-tokens is on.
	 *
	 * @var boolean
	 */
	private $is_option_inline_tokens_on;

	/**
	 * Settings repository for accessing plugin settings.
	 *
	 * @var SettingsRepositoryInterface
	 */
	private $settings;

	/**
	 * UI config instance for retrieving framework settings metadata.
	 *
	 * @var UI
	 */
	private $ui;

	/**
	 * Constructor
	 *
	 * @param SettingsRepositoryInterface|null $settings  The settings repository (optional, resolves from container if null).
	 * @param UI|null                          $ui        The UI config instance (optional, creates new if null).
	 * @param CoreCssFiles|null                $css_files The CSS files (optional, creates defaults if null).
	 */
	public function __construct(
		$settings = null,
		$ui = null,
		$css_files = null
	) {
		// Resolve settings from container if not provided.
		$this->settings = $settings ?? Container::get_instance()->get( SettingsRepositoryInterface::class );
		$this->ui = $ui ?? new UI();
		$this->css_files = $css_files ?? new CoreCssFiles(
			new SCSS_File( 'automaticcss-core', 'automatic.css', 'automatic.scss' ),
			new SCSS_File( 'automaticcss-variables', 'automatic-variables.css', 'automatic-variables.scss' ),
			new SCSS_File(
				'automaticcss-custom',
				'automatic-custom-css.css',
				array(
					'source_file' => Plugin::get_dynamic_css_dir() . '/automatic-custom-css.scss',
					'no_source_prefix' => true,
					'imports_folder' => './',
					'skip_file_exists_check' => true,
				)
			),
			new SCSS_File( 'automaticcss-tokens', 'automatic-tokens.css', 'automatic-tokens.scss' )
		);
		$this->is_option_inline_tokens_on = $this->settings->get_var( 'option-inline-tokens' ) === 'on' ?? false;
		// Hooks.
		add_action( 'acss/gutenberg/builder_context', array( $this, 'enqueue_gutenberg_builder_assets' ) );
		add_action( 'automaticcss_settings_after_save', array( $this, 'generate_custom_scss' ) );
	}

	/**
	 * Whether the builder is active.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return true; // Core is always active.
	}

	/**
	 * Whether the context is the builder context.
	 *
	 * @return boolean
	 */
	public function is_builder_context() {
		return false; // Core is not a builder.
	}

	/**
	 * Whether the context is the preview context.
	 *
	 * @return boolean
	 */
	public function is_preview_context() {
		return false; // Core is not a builder, so it can't be in preview mode.
	}

	/**
	 * Whether the context is the frontend context.
	 *
	 * @return boolean
	 */
	public function is_frontend_context() {
		return WordPress::is_wp_frontend();
	}

	/**
	 * Get the frontend and preview stylesheets.
	 *
	 * @param Context $context The context to get stylesheets for.
	 * @return array<SCSS_File> The stylesheets for the given context.
	 */
	public function get_assets_to_enqueue( Context $context ): array {
		switch ( $context->get_context() ) {
			case Context::BUILDER:
				return array(
					$this->css_files->vars,
				);
			case Context::PREVIEW:
				if ( $this->is_gutenberg_active( $context->get_determiners() ) ) {
					return array();
				}
				return array(
					$this->css_files->tokens,
					$this->css_files->core,
					$this->css_files->custom,
				);
			case Context::FRONTEND:
				return array(
					$this->css_files->tokens,
					$this->css_files->core,
					$this->css_files->custom,
				);
			default:
				return array();
		}
	}

	/**
	 * Enqueue the appropriate assets based on context.
	 *
	 * @param Context $context The context.
	 * @return void
	 */
	public function enqueue_assets( Context $context ): void {
		$assets = $this->get_assets_to_enqueue( $context );
		foreach ( $assets as $asset ) {
			switch ( $asset->handle ) {
				case $this->css_files->core->handle:
				case $this->css_files->vars->handle:
					$asset->enqueue_stylesheet();
					break;
				case $this->css_files->tokens->handle:
					// TODO: test cover this.
					if ( $this->is_option_inline_tokens_on ) {
						$asset->enqueue_inline();
					} else {
						$asset->enqueue_stylesheet();
					}
					break;
				case $this->css_files->custom->handle:
					$asset->enqueue_inline();
					break;
			}
		}
	}

	/**
	 * Enqueue the VARS stylesheet in the Gutenberg builder context.
	 * For some reason, without special handling, the VARS stylesheet bleeds into the Gutenberg preview context.
	 *
	 * @return void
	 */
	public function enqueue_gutenberg_builder_assets() {
		$this->css_files->vars->enqueue_stylesheet();
	}

	/**
	 * Whether Gutenberg is active.
	 *
	 * @param array<string> $determiners The class names of the determiners that are active for this context.
	 * @return boolean
	 */
	private function is_gutenberg_active( $determiners ) {
		return in_array( Gutenberg::class, $determiners );
	}

	/**
	 * Get the name of the context determiner.
	 *
	 * @return string
	 */
	public static function get_name() {
		return 'core';
	}

	/**
	 * Get all CSS files managed by this class.
	 *
	 * @return array<SCSS_File> The CSS files.
	 */
	public function get_css_files(): array {
		return array(
			$this->css_files->core,
			$this->css_files->vars,
			$this->css_files->custom,
			$this->css_files->tokens,
		);
	}

	/**
	 * Generate the custom SCSS file.
	 *
	 * @param array $values The settings array.
	 * @return void
	 * @throws \Exception If the file cannot be written.
	 */
	public function generate_custom_scss( $values ) {
		$custom_scss = $values['custom-global-css'] ?? '';
		// Load the template file from ACSS_ASSETS_DIR . '/scss/'.
		$template_file = ACSS_ASSETS_DIR . '/scss/front-end-editor.template.scss';
		$template_scss = file_get_contents( $template_file ) ?? '';
		$custom_scss = $template_scss . "\n" . $custom_scss;
		$file_path = Plugin::get_dynamic_css_dir() . '/automatic-custom-css.scss';
		if ( false === file_put_contents( $file_path, $custom_scss ) ) {
			throw new \Exception(
				sprintf(
					'%s: could not write CSS file to %s',
					__METHOD__,
					esc_html( $file_path )
				)
			);
		}
	}

	/**
	 * Get the framework variables for SCSS compilation.
	 *
	 * @param array $values The database settings values.
	 * @return array The processed variables for SCSS.
	 */
	public function get_framework_variables( $values ) {
		$timer = new Timer();
		Logger::log( sprintf( '%s: starting', __METHOD__ ) );
		$vars = $this->ui->get_all_settings();
		ksort( $vars );

		$dependent_color_transformer = new DependentColorTransformer();
		$dependent_vars = $dependent_color_transformer->get_dependent_vars();

		$variables = $this->process_main_variables( $vars, $values, $dependent_vars );
		$variables = $this->process_dependent_variables( $values, $variables, $dependent_color_transformer );

		Logger::log( sprintf( '%s: done in %s seconds', __METHOD__, $timer->get_time() ) );
		return $variables;
	}

	/**
	 * Process the main (non-dependent) variables.
	 *
	 * @param array $vars           The UI settings metadata.
	 * @param array $values         The database settings values.
	 * @param array $dependent_vars Variable names to skip (processed later).
	 * @return array The processed variables.
	 */
	private function process_main_variables( $vars, $values, $dependent_vars ) {
		$variables = array();
		$color_transformer = new ColorTransformer();
		$scale_transformer = new ScaleFallbackTransformer();
		$unit_transformer = new UnitTransformer();
		$css_var_transformer = new CssVarTransformer();
		$root_font_size = isset( $values['root-font-size'] )
			? floatval( $values['root-font-size'] )
			: UnitTransformer::DEFAULT_ROOT_FONT_SIZE;

		foreach ( $vars as $var => $options ) {
			if ( $this->should_skip_variable( $var, $options, $values, $dependent_vars ) ) {
				continue;
			}

			// Use database value if present, otherwise fall back to default from UI config.
			$value = array_key_exists( $var, $values ) ? $values[ $var ] : $options['default'];
			$value = apply_filters( "automaticcss_input_value_{$var}", $value );
			if ( array_key_exists( 'skip-if-empty', $options ) && '' === $value ) {
				continue;
			}

			$var = array_key_exists( 'variable', $options ) ? $options['variable'] : $var;
			$type = $options['type'];

			if ( 'color' === $type && null !== $value ) {
				$variables += $this->transform_color( $color_transformer, $var, $value );
				continue;
			}

			$value = $scale_transformer->maybe_transform( $var, $value, $values );
			$unit = $unit_transformer->get_unit( $options, $type );
			$value = $unit_transformer->transform( $value, $unit, $options, $root_font_size );
			$value = $css_var_transformer->maybe_transform( $value );
			$value = $unit_transformer->append_unit( $value, $options );
			$value = $this->maybe_wrap_in_quotes( $value, $options );
			$variables[ $var ] = apply_filters( "automaticcss_output_value_{$var}", $value );
		}

		return $variables;
	}

	/**
	 * Whether to skip a variable during main processing.
	 *
	 * @param string $var            The variable name.
	 * @param array  $options        The variable options from UI config.
	 * @param array  $values         The database settings values.
	 * @param array  $dependent_vars Variable names reserved for second pass.
	 * @return bool
	 */
	private function should_skip_variable( $var, $options, $values, $dependent_vars ) {
		if ( array_key_exists( 'skip-css-var', $options ) && $options['skip-css-var'] ) {
			return true;
		}
		if ( in_array( $var, $dependent_vars, true ) ) {
			return true;
		}
		if ( array_key_exists( $var, $values ) ) {
			return false;
		}
		return ! ( array_key_exists( 'default', $options ) && '' !== $options['default'] );
	}

	/**
	 * Transform a color variable into its component variables (HSL, hex, RGB).
	 *
	 * @param ColorTransformer $color_transformer The color transformer.
	 * @param string           $var               The variable name.
	 * @param string           $value             The color value.
	 * @return array Associative array of component variable name => value pairs.
	 */
	private function transform_color( $color_transformer, $var, $value ) {
		$result = array();
		foreach ( $color_transformer->transform( $var, $value ) as $name => $val ) {
			$result[ $name ] = apply_filters( "automaticcss_output_value_{$name}", $val );
		}
		return $result;
	}

	/**
	 * Wrap a value in double quotes if the output: quotes option is set.
	 *
	 * @param mixed $value   The value to potentially wrap.
	 * @param array $options The variable options from UI config.
	 * @return mixed The value, possibly wrapped in quotes.
	 */
	private function maybe_wrap_in_quotes( $value, $options ) {
		if ( ! isset( $options['output'] ) || 'quotes' !== $options['output'] ) {
			return $value;
		}
		if ( ! is_string( $value ) || '' === $value ) {
			return $value;
		}
		if ( preg_match( '/^[\'"].*[\'"]$/', $value ) ) {
			return $value;
		}
		return '"' . $value . '"';
	}

	/**
	 * Process dependent color variables in a second pass.
	 *
	 * @param array                     $values                      The database settings values.
	 * @param array                     $variables                   The already-processed variables.
	 * @param DependentColorTransformer $dependent_color_transformer The dependent color transformer.
	 * @return array The updated variables array.
	 */
	private function process_dependent_variables( $values, $variables, $dependent_color_transformer ) {
		foreach ( $dependent_color_transformer->get_dependent_vars() as $var ) {
			if ( ! array_key_exists( $var, $values ) ) {
				continue;
			}
			$value = apply_filters( "automaticcss_input_value_{$var}", $values[ $var ] );
			Logger::log( sprintf( '%s: handling %s', __METHOD__, $var ) );
			$additional_vars = $dependent_color_transformer->transform( $var, $value, $variables );
			foreach ( $additional_vars as $var_name => $var_value ) {
				$variables[ $var_name ] = $var_value;
				Logger::log( sprintf( '%s: injected %s value %s', __METHOD__, $var_name, $var_value ) );
			}
			$variables[ $var ] = apply_filters( "automaticcss_output_value_{$var}", $value );
		}
		return $variables;
	}
}
