<?php

namespace Space\Core\Modules\Translation;

defined( 'ABSPATH' ) || exit;

/**
 * Determines which post meta keys carry human-readable text worth translating.
 *
 * Exclude rules (in order):
 *  1. Key is on the hard-coded denylist.
 *  2. Key starts with a denylisted prefix.
 *  3. Value is empty, purely numeric, a serialized structure, or a bare URL.
 *
 * The `sc_translation_translatable_meta` filter lets site owners extend or
 * override the final result per post.
 */
class MetaFilter {

	/** @var string[] Exact keys that are never translatable. */
	private const DENIED_KEYS = [
		// WordPress core
		'_edit_lock', '_edit_last', '_pingme', '_encloseme',
		'_thumbnail_id', '_wp_page_template', '_wp_old_slug', '_wp_old_date',
		'_wp_trash_meta_status', '_wp_trash_meta_time',
		// WooCommerce — pricing & stock
		'_price', '_regular_price', '_sale_price',
		'_sale_price_dates_from', '_sale_price_dates_to',
		'_stock', '_stock_status', '_manage_stock', '_backorders',
		'_sold_individually',
		// WooCommerce — product identity & dimensions
		'_sku', '_weight', '_length', '_width', '_height',
		'_virtual', '_downloadable',
		// WooCommerce — relations & statistics
		'_upsell_ids', '_crosssell_ids', '_children',
		'_product_image_gallery', '_product_version',
		'total_sales', '_wc_rating_count', '_wc_average_rating', '_wc_review_count',
		'_tax_status', '_tax_class',
		// Menu items (handled by menus controller)
		'_menu_item_type', '_menu_item_menu_item_parent', '_menu_item_object_id',
		'_menu_item_object', '_menu_item_target', '_menu_item_classes',
		'_menu_item_xfn', '_menu_item_url',
	];

	/** @var string[] Keys starting with any of these prefixes are excluded. */
	private const DENIED_PREFIXES = [
		'_wc_',
		'_download_',
		'_wp_attachment_',
	];

	/**
	 * Returns meta_key => scalar_value pairs for all translatable post meta.
	 * When the site has explicitly configured keys for this post type those are
	 * used exclusively; otherwise auto-detection runs.
	 *
	 * @return array<string, scalar>
	 */
	public static function filter( int $post_id, string $post_type ): array {
		$configured = self::configured_post_keys( $post_type );

		if ( null !== $configured ) {
			$result = [];
			foreach ( $configured as $key ) {
				$value = get_post_meta( $post_id, $key, true );
				if ( self::is_translatable_value( $value ) ) {
					$result[ $key ] = $value;
				}
			}
		} else {
			$all_meta = get_post_meta( $post_id );
			$result   = [];

			foreach ( $all_meta as $key => $values ) {
				if ( self::is_denied_key( $key ) ) {
					continue;
				}
				$value = maybe_unserialize( $values[0] ?? '' );
				if ( ! self::is_translatable_value( $value ) ) {
					continue;
				}
				$result[ $key ] = $value;
			}
		}

		/**
		 * @param array<string, scalar> $result
		 * @param int                   $post_id
		 * @param string                $post_type
		 */
		return (array) apply_filters( 'sc_translation_translatable_meta', $result, $post_id, $post_type );
	}

	/**
	 * Returns meta_key => scalar_value pairs for translatable term meta.
	 * Only keys explicitly configured for this taxonomy are returned; if none
	 * are configured an empty array is returned (term meta auto-detection is
	 * intentionally opt-in).
	 *
	 * @return array<string, scalar>
	 */
	public static function filter_term( int $term_id, string $taxonomy ): array {
		$configured = self::configured_taxonomy_keys( $taxonomy );

		if ( null === $configured || empty( $configured ) ) {
			return [];
		}

		$result = [];
		foreach ( $configured as $key ) {
			$value = get_term_meta( $term_id, $key, true );
			if ( self::is_translatable_value( $value ) ) {
				$result[ $key ] = $value;
			}
		}

		/**
		 * @param array<string, scalar> $result
		 * @param int                   $term_id
		 * @param string                $taxonomy
		 */
		return (array) apply_filters( 'sc_translation_translatable_term_meta', $result, $term_id, $taxonomy );
	}

	/** Returns the configured meta keys for a post type, or null when not configured. */
	public static function configured_post_keys( string $post_type ): ?array {
		$opts = get_option( 'space_core_translation', [] );
		$keys = $opts['post_types'][ $post_type ]['keys'] ?? null;
		return is_array( $keys ) ? array_values( array_filter( $keys ) ) : null;
	}

	/** Returns the configured meta keys for a taxonomy, or null when not configured. */
	public static function configured_taxonomy_keys( string $taxonomy ): ?array {
		$opts = get_option( 'space_core_translation', [] );
		$keys = $opts['taxonomies'][ $taxonomy ]['keys'] ?? null;
		return is_array( $keys ) ? array_values( array_filter( $keys ) ) : null;
	}

	// -------------------------------------------------------------------------

	private static function is_denied_key( string $key ): bool {
		if ( in_array( $key, self::DENIED_KEYS, true ) ) {
			return true;
		}

		foreach ( self::DENIED_PREFIXES as $prefix ) {
			if ( str_starts_with( $key, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	private static function is_translatable_value( mixed $value ): bool {
		if ( ! is_string( $value ) ) {
			return false;
		}

		$value = trim( $value );

		if ( '' === $value ) {
			return false;
		}

		// Pure number (price, ID, count, etc.).
		if ( is_numeric( $value ) ) {
			return false;
		}

		// Serialized PHP structure.
		if ( preg_match( '/^[aOsbiCd]:[0-9]+[:{]/', $value ) ) {
			return false;
		}

		// JSON array or object.
		if ( str_starts_with( $value, '[' ) || str_starts_with( $value, '{' ) ) {
			return false;
		}

		// Bare URL (no spaces, starts with a scheme).
		if ( preg_match( '/^https?:\/\/\S+$/', $value ) ) {
			return false;
		}

		return true;
	}
}
