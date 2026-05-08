<?php
/**
 * Asset Manager.
 *
 * Manages the registration and enqueueing of assets.
 *
 * @package Automatic_CSS
 */

namespace Automatic_CSS\Framework\AssetManager;

use Automatic_CSS\Framework\AssetManager\HasAssetsToEnqueueInterface;
use Automatic_CSS\Framework\ContextManager\Context;
use Automatic_CSS\Framework\ContextManager\ContextManager;
use Automatic_CSS\Framework\ContextManager\DeterminesContextInterface;
use Automatic_CSS\Framework\IntegrationManager\IntegrationsManager;
use Automatic_CSS\Helpers\Logger;
use Automatic_CSS\Helpers\WideEventLogger;
use Automatic_CSS\Framework\Core\Core;
use Automatic_CSS\Framework\Dashboard\Dashboard;
use Automatic_CSS\Framework\Integrations\Gutenberg;
use Automatic_CSS\Helpers\Style_Queue;
use Automatic_CSS\Model\Database_Settings;
use Automatic_CSS\Helpers\Container;
use Automatic_CSS\Helpers\Style_Queue_Factory;

/**
 * Class AssetManager
 */
class AssetManager {

	/**
	 * Asset enqueuers (integrations + core).
	 *
	 * @var array<string, HasAssetsToEnqueueInterface>
	 */
	private $asset_enqueuers = array();

	/**
	 * Integrations manager.
	 *
	 * @var IntegrationsManager
	 */
	private $integrations_manager;

	/**
	 * Context manager.
	 *
	 * @var ContextManager
	 */
	private $context_manager;

	/**
	 * Core instance.
	 *
	 * @var HasAssetsToEnqueueInterface
	 */
	private $core_instance;

	/**
	 * Gutenberg instance.
	 *
	 * @var Gutenberg
	 */
	private $gutenberg_instance;

	/**
	 * Dashboard instance.
	 *
	 * @var HasAssetsToEnqueueInterface
	 */
	private $dashboard_instance;

	/**
	 * ACSS Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Style queue.
	 *
	 * @var Style_Queue
	 */
	private $style_queue;

	/**
	 * Constructor
	 *
	 * @param IntegrationsManager $integrations_manager Integrations manager.
	 * @param ContextManager      $context_manager      Context manager.
	 * @param array               $settings             ACSS Settings.
	 * @param Style_Queue         $style_queue          Style queue.
	 */
	public function __construct( $integrations_manager, $context_manager = null, $settings = null, $style_queue = null ) {
		$this->integrations_manager = $integrations_manager;
		$this->core_instance = new Core();
		$this->gutenberg_instance = new Gutenberg();
		$this->dashboard_instance = new Dashboard( $this->integrations_manager->get_permissions(), null, null );
		$this->context_manager = $context_manager ?? new ContextManager(
			$this->integrations_manager->get_active_integrations(),
			$this->core_instance,
			$this->gutenberg_instance
		);
		$this->settings = $settings ?? ( Database_Settings::get_instance()->init() )->get_vars();

		// Initialize style queue.
		$this->style_queue = $style_queue ?? Style_Queue_Factory::get_instance()->get_default_queue();

		// Register style queue in container.
		Container::get_instance()->bind( 'style_queue', $this->style_queue );
	}

