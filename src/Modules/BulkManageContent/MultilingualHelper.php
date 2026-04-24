<?php

namespace Space\Core\Modules\BulkManageContent;

defined( 'ABSPATH' ) || exit;

class MultilingualHelper {

    public static function is_active(): bool {
        return defined( 'ICL_SITEPRESS_VERSION' ) || defined( 'POLYLANG_VERSION' );
    }

    public static function plugin(): string {
        if ( defined( 'ICL_SITEPRESS_VERSION' ) ) return 'wpml';
        if ( defined( 'POLYLANG_VERSION' ) ) return 'polylang';
        return 'none';
    }

    public static function get_translated_post_id( int $id, string $lang, string $post_type = 'post' ): int {
        if ( 'wpml' === self::plugin() ) {
            $translated = apply_filters( 'wpml_object_id', $id, $post_type, false, $lang );
            return $translated ? (int) $translated : 0;
        }
        if ( 'polylang' === self::plugin() && function_exists( 'pll_get_post' ) ) {
            $translated = pll_get_post( $id, $lang );
            return $translated ? (int) $translated : 0;
        }
        return 0;
    }

    public static function get_translated_term_id( int $id, string $lang, string $taxonomy = '' ): int {
        if ( 'wpml' === self::plugin() ) {
            $translated = apply_filters( 'wpml_object_id', $id, $taxonomy ?: 'tax_id', false, $lang );
            return $translated ? (int) $translated : 0;
        }
        if ( 'polylang' === self::plugin() && function_exists( 'pll_get_term' ) ) {
            $translated = pll_get_term( $id, $lang );
            return $translated ? (int) $translated : 0;
        }
        return 0;
    }

    public static function get_post_title_in_lang( int $id, string $lang, string $post_type = 'post' ): string {
        $translated_id = self::get_translated_post_id( $id, $lang, $post_type );
        if ( ! $translated_id ) return '';
        $post = get_post( $translated_id );
        return $post ? $post->post_title : '';
    }

    public static function get_term_name_in_lang( int $id, string $lang, string $taxonomy = '' ): string {
        $translated_id = self::get_translated_term_id( $id, $lang, $taxonomy );
        if ( ! $translated_id ) return '';
        $term = get_term( $translated_id );
        return ( $term && ! is_wp_error( $term ) ) ? $term->name : '';
    }

    public static function update_post_title_in_lang( int $id, string $title, string $lang, string $post_type = 'post' ): bool {
        $translated_id = self::get_translated_post_id( $id, $lang, $post_type );
        if ( ! $translated_id ) return false;
        $result = wp_update_post( [ 'ID' => $translated_id, 'post_title' => $title ] );
        return ! is_wp_error( $result ) && $result > 0;
    }

    public static function update_term_name_in_lang( int $id, string $name, string $lang, string $taxonomy = '' ): bool {
        $translated_id = self::get_translated_term_id( $id, $lang, $taxonomy );
        if ( ! $translated_id || ! $taxonomy ) return false;
        $result = wp_update_term( $translated_id, $taxonomy, [ 'name' => $name ] );
        return ! is_wp_error( $result );
    }
}
