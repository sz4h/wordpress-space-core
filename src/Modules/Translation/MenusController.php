<?php

namespace Space\Core\Modules\Translation;

defined( 'ABSPATH' ) || exit;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class MenusController {

	private const NS = 'space-core/v1';

	public function register_routes(): void {
		// GET/POST /en/translation/menus
		// GET/POST /en/translation/menus/3   (menu_id scoped)
		register_rest_route(
			self::NS,
			'/(?P<lang>[a-z][a-z0-9_-]*)/translation/menus(?:/(?P<menu_id>\d+))?',
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
						'menu_id'     => [
							'required'          => false,
							'sanitize_callback' => 'absint',
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
						'lang' => [
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
		$target_lang = $request->get_param( 'target_lang' );
		$menu_id     = $request->get_param( 'menu_id' );
		$menu_ids    = $menu_id ? [ absint( $menu_id ) ] : [];

		try {
			$adapter = PluginDetector::adapter();
		} catch ( \RuntimeException $e ) {
			return new WP_Error( 'no_multilingual_plugin', $e->getMessage(), [ 'status' => 503 ] );
		}

		$rows   = $adapter->get_menu_items_with_groups( $menu_ids, $source_lang );
		$result = [];

		foreach ( $rows as $row ) {
			$translations = maybe_unserialize( $row->trans_desc );

			if ( is_array( $translations ) && array_key_exists( $target_lang, $translations ) ) {
				continue;
			}

			$result[] = [
				'id'        => (int) $row->ID,
				'title'     => $row->post_title,
				'url'       => $row->url ?? '',
				'menu_id'   => (int) $row->menu_id,
				'menu_name' => $row->menu_name,
			];
		}

		return new WP_REST_Response( $result, 200 );
	}

	public function create_translations( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$target_lang = $request['lang'];
		$items       = $request->get_json_params();

		if ( ! is_array( $items ) || empty( $items ) ) {
			return new WP_Error( 'empty_items', __( 'Request body must be a non-empty JSON array.', 'space-core' ), [ 'status' => 400 ] );
		}

		try {
			$adapter = PluginDetector::adapter();
		} catch ( \RuntimeException $e ) {
			return new WP_Error( 'no_multilingual_plugin', $e->getMessage(), [ 'status' => 503 ] );
		}

		$created = 0;
		$skipped = 0;
		$errors  = [];

		foreach ( $items as $item ) {
			$source_id = isset( $item['id'] ) ? absint( $item['id'] ) : 0;
			$title     = isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '';
			$url       = isset( $item['url'] ) ? esc_url_raw( $item['url'] ) : '';
			$menu_id   = isset( $item['menu_id'] ) ? absint( $item['menu_id'] ) : 0;

			if ( ! $source_id || '' === $title ) {
				++$skipped;
				continue;
			}

			$source_post = get_post( $source_id );
			if ( ! $source_post || 'nav_menu_item' !== $source_post->post_type ) {
				$errors[] = sprintf(
					/* translators: %d: item ID */
					__( 'Item %d is not a valid nav_menu_item.', 'space-core' ),
					$source_id
				);
				++$skipped;
				continue;
			}

			$source_lang = pll_get_post_language( $source_id );

			if ( ! $source_lang ) {
				$errors[] = sprintf(
					/* translators: %d: item ID */
					__( 'Menu item %d has no language assigned.', 'space-core' ),
					$source_id
				);
				++$skipped;
				continue;
			}

			// Skip if translation already exists.
			$existing = pll_get_post_translations( $source_id );
			if ( isset( $existing[ $target_lang ] ) ) {
				++$skipped;
				continue;
			}

			// Resolve menu_id from existing term relationship if not supplied.
			if ( ! $menu_id ) {
				$menus = wp_get_post_terms( $source_id, 'nav_menu' );
				if ( ! is_wp_error( $menus ) && ! empty( $menus ) ) {
					$menu_id = (int) $menus[0]->term_id;
				}
			}

			$new_id = $adapter->insert_menu_item_translation( $source_id, $title, $url, $menu_id, $target_lang, $source_lang );

			if ( is_wp_error( $new_id ) ) {
				$errors[] = sprintf(
					/* translators: 1: source item ID, 2: error message */
					__( 'Item %1$d: %2$s', 'space-core' ),
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
}
