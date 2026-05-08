<?php
/**
 * SettingsRoutes.php
 *
 * This file contains the SettingsRoutes class which defines REST API routes for handling Etch settings.
 *
 * PHP version 7.4+
 *
 * @category  Plugin
 * @package   Etch\RestApi\Routes
 */

declare(strict_types=1);
namespace Etch\RestApi\Routes;

use Etch\Services\SettingsService;
use Etch\Services\SettingsServiceInterface;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * SettingsRoutes
 *
 * This class defines REST API endpoints for retrieving and updating Etch settings.
 *
 * @package Etch\RestApi\Routes
 */
class SettingsRoutes extends BaseRoute {

	/**
	 * The templates service instance.
	 *
	 * @var SettingsServiceInterface
	 */
	private SettingsServiceInterface $settings_service;

	/**
	 * Constructor.
	 *
	 * @param SettingsServiceInterface|null $settings_service Optional service instance for dependency injection.
	 */
	public function __construct( ?SettingsServiceInterface $settings_service = null ) {
		$this->settings_service = $settings_service ?? SettingsService::get_instance();
	}

	/**
	 * Returns the route definitions for settings endpoints.
	 *
	 * @return array<array{route: string, methods: string|array<string>, callback: callable, permission_callback?: callable, args?: array<string, mixed>}>
	 */
	protected function get_routes(): array {
		return array(
			array(
				'route'    => '/settings',
				'methods'  => 'GET',
				'callback' => array( $this, 'get_settings' ),
			),
			array(
				'route'    => '/settings',
				'methods'  => 'PUT',
				'callback' => array( $this, 'update_settings' ),
			),
			array(
				'route'    => '/settings/(?P<setting_key>[a-zA-Z0-9_-]+)',
				'methods'  => 'GET',
				'callback' => array( $this, 'get_setting' ),
			),
			array(
				'route'    => '/settings/(?P<setting_key>[a-zA-Z0-9_-]+)',
				'methods'  => 'PUT',
				'callback' => array( $this, 'update_setting' ),
			),
			array(
				'route'    => '/settings/(?P<setting_key>[a-zA-Z0-9_-]+)',
				'methods'  => 'DELETE',
				'callback' => array( $this, 'delete_setting' ),
			),
		);
	}

	/**
	 * Retrieves all settings.
	 *
	 * @return WP_REST_Response The response containing the settings.
	 */
	public function get_settings(): WP_REST_Response {
		$settings = $this->settings_service->get_settings();
		return new WP_REST_Response( $settings, 200 );
	}

	/**
	 * Updates all settings.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request The REST request.
	 * @return WP_REST_Response|WP_Error The response indicating the result of the update.
	 */
	public function update_settings( $request ) {
		$settings = $request->get_json_params();

		if ( ! is_array( $settings ) ) {
			return new WP_Error(
				'invalid_request_body',
				__( 'Invalid request body', 'etch' ),
				array( 'status' => 400 )
			);
		}

		$this->settings_service->set_settings( $settings );
		return new WP_REST_Response(
			array(
				'message' => 'Settings updated successfully',
			),
			200
		);
	}

	/**
	 * Retrieves a specific setting.
	 *
	 * @param WP_REST_Request<array{setting_key?: string}> $request The REST request.
	 * @return WP_REST_Response | WP_Error
	 */
	public function get_setting( $request ): WP_REST_Response|WP_Error {
		$setting_key = $request->get_param( 'setting_key' );

		if ( ! is_string( $setting_key ) || empty( $setting_key ) ) {
			return new WP_Error(
				'invalid_setting_key',
				__( 'Invalid setting key', 'etch' ),
				array( 'status' => 400 )
			);
		}

		$setting = $this->settings_service->get_setting( $setting_key );
		return new WP_REST_Response( $setting, 200 );
	}

	/**
	 * Updates a specific setting.
	 *
	 * @param WP_REST_Request<array{setting_key?: string}> $request The REST request.
	 * @return WP_REST_Response | WP_Error The response indicating the result of the update.
	 */
	public function update_setting( $request ) {
		$setting_key = $request->get_param( 'setting_key' );

		if ( ! is_string( $setting_key ) || empty( $setting_key ) ) {
			return new WP_Error(
				'invalid_setting_key',
				__( 'Invalid setting key', 'etch' ),
				array( 'status' => 400 )
			);
		}

		$body = $request->get_json_params();

		if ( ! is_array( $body ) || ! isset( $body['value'] ) ) {
			return new WP_REST_Response(
				array( 'message' => 'Request body must contain "value" field' ),
				400
			);
		}

		$this->settings_service->set_setting( $setting_key, $body['value'] );
		return new WP_REST_Response(
			array(
				'message' => 'Setting updated successfully',
			),
			200
		);
	}

	/**
	 * Deletes a specific setting.
	 *
	 * @param WP_REST_Request<array{setting_key?: string}> $request The REST request.
	 * @return WP_REST_Response | WP_Error The response indicating the result of the deletion.
	 */
	public function delete_setting( $request ): WP_REST_Response|WP_Error {
		$setting_key = $request->get_param( 'setting_key' );

		if ( ! is_string( $setting_key ) || empty( $setting_key ) ) {
			return new WP_Error(
				'invalid_setting_key',
				__( 'Invalid setting key', 'etch' ),
				array( 'status' => 400 )
			);
		}

		$this->settings_service->delete_setting( $setting_key );
		return new WP_REST_Response(
			array(
				'message' => 'Setting deleted successfully',
			),
			200
		);
	}
}
