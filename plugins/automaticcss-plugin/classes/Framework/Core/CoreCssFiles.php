<?php
/**
 * Value object grouping the CSS files managed by Core.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Framework\Core;

use Automatic_CSS\Helpers\SCSS_File;

/**
 * Groups the CSS files used by the Core context.
 */
class CoreCssFiles {

	/**
	 * The core CSS file.
	 *
	 * @var SCSS_File
	 */
	public $core;

	/**
	 * The variables CSS file.
	 *
	 * @var SCSS_File
	 */
	public $vars;

	/**
	 * The custom CSS file.
	 *
	 * @var SCSS_File
	 */
	public $custom;

	/**
	 * The tokens CSS file.
	 *
	 * @var SCSS_File
	 */
	public $tokens;

	/**
	 * Constructor.
	 *
	 * @param SCSS_File $core   The core CSS file.
	 * @param SCSS_File $vars   The variables CSS file.
	 * @param SCSS_File $custom The custom CSS file.
	 * @param SCSS_File $tokens The tokens CSS file.
	 */
	public function __construct(
		SCSS_File $core,
		SCSS_File $vars,
		SCSS_File $custom,
		SCSS_File $tokens
	) {
		$this->core   = $core;
		$this->vars   = $vars;
		$this->custom = $custom;
		$this->tokens = $tokens;
	}
}
