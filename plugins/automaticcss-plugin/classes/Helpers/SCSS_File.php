<?php
/**
 * SCSS_File class - Extends CSS_File with SCSS compilation capabilities.
 *
 * @package Automatic_CSS\Helpers
 */

namespace Automatic_CSS\Helpers;

use Automatic_CSS\Exceptions\CSS_Generation_Failed;
use Automatic_CSS\Helpers\WideEventLogger;
use Automatic_CSS\Plugin;
use Automatic_CSS\ScssPhp\Compiler;
use Automatic_CSS\ScssPhp\ValueConverter;
use Automatic_CSS\Traits\Disableable;
use InvalidArgumentException;

/**
 * SCSS_File class.
 *
 * Extends CSS_File to add SCSS compilation. Inherits all enqueuing functionality
 * from CSS_File and overrides hooks for:
 * - should_proceed(): Check is_enabled() via Disableable trait
 * - get_file_url(): Dynamic URL computation for S3 offload support
 * - get_file_path(): Dynamic path computation for S3 offload support
 */
class SCSS_File extends CSS_File {

	/**
	 * Allow SCSS_Files to be disabled while running.
	 */
	use Disableable;

	/**
	 * Filename
	 *
	 * @var string
	 */
	protected $filename;

	/**
	 * SCSS Options
	 *
	 * @var array $scss_options = [
	 *  'source_file' => '',
	 *  'imports_folder' => '',
	 * ]
	 */
	protected $scss_options;

	/**
	 * Constructor
	 *
	 * @param string $handle The CSS file handle.
	 * @param string $css_filename The filename used to generate the CSS file.
	 * @param array  $scss_options The SCSS options.
	 * @param array  $enqueue_options The enqueue options.
	 * @param bool   $is_enabled  Is this SCSS File enabled or disabled.
	 */
	public function __construct( $handle, $css_filename, $scss_options = array(), $enqueue_options = array(), bool $is_enabled = true ) {
		$this->filename = $css_filename;
		$this->scss_options = $this->process_scss_options( $scss_options );

		// Extract enqueue options for parent.
		$deps = array();
		$media = 'all';
		$queue = null;

		if ( is_array( $enqueue_options ) ) {
			if ( isset( $enqueue_options['deps'] ) && is_array( $enqueue_options['deps'] ) ) {
				$deps = $enqueue_options['deps'];
			}
			if ( isset( $enqueue_options['media'] ) ) {
				$media = $enqueue_options['media'];
			}
			if ( isset( $enqueue_options['queue'] ) && is_a( $enqueue_options['queue'], '\WP_Styles' ) ) {
				$queue = $enqueue_options['queue'];
			}
		}

		// Call parent with null URL/path - we compute them dynamically via hooks.
		parent::__construct(
			$handle,
			null,
			null,
			$deps,
			$media,
			$queue
		);

		$this->set_enabled( $is_enabled );
	}

	/**
	 * Process SCSS options.
	 *
	 * @param array|string $scss_options The SCSS options.
	 * @return array
	 */
	private function process_scss_options( $scss_options ) {
		$scss_prefix = ACSS_ASSETS_DIR . '/scss/';
		$processed = array();

		if ( is_array( $scss_options ) ) {
			$processed = $scss_options;
			if ( isset( $processed['source_file'] ) ) {
				$no_source_prefix = isset( $scss_options['no_source_prefix'] ) && $scss_options['no_source_prefix'];
				$processed['source_file'] = $no_source_prefix ? $scss_options['source_file'] : $scss_prefix . $scss_options['source_file'];
			}
			if ( isset( $processed['imports_folder'] ) ) {
				$no_import_prefix = isset( $scss_options['no_import_prefix'] ) && $scss_options['no_import_prefix'];
				$processed['imports_folder'] = $no_import_prefix ? $scss_options['imports_folder'] : $scss_prefix . $scss_options['imports_folder'];
			}
		} elseif ( is_string( $scss_options ) ) {
			// $scss_options = source file; imports_folder = $scss_prefix.
			$processed['source_file'] = $scss_prefix . $scss_options;
			$processed['imports_folder'] = $scss_prefix;
		}

		return $processed;
	}

