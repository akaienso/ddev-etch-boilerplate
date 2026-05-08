<?php
/**
 * PatternsRoutes.php
 *
 * This file contains the PatternsRoutes class which defines REST API routes for handling synced patterns (wp_block).
 *
 * PHP version 7.4+
 *
 * @category  Plugin
 * @package   Etch\RestApi\Routes
 */

declare(strict_types=1);
namespace Etch\RestApi\Routes;

use Etch\Helpers\Logger;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * PatternsRoutes
 *
 * This class defines REST API endpoints for retrieving and managing synced patterns (wp_block CPT).
 *
 * @package Etch\RestApi\Routes
 */
class PatternsRoutes extends BaseRoute {

	/**
	 * Filter out empty blocks (freeform HTML) from parsed blocks.
	 *
	 * WordPress parse_blocks() returns null blockName for whitespace/newlines
	 * between blocks. This method filters those out to return only actual blocks.
	 *
	 * @param array<int|string, array<string, mixed>> $blocks The parsed blocks.
	 * @return array<int, array<string, mixed>> Filtered blocks with only non-empty entries.
	 */
	private function filter_empty_blocks( array $blocks ): array {
		$filtered = array_filter(
			$blocks,
			static function ( $block ) {
				return isset( $block['blockName'] ) && null !== $block['blockName'];
			}
		);

		return array_values( $filtered );
	}

