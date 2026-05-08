<?php
/**
 * Migration for version 1.0.0-rc-2.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Migrations\Versions;

use Etch\Helpers\Logger;
use Etch\Helpers\WideEventLogger;
use Etch\Lifecycle\PostsContextInterface;
use Etch\Lifecycle\WpPostsContext;
use Etch\Migrations\MigrationInterface;
use Etch\Services\SettingsService;
use Etch\Services\SettingsServiceInterface;

/**
 * Migration for version 1.0.0-rc-2.
 *
 * Detects if any posts or templates use the unsafe="true" parameter
 * in etch:html blocks, and enables the allow_raw_html_unsafe_usage
 * setting to preserve existing site behavior.
 */
class Migration_1_0_0_Rc_2 implements MigrationInterface {

	/**
	 * Posts context for database queries.
	 *
	 * @var PostsContextInterface
	 */
	private PostsContextInterface $posts_context;

	/**
	 * Settings service for storing settings.
	 *
	 * @var SettingsServiceInterface
	 */
	private SettingsServiceInterface $settings_service;

	/**
	 * Constructor.
	 *
	 * @param PostsContextInterface|null    $posts_context    Posts context for database queries.
	 * @param SettingsServiceInterface|null $settings_service Settings service for storing settings.
	 */
	public function __construct(
		?PostsContextInterface $posts_context = null,
		?SettingsServiceInterface $settings_service = null
	) {
		$this->posts_context    = $posts_context ?? new WpPostsContext();
		$this->settings_service = $settings_service ?? SettingsService::get_instance();
	}

	/**
	 * Get the target version for this migration.
	 *
	 * @return string Semantic version string.
	 */
	public function get_version(): string {
		return '1.0.0-rc-2';
	}

	/**
	 * Get a human-readable description of what this migration does.
	 *
	 * @return string Description of the migration.
	 */
	public function get_description(): string {
		return 'Enable unsafe HTML setting if already in use';
	}

	/**
	 * Run the migration.
	 *
	 * Searches for posts/templates with unsafe="true" in etch/raw-html blocks.
	 * If found, enables the allow_raw_html_unsafe_usage setting.
	 *
	 * @return void
	 */
	public function run(): void {
		$has_unsafe_html = $this->has_unsafe_html_posts();

		WideEventLogger::set( 'migration.1_0_0_rc_2.unsafe_html_found', $has_unsafe_html );

		if ( $has_unsafe_html ) {
			$this->settings_service->set_setting( 'allow_raw_html_unsafe_usage', true );
			WideEventLogger::set( 'migration.1_0_0_rc_2.setting_enabled', 'allow_raw_html_unsafe_usage' );
			Logger::log( 'Migration 1.0.0-rc-2: Found posts with unsafe HTML. Enabled allow_raw_html_unsafe_usage.' );
		} else {
			Logger::log( 'Migration 1.0.0-rc-2: No unsafe HTML usage found.' );
		}
	}

	/**
	 * Check if any posts have unsafe={true} in etch/raw-html blocks.
	 *
	 * Matches both literal values ("unsafe":"true") and dynamic expressions
	 * ("unsafe":"{true}").
	 *
	 * @return bool True if any posts have unsafe HTML.
	 */
	private function has_unsafe_html_posts(): bool {
		return $this->posts_context->has_posts_matching_any_group(
			array(
				// Pattern group 1: literal "true".
				array( '%wp:etch/raw-html%', '%"unsafe":"true"%' ),
				// Pattern group 2: dynamic expression "{true}".
				array( '%wp:etch/raw-html%', '%"unsafe":"{true}"%' ),
			)
		);
	}
}