	// =========================================================================
	// HOOK OVERRIDES - Customize CSS_File behavior for SCSS
	// =========================================================================

	/**
	 * Check if operations should proceed.
	 *
	 * @return bool
	 */
	protected function should_proceed(): bool {
		return $this->is_enabled();
	}

	/**
	 * Get the file URL dynamically.
	 *
	 * We compute URL on the fly to support plugins that alter upload_dir
	 * (like the S3 offload plugin).
	 *
	 * @return string
	 */
	protected function get_file_url(): string {
		return Plugin::get_dynamic_css_url() . '/' . $this->filename;
	}

	/**
	 * Get the file path dynamically.
	 *
	 * We compute path on the fly to support plugins that alter upload_dir
	 * (like the S3 offload plugin).
	 *
	 * @return string
	 */
	protected function get_file_path(): string {
		return Plugin::get_dynamic_css_dir() . '/' . $this->filename;
	}

	// =========================================================================
	// SCSS COMPILATION METHODS
	// =========================================================================

	/**
	 * Generate the CSS file from the provided variables and save it to the filesystem.
	 *
	 * @param array $variables CSS variable values.
	 * @return bool True if file was generated, false if disabled.
	 * @throws \Exception If it can't save the file.
	 * @throws CSS_Generation_Failed If SCSS compilation fails.
	 */
	public function save_file_from_variables( array $variables ) {
		if ( ! $this->is_enabled() ) {
			Logger::log( '%s: quitting because is_enabled = false', __METHOD__ );
			return false;
		}

		$css = $this->get_css_from_scss( $variables );
		if ( ! is_null( $css ) ) {
			$this->save_file( $css );
			return true;
		}
		return false;
	}