	/**
	 * Initialize the context system.
	 *
	 * @return void
	 */
	public function init() {
		// Order matters: Core goes FIRST, before all integrations in their registration order.
		$this->asset_enqueuers = array_merge(
			array( Core::get_name() => $this->core_instance ),
			array( Gutenberg::get_name() => $this->gutenberg_instance ),
			array( 'dashboard' => $this->dashboard_instance ),
			array_filter(
				$this->integrations_manager->get_active_integrations(),
				function ( $integration ) {
					return $integration instanceof HasAssetsToEnqueueInterface;
				}
			),
		);

		// Hook into WordPress.
		$priority =
			isset( $this->settings['option-load-stylesheets-last'] ) && 'on' === $this->settings['option-load-stylesheets-last']
			? ( PHP_INT_MAX - 100 )
			: 10;
		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), $priority );
		} else {
			// add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), $priority ); // TODO: remove?
			add_action( 'wp_head', array( $this, 'enqueue_assets' ), 1000000 ); // TODO: use $priority?
		}
	}

	/**
	 * Enqueue CSS files based on context.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		// Log active integrations and builder.
		$active_integrations = $this->integrations_manager->get_active_integrations();
		$integration_names = array_keys( $active_integrations );
		// First integration that implements DeterminesContextInterface is the builder.
		$builder_name = '';
		foreach ( $active_integrations as $name => $integration ) {
			if ( $integration instanceof DeterminesContextInterface ) {
				$builder_name = $name;
				break;
			}
		}
		// @var array<string, Context> $contexts
		$contexts = $this->context_manager->get_context();
		$active_context_name = 'none';
		foreach ( $contexts as $context ) {
			$is_active = $context->is_active();
			if ( ! $is_active ) {
				continue;
			}
			$active_context_name = $context->get_context();
			$determiners = $context->get_determiners();
			Logger::now(
				sprintf(
					"AssetManager:\n-- URL: %s\n-- context: %s\n-- is: %s\n-- as determined by %s",
					Logger::get_redacted_uri(),
					$active_context_name,
					'active',
					implode( ', ', $determiners ),
				)
			);
			WideEventLogger::set( 'enqueue_assets.determined_context', $active_context_name );
			WideEventLogger::set( 'enqueue_assets.determiners', $determiners );
			$this->enqueue_all_assets_for_context( $context );
		}

		// Emit response header for external diagnostics (cached along with the page).
		if ( ! headers_sent() ) {
			header( 'X-ACSS-Context: ' . $active_context_name );
		}

		// Log when CSS was not enqueued on a non-admin request. This method is
		// hooked to wp_head (non-admin) or admin_enqueue_scripts (admin), so
		// "no context" on a non-admin request means a page is rendering
		// without ACSS CSS — the exact scenario we're investigating.
		if ( 'none' === $active_context_name && ! is_admin() && ! wp_doing_ajax() ) {
			$diagnostic_data = array(
				'request_uri'        => isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '(not set)',
				'http_accept'        => isset( $_SERVER['HTTP_ACCEPT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ) ) : '(not set)',
				'content_type'       => isset( $_SERVER['CONTENT_TYPE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['CONTENT_TYPE'] ) ) : '(not set)',
				'user_agent'         => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '(not set)',
				'is_admin'           => is_admin(),
				'wp_doing_ajax'      => wp_doing_ajax(),
				'wp_is_json_request' => wp_is_json_request(),
			);
			WideEventLogger::failure(
				'enqueue_assets',
				'No context was active — CSS was not enqueued',
				$diagnostic_data
			);
			Logger::warning(
				sprintf(
					"AssetManager: no context active — CSS not enqueued.\nDiagnostic data: %s",
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
					print_r( $diagnostic_data, true )
				)
			);
		}

		WideEventLogger::set( 'enqueue_assets.active_builder', $builder_name );
		WideEventLogger::set( 'enqueue_assets.active_integrations', $integration_names );

		// Log Gutenberg context debug data last (after all other enqueue_assets keys).
		$this->gutenberg_instance->log_context_debug_data();
	}

	/**
	 * Enqueue all assets for a given context.
	 *
	 * @param Context $context The context to enqueue assets for.
	 * @return void
	 */
	private function enqueue_all_assets_for_context( $context ) {
		Logger::now(
			sprintf(
				'Enqueuing assets for context: %s, determiners: %s',
				$context->get_context(),
				implode(
					', ',
					array_map(
						function ( $determiner ) {
							return $determiner;
						},
						$context->get_determiners()
					)
				)
			)
		);
		$loaded_assets = array();
		foreach ( $this->asset_enqueuers as $enqueuer ) {
			$this->enqueue_enqueuers_assets_for_context( $enqueuer, $context );
			// Collect asset handles from enqueuers that implement get_assets_to_enqueue.
			if ( method_exists( $enqueuer, 'get_assets_to_enqueue' ) ) {
				$assets = $enqueuer->get_assets_to_enqueue( $context );
				foreach ( $assets as $asset ) {
					$loaded_assets[] = $asset->handle;
				}
			}
		}
		WideEventLogger::set( 'enqueue_assets.stylesheets_loaded', $loaded_assets );
		$this->style_queue->process();
	}

	/**
	 * Enqueue assets for a given context.
	 *
	 * @param HasAssetsToEnqueueInterface $enqueuer The enqueuer to enqueue assets for.
	 * @param Context                     $context The context to enqueue assets for.
	 * @return void
	 */
	private function enqueue_enqueuers_assets_for_context( $enqueuer, $context ) {
		Logger::now( sprintf( 'Enqueuing %s\'s assets for context: %s', $enqueuer::class, $context->get_context() ) );
		$enqueuer->enqueue_assets( $context );
	}

	/**
	 * Get the stylesheets for a given context.
	 *
	 * @param Context $context The context to get stylesheets for.
	 * @return array The stylesheets for the given context.
	 */
	public function get_assets_to_enqueue( $context ) {
		$assets = array();
		foreach ( $this->asset_enqueuers as $enqueuer ) {
			if ( ! method_exists( $enqueuer, 'get_assets_to_enqueue' ) ) {
				continue;
			}
			$assets = array_merge( $assets, $enqueuer->get_assets_to_enqueue( $context ) );
		}
		return $assets;
	}

	/**
	 * Get the style queue.
	 *
	 * @return Style_Queue
	 */
	public function get_style_queue() {
		return $this->style_queue;
	}

	/**
	 * Check if the enqueuer is active in the given context.
	 *
	 * @param HasAssetsToEnqueueInterface $enqueuer The enqueuer to check.
	 * @param Context                     $context The context to check.
	 * @return bool True if the enqueuer is active in the given context, false otherwise.
	 */
	private function is_active_in_context( $enqueuer, $context ) {
		if ( $enqueuer instanceof DeterminesContextInterface ) {
			// This is a builder, and needs to know if we're loading in ITS context.
			switch ( $context->get_context() ) {
				case Context::PREVIEW:
					return $enqueuer->is_preview_context();
				case Context::BUILDER:
					return $enqueuer->is_builder_context();
					break;
				case Context::FRONTEND:
					return $enqueuer->is_frontend_context();
			}
		}
		return false;
	}
}
