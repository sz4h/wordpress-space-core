<?php

namespace Space\Core\Modules\Translation;

defined( 'ABSPATH' ) || exit;

use WP_Error;

/**
 * WPML adapter — uses wp_icl_translations for reads and WPML's public
 * action/filter API for writes.
 */
class WpmlAdapter implements AdapterInterface {

	// -------------------------------------------------------------------------
	// Languages
	// -------------------------------------------------------------------------

	public function get_languages(): array {
		$languages = apply_filters( 'wpml_active_languages', null, [] );

		if ( ! is_array( $languages ) ) {
			return [];
		}

		$default = apply_filters( 'wpml_default_language', null );
		$result  = [];

		foreach ( $languages as $lang ) {
			$result[] = [
				'slug'    => $lang['language_code'],
				'name'    => $lang['translated_name'] ?? $lang['native_name'],
				'default' => $lang['language_code'] === $default,
			];
		}

		return $result;
	}

	// -------------------------------------------------------------------------
	// Terms
	// -------------------------------------------------------------------------

	/**
	 * Two queries total (no N+1):
	 *  1. Fetch source-lang terms with their WPML trid.
	 *  2. Fetch all translation records for those trids in one IN query.
	 * Attaches a serialized trans_desc so the controller's maybe_unserialize()
	 * + array_key_exists() filter works without modification.
	 */
	public function get_terms_with_groups( string $taxonomy, string $source_lang ): array {
		global $wpdb;

		$icl   = $wpdb->prefix . 'icl_translations';
		$etype = 'tax_' . $taxonomy;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$terms = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.term_id, t.name, t.slug, tr.trid
				FROM $wpdb->terms AS t
				INNER JOIN $wpdb->term_taxonomy AS tt
					ON t.term_id = tt.term_id AND tt.taxonomy = %s
				INNER JOIN $icl AS tr
					ON tr.element_id = t.term_id
					AND tr.element_type = %s
					AND tr.language_code = %s",
				$taxonomy,
				$etype,
				$source_lang
			)
		);

		if ( empty( $terms ) ) {
			return [];
		}

		$trids        = array_unique( array_column( $terms, 'trid' ) );
		$placeholders = implode( ',', array_fill( 0, count( $trids ), '%d' ) );

		$trans_rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT trid, language_code, element_id
				FROM $icl
				WHERE element_type = %s AND trid IN ($placeholders)",
				array_merge( [ $etype ], $trids )
			)
		);
		// phpcs:enable

		// Build trid => [lang => term_id] map.
		$trid_map = [];
		foreach ( $trans_rows as $row ) {
			$trid_map[ $row->trid ][ $row->language_code ] = (int) $row->element_id;
		}

		// Attach serialized trans_desc so the TermsController filter works as-is.
		foreach ( $terms as $term ) {
			$group            = $trid_map[ $term->trid ] ?? [];
			$term->trans_desc = ! empty( $group ) ? maybe_serialize( $group ) : null;
		}

		return $terms;
	}

	public function insert_term_translation(
		string $name,
		string $taxonomy,
		string $slug,
		string $target_lang,
		int $source_id,
		string $source_lang
	): int|WP_Error {
		$trid = apply_filters( 'wpml_element_trid', null, $source_id, 'tax_' . $taxonomy );

		if ( empty( $trid ) ) {
			return new WP_Error(
				'wpml_no_trid',
				sprintf(
					/* translators: %d: term ID */
					__( 'Could not find WPML translation group for term %d.', 'space-core' ),
					$source_id
				)
			);
		}

		$result = wp_insert_term( $name, $taxonomy, [ 'slug' => $slug ] );

		if ( is_wp_error( $result ) ) {
			if ( 'term_exists' === $result->get_error_code() ) {
				$data   = $result->get_error_data();
				$new_id = (int) ( is_array( $data ) ? $data['term_id'] : $data );
			} else {
				return $result;
			}
		} else {
			$new_id = (int) $result['term_id'];
		}

		do_action(
			'wpml_set_element_language_details',
			[
				'element_id'           => $new_id,
				'element_type'         => 'tax_' . $taxonomy,
				'trid'                 => $trid,
				'language_code'        => $target_lang,
				'source_language_code' => $source_lang,
			]
		);

		return $new_id;
	}

	// -------------------------------------------------------------------------
	// Menus
	// -------------------------------------------------------------------------

	/**
	 * Same two-query pattern as get_terms_with_groups but for nav_menu_items.
	 */
	public function get_menu_items_with_groups( array $menu_ids, string $source_lang ): array {
		global $wpdb;

		$icl         = $wpdb->prefix . 'icl_translations';
		$menu_filter = '';

		if ( ! empty( $menu_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $menu_ids ), '%d' ) );
			$menu_filter  = "AND t_menu.term_id IN ($placeholders)";
		}

		$args = array_merge( [ $source_lang ], $menu_ids );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$items = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT p.ID, p.post_title,
					pm_url.meta_value AS url,
					t_menu.term_id AS menu_id,
					t_menu.name AS menu_name,
					tr.trid
				FROM $wpdb->posts AS p
				INNER JOIN $wpdb->term_relationships AS tr_menu
					ON tr_menu.object_id = p.ID
				INNER JOIN $wpdb->term_taxonomy AS tt_menu
					ON tr_menu.term_taxonomy_id = tt_menu.term_taxonomy_id
					AND tt_menu.taxonomy = 'nav_menu'
				INNER JOIN $wpdb->terms AS t_menu
					ON tt_menu.term_id = t_menu.term_id
				INNER JOIN $icl AS tr
					ON tr.element_id = p.ID
					AND tr.element_type = 'post_nav_menu_item'
					AND tr.language_code = %s
				LEFT JOIN $wpdb->postmeta AS pm_url
					ON pm_url.post_id = p.ID AND pm_url.meta_key = '_menu_item_url'
				WHERE p.post_type = 'nav_menu_item'
					AND p.post_status = 'publish'
					$menu_filter",
				...$args
			)
		);

		if ( empty( $items ) ) {
			return [];
		}

		$trids        = array_unique( array_column( $items, 'trid' ) );
		$placeholders = implode( ',', array_fill( 0, count( $trids ), '%d' ) );

		$trans_rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT trid, language_code, element_id
				FROM $icl
				WHERE element_type = 'post_nav_menu_item' AND trid IN ($placeholders)",
				$trids
			)
		);
		// phpcs:enable

		$trid_map = [];
		foreach ( $trans_rows as $row ) {
			$trid_map[ $row->trid ][ $row->language_code ] = (int) $row->element_id;
		}

		foreach ( $items as $item ) {
			$group             = $trid_map[ $item->trid ] ?? [];
			$item->trans_desc  = ! empty( $group ) ? maybe_serialize( $group ) : null;
		}

		return $items;
	}

	public function insert_menu_item_translation(
		int $source_id,
		string $title,
		string $url,
		int $menu_id,
		string $target_lang,
		string $source_lang
	): int|WP_Error {
		$trid = apply_filters( 'wpml_element_trid', null, $source_id, 'post_nav_menu_item' );

		if ( empty( $trid ) ) {
			return new WP_Error(
				'wpml_no_trid',
				sprintf(
					/* translators: %d: item ID */
					__( 'Could not find WPML translation group for menu item %d.', 'space-core' ),
					$source_id
				)
			);
		}

		$new_id = $this->duplicate_post( $source_id, $title, 'publish' );
		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}

		if ( '' !== $url ) {
			update_post_meta( $new_id, '_menu_item_url', esc_url_raw( $url ) );
			update_post_meta( $new_id, '_menu_item_type', 'custom' );
		}

		wp_set_object_terms( $new_id, $menu_id, 'nav_menu' );

		do_action(
			'wpml_set_element_language_details',
			[
				'element_id'           => $new_id,
				'element_type'         => 'post_nav_menu_item',
				'trid'                 => $trid,
				'language_code'        => $target_lang,
				'source_language_code' => $source_lang,
			]
		);

		return $new_id;
	}

	// -------------------------------------------------------------------------
	// Posts / Pages / CPTs
	// -------------------------------------------------------------------------

	public function get_posts_missing_translation( string $post_type, string $source_lang, string $target_lang ): array {
		global $wpdb;

		$icl   = $wpdb->prefix . 'icl_translations';
		$etype = 'post_' . $post_type;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT p.ID, p.post_title, p.post_name, p.post_excerpt, p.post_content
				FROM $wpdb->posts AS p
				INNER JOIN $icl AS tr
					ON tr.element_id = p.ID
					AND tr.element_type = %s
					AND tr.language_code = %s
				WHERE p.post_status IN ('publish', 'draft')
					AND tr.trid NOT IN (
						SELECT trid FROM $icl
						WHERE language_code = %s AND element_type = %s
					)",
				$etype,
				$source_lang,
				$target_lang,
				$etype
			)
		);
		// phpcs:enable

		return is_array( $rows ) ? $rows : [];
	}

	public function get_post_language( int $post_id ): string {
		$lang = apply_filters(
			'wpml_element_language_code',
			null,
			[ 'element_id' => $post_id, 'element_type' => 'post' ]
		);

		return is_string( $lang ) ? $lang : '';
	}

	public function has_post_translation( int $post_id, string $target_lang ): bool {
		$translations = apply_filters( 'wpml_get_element_translations', null, $post_id, 'post' );

		if ( ! is_array( $translations ) ) {
			return false;
		}

		foreach ( $translations as $t ) {
			if ( isset( $t->language_code ) && $t->language_code === $target_lang ) {
				return true;
			}
		}

		return false;
	}

	public function insert_post_translation(
		int $source_id,
		array $post_data,
		string $target_lang,
		string $source_lang
	): int|WP_Error {
		$source_post = get_post( $source_id );
		if ( ! $source_post ) {
			return new WP_Error( 'source_not_found', __( 'Source post not found.', 'space-core' ) );
		}

		$trid = apply_filters( 'wpml_element_trid', null, $source_id, 'post_' . $source_post->post_type );

		if ( empty( $trid ) ) {
			return new WP_Error(
				'wpml_no_trid',
				sprintf(
					/* translators: %d: post ID */
					__( 'Could not find WPML translation group for post %d.', 'space-core' ),
					$source_id
				)
			);
		}

		$new_id = wp_insert_post(
			[
				'post_title'   => $post_data['title'],
				'post_name'    => $post_data['slug'] ?: '',
				'post_excerpt' => $post_data['excerpt'] !== '' ? $post_data['excerpt'] : $source_post->post_excerpt,
				'post_content' => $post_data['content'] !== '' ? $post_data['content'] : $source_post->post_content,
				'post_type'    => $source_post->post_type,
				'post_status'  => 'draft',
				'post_author'  => $source_post->post_author,
			],
			true
		);

		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}

		foreach ( get_post_meta( $source_id ) as $key => $values ) {
			foreach ( $values as $value ) {
				add_post_meta( $new_id, $key, maybe_unserialize( $value ) );
			}
		}

		foreach ( $post_data['meta'] ?? [] as $key => $value ) {
			update_post_meta( $new_id, $key, $value );
		}

		do_action(
			'wpml_set_element_language_details',
			[
				'element_id'           => $new_id,
				'element_type'         => 'post_' . $source_post->post_type,
				'trid'                 => $trid,
				'language_code'        => $target_lang,
				'source_language_code' => $source_lang,
			]
		);

		return $new_id;
	}

	// -------------------------------------------------------------------------

	/**
	 * Creates a new post by duplicating source meta (excluding URL meta).
	 * Shared by insert_menu_item_translation for nav_menu_item duplication.
	 */
	private function duplicate_post( int $source_id, string $title, string $status ): int|WP_Error {
		$source = get_post( $source_id );
		if ( ! $source ) {
			return new WP_Error( 'source_not_found', __( 'Source post not found.', 'space-core' ) );
		}

		$new_id = wp_insert_post(
			[
				'post_title'  => $title,
				'post_type'   => $source->post_type,
				'post_status' => $status,
			],
			true
		);

		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}

		foreach ( get_post_meta( $source_id ) as $key => $values ) {
			if ( '_menu_item_url' === $key ) {
				continue;
			}
			foreach ( $values as $value ) {
				add_post_meta( $new_id, $key, maybe_unserialize( $value ) );
			}
		}

		return $new_id;
	}
}
