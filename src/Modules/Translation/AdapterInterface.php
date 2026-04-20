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

	/**
	 * Creates a translated term and links it to the source.
	 *
	 * @return int|\WP_Error New term ID on success.
	 */
	public function insert_term_translation(
		string $name,
		string $taxonomy,
		string $slug,
		string $target_lang,
		int $source_id,
		string $source_lang
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
}
