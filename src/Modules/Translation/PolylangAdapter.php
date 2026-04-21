<?php

namespace Space\Core\Modules\Translation;

use WP_Error;

defined( 'ABSPATH' ) || exit;

class PolylangAdapter implements AdapterInterface {

	public function get_languages(): array {
		$languages = pll_languages_list( [ 'fields' => false, 'hide_empty' => false ] );
		$default   = pll_default_language();
		$result    = [];

		foreach ( $languages as $lang ) {
			$result[] = [
				'slug'    => $lang->slug,
				'name'    => $lang->name,
				'default' => $lang->slug === $default,
			];
		}

		return $result;
	}

	/**
	 * Single JOIN query: fetch all terms in $taxonomy that belong to $source_lang,
	 * including their translation-group description so the controller can filter
	 * missing $target_lang in PHP without extra queries.
	 */
	public function get_terms_with_groups( string $taxonomy, string $source_lang ): array {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.term_id, t.name, t.slug, tt_tg.description AS trans_desc
				FROM {$wpdb->terms} AS t
				INNER JOIN {$wpdb->term_taxonomy} AS tt
					ON t.term_id = tt.term_id AND tt.taxonomy = %s
				INNER JOIN {$wpdb->term_relationships} AS tr_lang
					ON tr_lang.object_id = t.term_id
				INNER JOIN {$wpdb->term_taxonomy} AS tt_lang
					ON tr_lang.term_taxonomy_id = tt_lang.term_taxonomy_id
					AND tt_lang.taxonomy = 'language'
				INNER JOIN {$wpdb->terms} AS t_lang
					ON tt_lang.term_id = t_lang.term_id AND t_lang.slug = %s
				LEFT JOIN {$wpdb->term_relationships} AS tr_tg
					ON tr_tg.object_id = t.term_id
				LEFT JOIN {$wpdb->term_taxonomy} AS tt_tg
					ON tr_tg.term_taxonomy_id = tt_tg.term_taxonomy_id
					AND tt_tg.taxonomy = 'term_translations'",
				$taxonomy,
				$source_lang
			)
		);

		// phpcs:enable

		return is_array( $rows ) ? $rows : [];
	}

	public function get_term_language( int $term_id ): string {
		$lang = pll_get_term_language( $term_id );
		return is_string( $lang ) ? $lang : '';
	}

	public function has_term_translation( int $term_id, string $target_lang ): bool {
		$translations = pll_get_term_translations( $term_id );
		return isset( $translations[ $target_lang ] );
	}

	public function insert_term_translation(
		string $name,
		string $taxonomy,
		string $slug,
		string $target_lang,
		int $source_id,
		string $source_lang,
		array $meta = []
	): int|WP_Error {
		$result = pll_insert_term( $name, $taxonomy, $target_lang, [ 'slug' => $slug ] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( empty( $result['term_id'] ) ) {
			return new WP_Error( 'insert_failed', __( 'Term creation returned no ID.', 'space-core' ) );
		}

		$new_id = (int) $result['term_id'];

		pll_save_term_translations( [ $source_lang => $source_id, $target_lang => $new_id ] );

		foreach ( $meta as $key => $value ) {
			update_term_meta( $new_id, $key, $value );
		}

		return $new_id;
	}

	/**
	 * Single JOIN query: fetch nav_menu_item posts in $source_lang with their
	 * translation-group description so the controller can filter missing $target_lang.
	 */
	public function get_menu_items_with_groups( array $menu_ids, string $source_lang ): array {
		global $wpdb;

		$menu_filter = '';
		if ( ! empty( $menu_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $menu_ids ), '%d' ) );
			$menu_filter  = "AND t_menu.term_id IN ({$placeholders})";
		}

		$args = array_merge( [ $source_lang ], $menu_ids );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT p.ID, p.post_title,
					pm_url.meta_value AS url,
					t_menu.term_id AS menu_id,
					t_menu.name AS menu_name,
					tt_tg.description AS trans_desc
				FROM {$wpdb->posts} AS p
				INNER JOIN {$wpdb->term_relationships} AS tr_menu
					ON tr_menu.object_id = p.ID
				INNER JOIN {$wpdb->term_taxonomy} AS tt_menu
					ON tr_menu.term_taxonomy_id = tt_menu.term_taxonomy_id
					AND tt_menu.taxonomy = 'nav_menu'
				INNER JOIN {$wpdb->terms} AS t_menu
					ON tt_menu.term_id = t_menu.term_id
				INNER JOIN {$wpdb->term_relationships} AS tr_lang
					ON tr_lang.object_id = p.ID
				INNER JOIN {$wpdb->term_taxonomy} AS tt_lang
					ON tr_lang.term_taxonomy_id = tt_lang.term_taxonomy_id
					AND tt_lang.taxonomy = 'language'
				INNER JOIN {$wpdb->terms} AS t_lang
					ON tt_lang.term_id = t_lang.term_id AND t_lang.slug = %s
				LEFT JOIN {$wpdb->term_relationships} AS tr_tg
					ON tr_tg.object_id = p.ID
				LEFT JOIN {$wpdb->term_taxonomy} AS tt_tg
					ON tr_tg.term_taxonomy_id = tt_tg.term_taxonomy_id
					AND tt_tg.taxonomy = 'post_translations'
				LEFT JOIN {$wpdb->postmeta} AS pm_url
					ON pm_url.post_id = p.ID AND pm_url.meta_key = '_menu_item_url'
				WHERE p.post_type = 'nav_menu_item'
					AND p.post_status = 'publish'
					{$menu_filter}",
				...$args
			)
		);

		// phpcs:enable

		return is_array( $rows ) ? $rows : [];
	}

	// -------------------------------------------------------------------------
	// Posts / Pages / CPTs
	// -------------------------------------------------------------------------

	public function get_posts_missing_translation( string $post_type, string $source_lang, string $target_lang ): array {
		global $wpdb;

		// Single JOIN: fetch posts in source_lang with their translation-group
		// description; PHP filters out those already translated to target_lang.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, p.post_name, p.post_excerpt, p.post_content,
					tt_tg.description AS trans_desc
				FROM {$wpdb->posts} AS p
				INNER JOIN {$wpdb->term_relationships} AS tr_lang
					ON tr_lang.object_id = p.ID
				INNER JOIN {$wpdb->term_taxonomy} AS tt_lang
					ON tr_lang.term_taxonomy_id = tt_lang.term_taxonomy_id
					AND tt_lang.taxonomy = 'language'
				INNER JOIN {$wpdb->terms} AS t_lang
					ON tt_lang.term_id = t_lang.term_id AND t_lang.slug = %s
				LEFT JOIN {$wpdb->term_relationships} AS tr_tg
					ON tr_tg.object_id = p.ID
				LEFT JOIN {$wpdb->term_taxonomy} AS tt_tg
					ON tr_tg.term_taxonomy_id = tt_tg.term_taxonomy_id
					AND tt_tg.taxonomy = 'post_translations'
				WHERE p.post_type = %s
					AND p.post_status IN ('publish', 'draft')",
				$source_lang,
				$post_type
			)
		);
		// phpcs:enable

		if ( ! is_array( $rows ) ) {
			return [];
		}

		return array_values(
			array_filter(
				$rows,
				static function ( object $row ) use ( $target_lang ): bool {
					$translations = maybe_unserialize( $row->trans_desc );
					return ! ( is_array( $translations ) && array_key_exists( $target_lang, $translations ) );
				}
			)
		);
	}

	public function get_post_language( int $post_id ): string {
		$lang = pll_get_post_language( $post_id );
		return is_string( $lang ) ? $lang : '';
	}

	public function has_post_translation( int $post_id, string $target_lang ): bool {
		$translations = pll_get_post_translations( $post_id );
		return isset( $translations[ $target_lang ] );
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

		$new_id = wp_insert_post(
			[
				'post_title'   => $post_data['title'],
				'post_name'    => $post_data['slug'] ?: '',
				'post_excerpt' => $post_data['excerpt'] !== '' ? $post_data['excerpt'] : $source_post->post_excerpt,
				'post_content' => $post_data['content'] !== '' ? $post_data['content'] : $source_post->post_content,
				'post_type'    => $source_post->post_type,
				'post_status'  => 'publish',
				'post_author'  => $source_post->post_author,
			],
			true
		);

		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}

		// Copy all source meta first (preserves WooCommerce product structure, etc.).
		foreach ( get_post_meta( $source_id ) as $key => $values ) {
			foreach ( $values as $value ) {
				add_post_meta( $new_id, $key, maybe_unserialize( $value ) );
			}
		}

		// Overwrite with translated meta values.
		foreach ( $post_data['meta'] ?? [] as $key => $value ) {
			update_post_meta( $new_id, $key, $value );
		}

		pll_set_post_language( $new_id, $target_lang );
		pll_save_post_translations( [ $source_lang => $source_id, $target_lang => $new_id ] );

		return $new_id;
	}

	public function insert_menu_item_translation(
		int $source_id,
		string $title,
		string $url,
		int $menu_id,
		string $target_lang,
		string $source_lang
	): int|WP_Error {
		// Collect all meta from the source item.
		$source_meta = get_post_meta( $source_id );

		$new_id = wp_insert_post(
			[
				'post_title'  => $title,
				'post_type'   => 'nav_menu_item',
				'post_status' => 'publish',
			],
			true
		);

		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}

		// Copy all meta from source, overwriting URL with the translated one.
		$skip = [ '_menu_item_url' ];
		foreach ( $source_meta as $meta_key => $meta_values ) {
			if ( in_array( $meta_key, $skip, true ) ) {
				continue;
			}
			foreach ( $meta_values as $meta_value ) {
				add_post_meta( $new_id, $meta_key, maybe_unserialize( $meta_value ) );
			}
		}

		if ( '' !== $url ) {
			update_post_meta( $new_id, '_menu_item_url', esc_url_raw( $url ) );
			update_post_meta( $new_id, '_menu_item_type', 'custom' );
		}

		// Keep the same nav_menu assignment.
		wp_set_object_terms( $new_id, $menu_id, 'nav_menu' );

		// Assign language and link translations.
		pll_set_post_language( $new_id, $target_lang );
		pll_save_post_translations( [ $source_lang => $source_id, $target_lang => $new_id ] );

		return $new_id;
	}
}