	/**
	 * Returns the route definitions for patterns endpoints.
	 *
	 * @return array<array{route: string, methods: string|array<string>, callback: callable, permission_callback?: callable, args?: array<string, mixed>}>
	 */
	protected function get_routes(): array {
		return array(
			array(
				'route'               => '/patterns',
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_patterns' ),
			),
			array(
				'route'               => '/patterns/(?P<id>\d+)',
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_pattern' ),
			),
			array(
				'route'               => '/patterns',
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_pattern' ),
			),
			array(
				'route'               => '/patterns/(?P<id>\d+)',
				'methods'             => 'PUT',
				'callback'            => array( $this, 'update_pattern' ),
			),
			array(
				'route'               => '/patterns/(?P<id>\d+)',
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'delete_pattern' ),
			),
		);
	}

	/**
	 * Get all patterns.
	 *
	 * @param WP_REST_Request<array{}> $request The REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_patterns( $request ) {

		$args = array(
			'post_type'      => 'wp_block',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'     => 'etch_component_html_key',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => 'etch_component_html_key',
					'value'   => '',
					'compare' => '=',
				),
			),
		);

		$posts = get_posts( $args );

		$patterns = array_map(
			function ( $post ) {
				$synced = get_post_meta( $post->ID, 'wp_pattern_sync_status', true ) !== 'unsynced';

				$parsed_blocks = $this->filter_empty_blocks( parse_blocks( $post->post_content ) );

				return array(
					'id'          => $post->ID,
					'name'        => $post->post_title,
					'blocks'      => $parsed_blocks,
					'description' => $post->post_excerpt,
					'synced'      => $synced,
					'categories'  => get_the_terms( $post->ID, 'wp_pattern_category' ),
				);
			},
			$posts
		);

		return new WP_REST_Response( $patterns, 200 );
	}

		/**
		 * Get a single pattern.
		 *
		 * @param WP_REST_Request<array{id: string}> $request The REST request object.
		 * @return WP_REST_Response|WP_Error
		 */
	public function get_pattern( $request ) {
		$id = $request->get_param( 'id' );
		$post = get_post( (int) $id );

		if ( null === $post || 'wp_block' !== $post->post_type ) {
			return new WP_Error( 'not_found', 'Pattern not found.', array( 'status' => 404 ) );
		}

		$synced = get_post_meta( $post->ID, 'wp_pattern_sync_status', true ) !== 'unsynced';

		$response_data = array(
			'id'          => $post->ID,
			'name'        => $post->post_title,
			'blocks'      => $this->filter_empty_blocks( parse_blocks( $post->post_content ) ),
			'description' => $post->post_excerpt,
			'synced'      => $synced,
			'categories'  => get_the_terms( $post->ID, 'wp_pattern_category' ),
		);

		return new WP_REST_Response( $response_data, 200 );
	}

	/**
	 * Create a pattern.
	 *
	 * @param WP_REST_Request<array{name?: string, blocks?: array<int|string, mixed>, description?: string, synced?: boolean}> $request The REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_pattern( $request ) {
		$body = $request->get_json_params();

		$post_data = array(
			'post_type'    => 'wp_block',
			'post_title'   => sanitize_text_field( $body['name'] ?? 'New Pattern' ),
			'post_content' => wp_slash( serialize_blocks( $body['blocks'] ?? array() ) ),
			'post_excerpt' => sanitize_text_field( $body['description'] ?? '' ),
			'post_status'  => 'publish',
		);

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Ensure synced status is set correctly
		if ( isset( $body['synced'] ) && ! $body['synced'] ) {
			update_post_meta( $post_id, 'wp_pattern_sync_status', 'unsynced' );
		}

		if ( ! empty( $body['categories'] ) && is_array( $body['categories'] ) ) {
			wp_set_object_terms(
				$post_id,
				array_map( 'sanitize_text_field', $body['categories'] ),
				'wp_pattern_category'
			);
		}

		/**
		 * Create a GET request for retrieving the pattern.
		 *
		 * @var WP_REST_Request<array{id: string}> $get_request
		 */
		$get_request = new WP_REST_Request( 'GET' );
		$get_request->set_url_params( array( 'id' => (string) $post_id ) );

		return $this->get_pattern( $get_request );
	}

	/**
	 * Update a pattern.
	 *
	 * @param WP_REST_Request<array{id: string, name?: string, blocks?: array<string, mixed>, description?: string, properties?: array<string, mixed>, key?: string}> $request The REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_pattern( $request ) {
		$id = (int) $request->get_param( 'id' );
		$post = get_post( $id );

		if ( null === $post || 'wp_block' !== $post->post_type ) {
			return new WP_Error( 'not_found', 'Pattern not found.', array( 'status' => 404 ) );
		}

		$body = $request->get_json_params();

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

		if ( isset( $body['synced'] ) ) {
			if ( $body['synced'] ) {
				delete_post_meta( $id, 'wp_pattern_sync_status' );
			} else {
				update_post_meta( $id, 'wp_pattern_sync_status', 'unsynced' );
			}
		}

		if ( array_key_exists( 'categories', $body ) ) {
			if ( empty( $body['categories'] ) ) {
				// Explicit clear
				wp_set_object_terms( $id, array(), 'wp_pattern_category' );
			} else {
				// Replace
				wp_set_object_terms(
					$id,
					array_map( 'sanitize_text_field', (array) $body['categories'] ),
					'wp_pattern_category'
				);
			}
		}

		$post_id = wp_update_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		/**
		 * Create a GET request for retrieving the pattern.
		 *
		 * @var WP_REST_Request<array{id: string}> $get_request
		 */
		$get_request = new WP_REST_Request( 'GET' );
		$get_request->set_url_params( array( 'id' => (string) $id ) );

		return $this->get_pattern( $get_request );
	}

	/**
	 * Delete a pattern.
	 *
	 * @param WP_REST_Request<array{id: string}> $request The REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_pattern( $request ) {
		$id = (int) $request->get_param( 'id' );
		$post = get_post( $id );

		if ( null === $post || 'wp_block' !== $post->post_type ) {
			return new WP_Error( 'not_found', 'Pattern not found.', array( 'status' => 404 ) );
		}

		$deleted = wp_delete_post( $id, true );

		if ( false === $deleted ) {
			return new WP_Error( 'delete_failed', 'Failed to delete pattern.', array( 'status' => 500 ) );
		}

		return new WP_REST_Response( array( 'message' => 'Pattern deleted successfully.' ), 200 );
	}
}
