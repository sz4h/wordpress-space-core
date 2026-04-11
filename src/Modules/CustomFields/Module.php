<?php

namespace Space\Core\Modules\CustomFields;

defined( 'ABSPATH' ) || exit;

use Space\Core\Abstracts\AbstractModule;

class Module extends AbstractModule {

    private array $definitions = [];

    public function get_label(): string {
        return __( 'Custom Fields', 'space-core' );
    }

    public function get_description(): string {
        return __( 'Add custom meta fields to any post type via a table UI.', 'space-core' );
    }

    public function boot(): void {
        $this->definitions = $this->load_definitions();
        add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
        add_action( 'save_post', [ $this, 'save_meta' ], 10, 2 );
        add_action( 'wp_ajax_sc_save_custom_fields', [ $this, 'ajax_save' ] );
        add_action( 'wp_ajax_sc_delete_custom_field', [ $this, 'ajax_delete' ] );
    }

    private function load_definitions(): array {
        $raw = get_option( 'space_core_custom_fields', '[]' );
        $def = json_decode( $raw, true );
        return is_array( $def ) ? $def : [];
    }

    // ── AJAX ─────────────────────────────────────────────────────

    public function ajax_save(): void {
        check_ajax_referer( 'sc_cf_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'space-core' ) ] );
        }

        $rows = json_decode( stripslashes( $_POST['rows'] ?? '[]' ), true );
        if ( ! is_array( $rows ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid data.', 'space-core' ) ] );
        }

        $valid_types = [ 'text', 'textarea', 'select', 'checkbox', 'date', 'image', 'number', 'url' ];
        $clean       = [];

        foreach ( $rows as $row ) {
            $key  = sanitize_key( $row['key'] ?? '' );
            $type = sanitize_key( $row['type'] ?? 'text' );
            if ( empty( $key ) ) continue;
            if ( ! in_array( $type, $valid_types, true ) ) $type = 'text';

            $choices = [];
            if ( 'select' === $type && ! empty( $row['choices'] ) ) {
                $choices = array_filter( array_map( 'sanitize_text_field', explode( "\n", $row['choices'] ) ) );
            }

            $clean[] = [
                'key'       => $key,
                'label'     => sanitize_text_field( $row['label'] ?? $key ),
                'type'      => $type,
                'post_type' => sanitize_key( $row['post_type'] ?? 'post' ),
                'choices'   => array_values( $choices ),
            ];
        }

        update_option( 'space_core_custom_fields', wp_json_encode( $clean ) );
        wp_send_json_success( [ 'message' => __( 'Fields saved.', 'space-core' ), 'rows' => $clean ] );
    }

    public function ajax_delete(): void {
        check_ajax_referer( 'sc_cf_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error();
        }
        $key  = sanitize_key( $_POST['key'] ?? '' );
        $pt   = sanitize_key( $_POST['post_type'] ?? '' );
        $defs = $this->load_definitions();
        $defs = array_values( array_filter( $defs, fn( $d ) => !( $d['key'] === $key && $d['post_type'] === $pt ) ) );
        update_option( 'space_core_custom_fields', wp_json_encode( $defs ) );
        wp_send_json_success();
    }

    // ── Meta boxes ────────────────────────────────────────────────

    public function add_meta_boxes(): void {
        $groups = [];
        foreach ( $this->definitions as $field ) {
            $pt = sanitize_key( $field['post_type'] ?? 'post' );
            $groups[ $pt ][] = $field;
        }
        foreach ( $groups as $post_type => $fields ) {
            add_meta_box(
                'sc_custom_fields_' . $post_type,
                __( 'Space Core Fields', 'space-core' ),
                fn( \WP_Post $post ) => $this->render_meta_box( $post, $fields ),
                $post_type,
                'normal',
                'default'
            );
        }
    }

    private function render_meta_box( \WP_Post $post, array $fields ): void {
        echo $this->view( 'admin/meta-box', [
            'post'   => $post,
            'fields' => $fields,
        ] );
    }

    private function render_field( string $key, string $type, mixed $value, array $field ): void {
        echo $this->view( 'admin/meta-field', [
            'key'   => $key,
            'type'  => $type,
            'value' => $value,
            'field' => $field,
        ] );
    }

    public function save_meta( int $post_id, \WP_Post $post ): void {
        if ( ! isset( $_POST['sc_cf_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sc_cf_nonce'] ) ), 'sc_custom_fields_save' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        foreach ( $this->definitions as $field ) {
            if ( sanitize_key( $field['post_type'] ?? '' ) !== $post->post_type ) continue;
            $key  = sanitize_key( $field['key'] ?? '' );
            $type = $field['type'] ?? 'text';
            if ( 'checkbox' === $type ) {
                update_post_meta( $post_id, '_sc_' . $key, isset( $_POST['sc_cf'][ $key ] ) ? '1' : '' );
            } elseif ( isset( $_POST['sc_cf'][ $key ] ) ) {
                $value = match ( $type ) {
                    'textarea' => sanitize_textarea_field( wp_unslash( $_POST['sc_cf'][ $key ] ) ),
                    'image'    => absint( $_POST['sc_cf'][ $key ] ),
                    'url'      => esc_url_raw( wp_unslash( $_POST['sc_cf'][ $key ] ) ),
                    'number'   => (float) $_POST['sc_cf'][ $key ],
                    default    => sanitize_text_field( wp_unslash( $_POST['sc_cf'][ $key ] ) ),
                };
                update_post_meta( $post_id, '_sc_' . $key, $value );
            }
        }
    }

    // ── Settings UI ───────────────────────────────────────────────

    public function render_settings(): void {
        $nonce = wp_create_nonce( 'sc_cf_nonce' );
        $defs  = $this->load_definitions();
        $types = [
            'text'     => __( 'Text', 'space-core' ),
            'textarea' => __( 'Textarea', 'space-core' ),
            'select'   => __( 'Select', 'space-core' ),
            'checkbox' => __( 'Checkbox', 'space-core' ),
            'date'     => __( 'Date', 'space-core' ),
            'image'    => __( 'Image', 'space-core' ),
            'number'   => __( 'Number', 'space-core' ),
            'url'      => __( 'URL', 'space-core' ),
        ];
        $post_types = get_post_types( [ 'show_ui' => true ], 'objects' );
        echo $this->view( 'admin/settings', [
            'nonce'      => $nonce,
            'defs'       => $defs,
            'types'      => $types,
            'post_types' => $post_types,
        ] );
    }
}
