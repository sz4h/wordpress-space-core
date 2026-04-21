<?php

namespace Space\Core\Modules\Translation;

defined( 'ABSPATH' ) || exit;

use RuntimeException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class PostsController {

	private const NS = 'space-core/v1';

	public function register_routes(): void {
		register_rest_route(
			self::NS,
			'/(?P<lang>[a-z][a-z0-9_-]*)/translation/(?P<post_type>[a-zA-Z][a-zA-Z0-9_-]*)/posts',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_missing' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args'                => [
						'lang'        => [
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						],
						'post_type'   => [
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						],
						'target_lang' => [
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
							'description'       => __( 'Target language slug to check for missing translations.', 'space-core' ),
						],
					],
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'create_translations' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args'                => [
						'lang'      => [
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						],
						'post_type' => [
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						],
					],
				],
			]
		);
	}

	public function check_permission(): bool|WP_Error {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to manage translations.', 'space-core' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return true;
	}

	public function get_missing( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$source_lang = $request['lang'];
		$post_type   = $request['post_type'];
		$target_lang = $request->get_param( 'target_lang' );

		if ( ! post_type_exists( $post_type ) ) {
			return new WP_Error(
				'invalid_post_type',
				__( 'The requested post type does not exist.', 'space-core' ),
				[ 'status' => 400 ]
			);
		}

		try {
			$adapter = PluginDetector::adapter();
		} catch ( RuntimeException $e ) {
			return new WP_Error( 'no_multilingual_plugin', $e->getMessage(), [ 'status' => 503 ] );
		}

		$rows   = $adapter->get_posts_missing_translation( $post_type, $source_lang, $target_lang );
		$result = [];

		foreach ( $rows as $row ) {
			$id     = (int) $row->ID;
			$result[] = [
				'id'      => $id,
				'title'   => $row->post_title,
				'slug'    => $row->post_name,
				'excerpt' => $row->post_excerpt,
				'content' => $row->post_content,
				'meta'    => MetaFilter::filter( $id, $post_type ),
			];
		}

		return new WP_REST_Response( $result, 200 );
	}

	public function create_translations( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$target_lang = $request['lang'];
		$post_type   = $request['post_type'];
		$items       = $request->get_json_params();

		if ( ! is_array( $items ) || empty( $items ) ) {
			return new WP_Error(
				'empty_items',
				__( 'Request body must be a non-empty JSON array.', 'space-core' ),
				[ 'status' => 400 ]
			);
		}

		if ( ! post_type_exists( $post_type ) ) {
			return new WP_Error(
				'invalid_post_type',
				__( 'The requested post type does not exist.', 'space-core' ),
				[ 'status' => 400 ]
			);
		}

		try {
			$adapter = PluginDetector::adapter();
		} catch ( RuntimeException $e ) {
			return new WP_Error( 'no_multilingual_plugin', $e->getMessage(), [ 'status' => 503 ] );
		}

		$created = 0;
		$skipped = 0;
		$errors  = [];

		foreach ( $items as $item ) {
			$source_id = isset( $item['id'] ) ? absint( $item['id'] ) : 0;
			$title     = isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '';

			if ( ! $source_id || '' === $title ) {
				++$skipped;
				continue;
			}

			$source_post = get_post( $source_id );
			if ( ! $source_post || $source_post->post_type !== $post_type ) {
				$errors[] = sprintf(
					/* translators: %d: post ID */
					__( 'Post %d does not exist or has the wrong post type.', 'space-core' ),
					$source_id
				);
				++$skipped;
				continue;
			}

			$source_lang = $adapter->get_post_language( $source_id );
			if ( ! $source_lang ) {
				$errors[] = sprintf(
					/* translators: %d: post ID */
					__( 'Post %d has no language assigned.', 'space-core' ),
					$source_id
				);
				++$skipped;
				continue;
			}

			// Skip if translation already exists.
			if ( $adapter->has_post_translation( $source_id, $target_lang ) ) {
				++$skipped;
				continue;
			}

			$post_data = [
				'title'   => $title,
				'slug'    => isset( $item['slug'] ) ? sanitize_title( $item['slug'] ) : '',
				'excerpt' => isset( $item['excerpt'] ) ? wp_kses_post( $item['excerpt'] ) : '',
				'content' => isset( $item['content'] ) ? wp_kses_post( $item['content'] ) : '',
				'meta'    => $this->sanitize_meta( $item['meta'] ?? [] ),
			];

			$new_id = $adapter->insert_post_translation( $source_id, $post_data, $target_lang, $source_lang );

			if ( is_wp_error( $new_id ) ) {
				$errors[] = sprintf(
					/* translators: 1: post ID, 2: error message */
					__( 'Post %1$d: %2$s', 'space-core' ),
					$source_id,
					$new_id->get_error_message()
				);
				continue;
			}

			++$created;
		}

		return new WP_REST_Response(
			[
				'created' => $created,
				'skipped' => $skipped,
				'errors'  => $errors,
			],
			200
		);
	}

	/** Sanitize submitted meta — keys sanitized, values cast to string. */
	private function sanitize_meta( mixed $meta ): array {
		if ( ! is_array( $meta ) ) {
			return [];
		}

		$clean = [];
		foreach ( $meta as $key => $value ) {
			$clean[ sanitize_key( $key ) ] = sanitize_text_field( (string) $value );
		}

		return $clean;
	}
}
