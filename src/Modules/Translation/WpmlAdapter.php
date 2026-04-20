<?php

namespace Space\Core\Modules\Translation;

defined( 'ABSPATH' ) || exit;

/**
 * WPML adapter stub — full implementation coming in a future release.
 */
class WpmlAdapter implements AdapterInterface {

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

	public function get_terms_with_groups( string $taxonomy, string $source_lang ): array {
		return [];
	}

	public function insert_term_translation(
		string $name,
		string $taxonomy,
		string $slug,
		string $target_lang,
		int $source_id,
		string $source_lang
	): int|\WP_Error {
		return new \WP_Error( 'wpml_not_implemented', __( 'WPML term translation is not yet implemented.', 'space-core' ), [ 'status' => 501 ] );
	}

	public function get_menu_items_with_groups( array $menu_ids, string $source_lang ): array {
		return [];
	}

	public function insert_menu_item_translation(
		int $source_id,
		string $title,
		string $url,
		int $menu_id,
		string $target_lang,
		string $source_lang
	): int|\WP_Error {
		return new \WP_Error( 'wpml_not_implemented', __( 'WPML menu translation is not yet implemented.', 'space-core' ), [ 'status' => 501 ] );
	}
}
