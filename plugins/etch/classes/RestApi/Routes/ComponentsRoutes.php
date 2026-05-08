<?php
/**
 * ComponentsRoutes.php
 *
 * This file contains the ComponentsRoutes class which defines REST API routes for handling synced components (wp_block).
 *
 * PHP version 7.4+
 *
 * @category  Plugin
 * @package   Etch\RestApi\Routes
 */

declare(strict_types=1);
namespace Etch\RestApi\Routes;

use Etch\Helpers\Flag;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * ComponentsRoutes
 *
 * This class defines REST API endpoints for retrieving and managing synced components (wp_block CPT).
 *
 * @package Etch\RestApi\Routes
 */
class ComponentsRoutes extends BaseRoute {

	/**
	 * Returns the route definitions for components endpoints.
	 *
	 * @return array<array{route: string, methods: string|array<string>, callback: callable, permission_callback?: callable, args?: array<string, mixed>}>
	 */
	protected function get_routes(): array {
		return array(
			array(
				'route'               => '/components',
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_components' ),
			),
			array(
				'route'               => '/components/list',
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_components_list' ),
			),
			array(
				'route'               => '/components',
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_component' ),
			),
			array(
				'route'               => '/components/(?P<id>\d+)',
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_component' ),
			),
			array(
				'route'               => '/components/(?P<id>\d+)',
				'methods'             => 'PUT',
				'callback'            => array( $this, 'update_component' ),
			),
			array(
				'route'               => '/components/(?P<id>\d+)',
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'delete_component' ),
			),
		);
	}

	/**
	 * Get all components.
	 *
	 * @param WP_REST_Request<array{}> $request The REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_components( $request ) {
		$args = array(
			'post_type'      => 'wp_block',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'meta_query'     => array(
				array(
					'key'     => 'etch_component_html_key',
					'compare' => 'EXISTS',
				),
			),
		);

		$posts = get_posts( $args );

		$components = array_map(
			function ( $post ) {
				$properties = get_post_meta( $post->ID, 'etch_component_properties', true );
				if ( ! is_array( $properties ) ) {
					$properties = array();
				}

				return array(
					'id'          => $post->ID,
					'name'        => $post->post_title,
					'key'         => get_post_meta( $post->ID, 'etch_component_html_key', true ) ?? '',
					'blocks'      => parse_blocks( $post->post_content ),
					'properties'  => $properties,
					'description' => $post->post_excerpt,
					'legacyId'    => get_post_meta( $post->ID, 'etch_component_legacy_id', true ),
				);
			},
			$posts
		);

		return new WP_REST_Response( $components, 200 );
	}

	/**
	 * Get all components as a list of names and keys.
	 *
	 * @param WP_REST_Request<array{}> $request The REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_components_list( $request ) {
		$args = array(
			'post_type'      => 'wp_block',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'meta_query'     => array(
				array(
					'key'     => 'etch_component_html_key',
					'compare' => 'EXISTS',
				),
			),
		);

		$posts = get_posts( $args );

		$components = array_map(
			function ( $post ) {
				return array(
					'id'   => $post->ID,
					'key'  => get_post_meta( $post->ID, 'etch_component_html_key', true ) ?? '',
					'name' => $post->post_title,
					'legacyId' => get_post_meta( $post->ID, 'etch_component_legacy_id', true ),
				);
			},
			$posts
		);

		return new WP_REST_Response( $components, 200 );
	}

	/**
	 * Get a single component.
	 *
	 * @param WP_REST_Request<array{id: string}> $request The REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_component( $request ) {
		$id = $request->get_param( 'id' );
		$post = get_post( (int) $id );

		if ( null === $post || 'wp_block' !== $post->post_type ) {
			return new WP_Error( 'not_found', 'Component not found.', array( 'status' => 404 ) );
		}

		$key = get_post_meta( $post->ID, 'etch_component_html_key', true ) ?? '';
		if ( ! $key ) {
			// If not set, return a 412 error
			return new WP_Error( 'not_found', 'Requested pattern is not a etch component.', array( 'status' => 412 ) );
		}

		$properties = get_post_meta( $post->ID, 'etch_component_properties', true );
		if ( ! is_array( $properties ) ) {
			$properties = array();
		}

		$response_data = array(
			'id'          => $post->ID,
			'name'        => $post->post_title,
			'key'         => get_post_meta( $post->ID, 'etch_component_html_key', true ) ?? '',
			'blocks'      => parse_blocks( $post->post_content ),
			'properties'  => $properties,
			'description' => $post->post_excerpt,
		);

		return new WP_REST_Response( $response_data, 200 );
	}

	/**
	 * Create a component.
	 *
	 * @param WP_REST_Request<array{name?: string, blocks?: array<int|string, mixed>, description?: string, properties?: array<int|string, mixed>, key?: string}> $request The REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_component( $request ) {
		if ( Flag::is_on( 'ENABLE_WAF_BLOCK_REQUEST_WORKAROUND' ) ) {
			$body = json_decode( $request->get_body(), true );
			if ( is_string( $body ) ) {
				$body = json_decode( $body, true );
			}
			if ( ! is_array( $body ) ) {
				$body = $request->get_json_params();
			}
		} else {
			$body = $request->get_json_params();
		}

		$post_data = array(
			'post_type'    => 'wp_block',
			'post_title'   => sanitize_text_field( $body['name'] ?? 'New Component' ),
			'post_content' => wp_slash( serialize_blocks( $body['blocks'] ?? array() ) ),
			'post_excerpt' => sanitize_text_field( $body['description'] ?? '' ),
			'post_status'  => 'publish',
		);

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		if ( isset( $body['properties'] ) ) {
			update_post_meta( $post_id, 'etch_component_properties', $body['properties'] );
		}

		if ( isset( $body['key'] ) ) {
			update_post_meta( $post_id, 'etch_component_html_key', $body['key'] );
		}

		/**
		 * Create a GET request for retrieving the component.
		 *
		 * @var WP_REST_Request<array{id: string}> $get_request
		 */
		$get_request = new WP_REST_Request( 'GET' );
		$get_request->set_url_params( array( 'id' => (string) $post_id ) );

		return $this->get_component( $get_request );
	}

	/**
	 * Update a component.
	 *
	 * @param WP_REST_Request<array{id: string, name?: string, blocks?: array<string, mixed>, description?: string, properties?: array<string, mixed>, key?: string}> $request The REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_component( $request ) {
		$id = (int) $request->get_param( 'id' );
		$post = get_post( $id );

		if ( null === $post || 'wp_block' !== $post->post_type ) {
			return new WP_Error( 'not_found', 'Component not found.', array( 'status' => 404 ) );
		}

		if ( Flag::is_on( 'ENABLE_WAF_BLOCK_REQUEST_WORKAROUND' ) ) {
			$body = json_decode( $request->get_body(), true );
			if ( is_string( $body ) ) {
				$body = json_decode( $body, true );
			}
			if ( ! is_array( $body ) ) {
				$body = $request->get_json_params();
			}
		} else {
			$body = $request->get_json_params();
		}

		$post_data = array(
			'ID' => $id,
		);

		if ( isset( $body['name'] ) ) {
			$post_data['post_title'] = sanitize_text_field( $body['name'] );
		}

		if ( isset( $body['blocks'] ) ) {
			$post_data['post_content'] = wp_slash( serialize_blocks( $body['blocks'] ) );
		}

		if ( isset( $body['description'] ) ) {
			$post_data['post_excerpt'] = sanitize_text_field( $body['description'] );
		}

		if ( isset( $body['key'] ) ) {
			update_post_meta( $id, 'etch_component_html_key', $body['key'] );
		}

		$post_id = wp_update_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		if ( isset( $body['properties'] ) ) {
			update_post_meta( $id, 'etch_component_properties', $body['properties'] );
		}

		/**
		 * Create a GET request for retrieving the component.
		 *
		 * @var WP_REST_Request<array{id: string}> $get_request
		 */
		$get_request = new WP_REST_Request( 'GET' );
		$get_request->set_url_params( array( 'id' => (string) $id ) );

		return $this->get_component( $get_request );
	}

	/**
	 * Delete a component.
	 *
	 * @param WP_REST_Request<array{id: string}> $request The REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_component( $request ) {
		$id = (int) $request->get_param( 'id' );
		$post = get_post( $id );

		if ( null === $post || 'wp_block' !== $post->post_type ) {
			return new WP_Error( 'not_found', 'Component not found.', array( 'status' => 404 ) );
		}

		$deleted = wp_delete_post( $id, true );

		if ( false === $deleted ) {
			return new WP_Error( 'delete_failed', 'Failed to delete component.', array( 'status' => 500 ) );
		}

		return new WP_REST_Response( array( 'message' => 'Component deleted successfully.' ), 200 );
	}
}
