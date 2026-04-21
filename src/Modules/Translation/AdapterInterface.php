<?php

namespace Space\Core\Modules\Translation;

defined( 'ABSPATH' ) || exit;

interface AdapterInterface {

	/** @return array<int, array{slug: string, name: string, default: bool}> */
	public function get_languages(): array;

	/**
	 * Returns terms in $taxonomy assigned to $source_lang, each with their
	 * translation-group description so callers can filter by missing $target_lang.
	 *
	 * @return array<int, object{term_id: int, name: string, slug: string, trans_desc: string|null}>
	 */
	public function get_terms_with_groups( string $taxonomy, string $source_lang ): array;

	/** Returns the language slug assigned to $term_id, or empty string if unset. */
	public function get_term_language( int $term_id ): string;

	/** Returns true when $term_id already has a translation in $target_lang. */
	public function has_term_translation( int $term_id, string $target_lang ): bool;

	/**
	 * Creates a translated term, saves optional meta, and links it to the source.
	 *
	 * @param array<string, scalar> $meta Translated term meta to save after creation.
	 * @return int|\WP_Error New term ID on success.
	 */
	public function insert_term_translation(
		string $name,
		string $taxonomy,
		string $slug,
		string $target_lang,
		int $source_id,
		string $source_lang,
		array $meta = []
	): int|\WP_Error;

	/**
	 * Returns nav_menu_item posts in $source_lang (optionally scoped to $menu_ids)
	 * with their translation-group description attached.
	 *
	 * @param int[] $menu_ids  Empty array = all menus.
	 * @return array<int, object{ID: int, post_title: string, url: string|null, menu_id: int, menu_name: string, trans_desc: string|null}>
	 */
	public function get_menu_items_with_groups( array $menu_ids, string $source_lang ): array;

	/**
	 * Creates a translated nav_menu_item and links it to the source.
	 *
	 * @return int|\WP_Error New post ID on success.
	 */
	public function insert_menu_item_translation(
		int $source_id,
		string $title,
		string $url,
		int $menu_id,
		string $target_lang,
		string $source_lang
	): int|\WP_Error;

	// -------------------------------------------------------------------------
	// Posts / Pages / CPTs
	// -------------------------------------------------------------------------

	/**
	 * Returns posts of $post_type in $source_lang that have no translation in $target_lang.
	 * Filtering is performed inside the adapter (SQL for WPML, PHP for Polylang).
	 *
	 * @return array<int, object{ID: int, post_title: string, post_name: string, post_excerpt: string, post_content: string}>
	 */
	public function get_posts_missing_translation( string $post_type, string $source_lang, string $target_lang ): array;

	/** Returns the language slug assigned to $post_id, or empty string if unset. */
	public function get_post_language( int $post_id ): string;

	/** Returns true when $post_id already has a translation in $target_lang. */
	public function has_post_translation( int $post_id, string $target_lang ): bool;

	/**
	 * Creates a translated post and links it to the source.
	 *
	 * $post_data keys: title (required), slug, excerpt, content, meta (assoc array).
	 * Meta from source is copied first; $post_data['meta'] values overwrite.
	 *
	 * @return int|\WP_Error New post ID on success.
	 */
	public function insert_post_translation(
		int $source_id,
		array $post_data,
		string $target_lang,
		string $source_lang
	): int|\WP_Error;
}
