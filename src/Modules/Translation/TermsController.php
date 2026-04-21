<?php

namespace Space\Core\Modules\Translation;

defined( 'ABSPATH' ) || exit;

use RuntimeException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class TermsController {

	private const NS = 'space-core/v1';

	public function register_routes(): void {
		register_rest_route(
			self::NS,
			'/(?P<lang>[a-z][a-z0-9_-]*)/translation/(?P<taxonomy>[a-zA-Z][a-zA-Z0-9_-]*)/terms',
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
						'taxonomy'    => [
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
						'lang'     => [
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						],
						'taxonomy' => [
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
		$taxonomy    = $request['taxonomy'];
		$target_lang = $request->get_param( 'target_lang' );

		try {
			$adapter = PluginDetector::adapter();
		} catch ( RuntimeException $e ) {
			return new WP_Error( 'no_multilingual_plugin', $e->getMessage(), [ 'status' => 503 ] );
		}

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return new WP_Error( 'invalid_taxonomy', __( 'The requested taxonomy does not exist.', 'space-core' ), [ 'status' => 400 ] );
		}

		$rows   = $adapter->get_terms_with_groups( $taxonomy, $source_lang );
		$result = [];

		foreach ( $rows as $row ) {
			$translations = maybe_unserialize( $row->trans_desc );

			if ( is_array( $translations ) && array_key_exists( $target_lang, $translations ) ) {
				continue;
			}

			$entry = [
				'id'   => (int) $row->term_id,
				'name' => $row->name,
				'slug' => $row->slug,
			];

			$meta = MetaFilter::filter_term( (int) $row->term_id, $taxonomy );
			if ( ! empty( $meta ) ) {
				$entry['meta'] = $meta;
			}

			$result[] = $entry;
		}

		return new WP_REST_Response( $result, 200 );
	}

	public function create_translations( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$target_lang = $request['lang'];
		$taxonomy    = $request['taxonomy'];
		$items       = $request->get_json_params();

		if ( ! is_array( $items ) || empty( $items ) ) {
			return new WP_Error( 'empty_items', __( 'Request body must be a non-empty JSON array.', 'space-core' ), [ 'status' => 400 ] );
		}

		try {
			$adapter = PluginDetector::adapter();
		} catch ( RuntimeException $e ) {
			return new WP_Error( 'no_multilingual_plugin', $e->getMessage(), [ 'status' => 503 ] );
		}

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return new WP_Error( 'invalid_taxonomy', __( 'The requested taxonomy does not exist.', 'space-core' ), [ 'status' => 400 ] );
		}

		$created = 0;
		$skipped = 0;
		$errors  = [];

		foreach ( $items as $item ) {
			$source_id = isset( $item['id'] ) ? absint( $item['id'] ) : 0;
			$name      = isset( $item['name'] ) ? sanitize_text_field( $item['name'] ) : '';
			$slug      = isset( $item['slug'] ) ? sanitize_title( $item['slug'] ) : '';

			if ( ! $source_id || '' === $name ) {
				++ $skipped;
				continue;
			}

			$source_lang = $adapter->get_term_language( $source_id );

			if ( ! $source_lang ) {
				$errors[] = sprintf(
				/* translators: %d: source term ID */
					__( 'Term %d has no language assigned.', 'space-core' ),
					$source_id
				);
				++ $skipped;
				continue;
			}

			if ( $adapter->has_term_translation( $source_id, $target_lang ) ) {
				++ $skipped;
				continue;
			}

			$meta   = isset( $item['meta'] ) && is_array( $item['meta'] ) ? $item['meta'] : [];
			$new_id = $adapter->insert_term_translation( $name, $taxonomy, $slug, $target_lang, $source_id, $source_lang, $meta );

			if ( is_wp_error( $new_id ) ) {
				$errors[] = sprintf(
				/* translators: 1: source term ID, 2: error message */
					__( 'Term %1$d: %2$s', 'space-core' ),
					$source_id,
					$new_id->get_error_message()
				);
				continue;
			}

			++ $created;
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
}
