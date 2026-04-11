<?php

namespace Space\Core\Modules\CustomTaxonomies;

defined( 'ABSPATH' ) || exit;

use Space\Core\Abstracts\AbstractModule;

class Module extends AbstractModule {

    public function get_label(): string {
        return __( 'Custom Taxonomies', 'space-core' );
    }

    public function get_description(): string {
        return __( 'Register custom taxonomies and attach them to any post type.', 'space-core' );
    }

    public function boot(): void {
        add_action( 'init', [ $this, 'register_taxonomies' ], 6 );
        add_action( 'wp_ajax_sc_save_taxonomies', [ $this, 'ajax_save' ] );
        add_action( 'wp_ajax_sc_delete_taxonomy', [ $this, 'ajax_delete' ] );
    }

    public static function get_definitions(): array {
        $raw = get_option( 'space_core_taxonomies', '[]' );
        $def = json_decode( $raw, true );
        return is_array( $def ) ? $def : [];
    }

    public function register_taxonomies(): void {
        foreach ( self::get_definitions() as $tax ) {
            if ( empty( $tax['slug'] ) ) continue;
            $this->register_single( $tax );
        }
    }

    private function register_single( array $tax ): void {
        $slug         = sanitize_key( $tax['slug'] );
        $name         = sanitize_text_field( $tax['name'] ?? $slug );
        $plural       = sanitize_text_field( $tax['plural'] ?? $name . 's' );
        $post_types   = array_map( 'sanitize_key', (array) ( $tax['post_types'] ?? [ 'post' ] ) );
        $hierarchical = (bool) ( $tax['hierarchical'] ?? false );

        $labels = [
            'name'              => $plural,
            'singular_name'     => $name,
            'search_items'      => sprintf( __( 'Search %s', 'space-core' ), $plural ),
            'all_items'         => sprintf( __( 'All %s', 'space-core' ), $plural ),
            'parent_item'       => $hierarchical ? sprintf( __( 'Parent %s', 'space-core' ), $name ) : null,
            'parent_item_colon' => $hierarchical ? sprintf( __( 'Parent %s:', 'space-core' ), $name ) : null,
            'edit_item'         => sprintf( __( 'Edit %s', 'space-core' ), $name ),
            'update_item'       => sprintf( __( 'Update %s', 'space-core' ), $name ),
            'add_new_item'      => sprintf( __( 'Add New %s', 'space-core' ), $name ),
            'new_item_name'     => sprintf( __( 'New %s Name', 'space-core' ), $name ),
            'menu_name'         => $plural,
        ];

        register_taxonomy( $slug, $post_types, [
            'labels'            => $labels,
            'hierarchical'      => $hierarchical,
            'public'            => (bool) ( $tax['public'] ?? true ),
            'show_ui'           => true,
            'show_in_menu'      => true,
            'show_in_rest'      => (bool) ( $tax['show_in_rest'] ?? true ),
            'rewrite'           => [ 'slug' => $slug ],
            'show_admin_column' => (bool) ( $tax['show_admin_column'] ?? true ),
        ] );
    }

    // ── AJAX ─────────────────────────────────────────────────────

    public function ajax_save(): void {
        check_ajax_referer( 'sc_taxonomies_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'space-core' ) ] );
        }

        $rows = json_decode( stripslashes( $_POST['rows'] ?? '[]' ), true );
        if ( ! is_array( $rows ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid data.', 'space-core' ) ] );
        }

        $clean = [];
        foreach ( $rows as $row ) {
            $slug = sanitize_key( $row['slug'] ?? '' );
            if ( empty( $slug ) ) continue;
            $clean[] = [
                'slug'              => $slug,
                'name'              => sanitize_text_field( $row['name'] ?? $slug ),
                'plural'            => sanitize_text_field( $row['plural'] ?? '' ),
                'post_types'        => array_filter( array_map( 'sanitize_key', (array) ( $row['post_types'] ?? [] ) ) ),
                'hierarchical'      => (bool) ( $row['hierarchical'] ?? false ),
                'public'            => (bool) ( $row['public'] ?? true ),
                'show_in_rest'      => (bool) ( $row['show_in_rest'] ?? true ),
                'show_admin_column' => (bool) ( $row['show_admin_column'] ?? true ),
            ];
        }

        update_option( 'space_core_taxonomies', wp_json_encode( $clean ) );
        wp_send_json_success( [ 'message' => __( 'Taxonomies saved.', 'space-core' ), 'rows' => $clean ] );
    }

    public function ajax_delete(): void {
        check_ajax_referer( 'sc_taxonomies_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error();
        }
        $slug = sanitize_key( $_POST['slug'] ?? '' );
        $defs = self::get_definitions();
        $defs = array_values( array_filter( $defs, fn( $d ) => $d['slug'] !== $slug ) );
        update_option( 'space_core_taxonomies', wp_json_encode( $defs ) );
        wp_send_json_success();
    }

    public function render_settings(): void {
        $nonce = wp_create_nonce( 'sc_taxonomies_nonce' );
        $defs  = self::get_definitions();

        // Get all registered post types for the multi-select.
        $post_types = get_post_types( [ 'show_ui' => true ], 'objects' );
        $pt_options = [];
        foreach ( $post_types as $pt ) {
            $pt_options[ $pt->name ] = $pt->labels->singular_name;
        }

        echo $this->view( 'admin/settings', [
            'nonce'      => $nonce,
            'defs'       => $defs,
            'pt_options' => $pt_options,
        ] );
    }
}