	/**
	 * Save the CSS file to the filesystem.
	 *
	 * @param string $css The CSS code to save to the filesystem.
	 * @return void
	 * @throws \Exception If it can't save the file.
	 */
	private function save_file( $css ) {
		$file_path = $this->get_file_path();
		if ( false === file_put_contents( $file_path, $css ) ) {
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
	 * Compile the SCSS file into the CSS code.
	 *
	 * @param array $variables CSS variable values.
	 * @return string
	 * @throws \Exception If the SCSS file does not exist or variables are not set.
	 * @throws CSS_Generation_Failed If SCSS compilation fails.
	 */
	private function get_css_from_scss( array $variables ) {
		$source_scss = $this->scss_options['source_file'];
		$imports_folder = $this->scss_options['imports_folder'];
		$skip_check = isset( $this->scss_options['skip_file_exists_check'] ) && $this->scss_options['skip_file_exists_check'];
		if ( ! $skip_check && ( '' === $source_scss || ! file_exists( $source_scss ) ) ) {
			$error_message = sprintf( '%s: SCSS file %s does not exist', __METHOD__, $source_scss );
			Logger::log( $error_message, Logger::LOG_LEVEL_ERROR );
			throw new \Exception( esc_html( $error_message ) );
		}
		if ( empty( $variables ) || is_null( $variables ) ) {
			$error_message = sprintf( '%s: SCSS variables are not set', __METHOD__ );
			Logger::log( $error_message, Logger::LOG_LEVEL_ERROR );
			throw new \Exception( esc_html( $error_message ) );
		}
		$compiler = new Compiler();
		$compiler->setSourceMapOptions( Compiler::SOURCE_MAP_NONE );
		$scss_contents = file_get_contents( $source_scss ) ?? '';
		$import_path = ACSS_ASSETS_DIR . '/scss';
		$compiler->addImportPath( $import_path );
		if ( '' !== $imports_folder ) {
			$compiler->addImportPath( $imports_folder );
		}
		$compiler_variables = array();
		foreach ( $variables as $var_id => $var_value ) {
			try {
				// TODO: abstract this special handling and test cover.
				$compiler_variables[ $var_id ] = 'heading-font-family' === $var_id || 'text-font-family' === $var_id
					? ValueConverter::fromPhp( $var_value )
					: ValueConverter::parseValue( $var_value );
			} catch ( InvalidArgumentException $e ) {
				$error_message = sprintf( '%s: error while parsing variable %s (value: "%s"): %s', __METHOD__, $var_id, $var_value, $e->getMessage() );
				Logger::log( $error_message, Logger::LOG_LEVEL_ERROR );
			}
		}
		$compiler->addVariables( $compiler_variables );

		try {
			$css_compiled = $compiler->compileString( $scss_contents )->getCss();
		} catch ( \Exception $e ) {
			$scss_filename = basename( $source_scss );
			$error_message = sprintf( 'SCSS compilation failed for %s: %s', $scss_filename, $e->getMessage() );
			Logger::log( sprintf( '%s: %s', __METHOD__, $error_message ), Logger::LOG_LEVEL_ERROR );
			WideEventLogger::failure(
				'scss',
				$error_message,
				array(
					'file' => $scss_filename,
				)
			);
			throw new CSS_Generation_Failed(
				esc_html( $error_message ),
				array(
					array(
						'file'    => esc_html( $scss_filename ),
						'message' => esc_html( $e->getMessage() ),
					),
				)
			);
		}

		if ( '' === $css_compiled ) {
			return '';
		}

		$file_path = $this->get_file_path();
		$css_filename = basename( $file_path );
		$css = sprintf(
			"/* File: %s - Version: %s - Generated: %s */\n",
			$css_filename,
			Plugin::get_plugin_version(),
			current_time( 'mysql' )
		);
		$css .= $css_compiled;
		return $css;
	}

	/**
	 * Handle font-family values.
	 *
	 * @param mixed $value The value to handle.
	 * @return mixed
	 */
	private static function handle_font_family( $value ) {
		// Check if this is a font-family declaration (contains commas).
		if ( is_string( $value ) && strpos( $value, ',' ) !== false ) {
			$parts = array_map( 'trim', explode( ',', $value ) );
			$parts = array_map(
				function ( $part ) {
					// If it contains spaces and isn't already quoted.
					if ( strpos( $part, ' ' ) !== false && ! preg_match( '/^[\'"].*[\'"]$/', $part ) ) {
						// Use Type::T_STRING to force SCSSPHP to treat it as a quoted string.
						return '"' . $part . '"';
					}
					return $part;
				},
				$parts
			);
			// Return as a Type::T_LIST.
			return $parts;
		}
		return $value;
	}

	/**
	 * Delete the CSS file.
	 *
	 * @return void
	 */
	public function delete_file() {
		@unlink( $this->get_file_path() );
	}

	// =========================================================================
	// MAGIC GETTER - Backward compatibility
	// =========================================================================

	/**
	 * Getter function for backward compatibility.
	 *
	 * @param string $key Key to search for.
	 * @return mixed
	 * @throws \Exception If provided key is not allowed or not set.
	 */
	public function __get( $key ) {
		$allowed_keys = array( 'handle', 'filename', 'file_path', 'file_url', 'css_contents' );
		if ( in_array( $key, $allowed_keys ) ) {
			switch ( $key ) {
				case 'file_url':
					return $this->get_file_url();
				case 'file_path':
					return $this->get_file_path();
				case 'css_contents':
					$path = $this->get_file_path();
					return file_exists( $path ) ? file_get_contents( $path ) : '';
				case 'handle':
					return $this->handle;
				case 'filename':
					return $this->filename;
				default:
					return $this->$key;
			}
		} else {
			throw new \Exception( esc_html( "Trying to get a not allowed or not set key {$key} on a SCSS_File instance" ) );
		}
	}
}
