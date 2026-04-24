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
        add_shortcode( 'sc_custom_field', [ $this, 'render_shortcode' ] );
    }

    private function load_definitions(): array {
        return self::get_definitions();
    }

    public static function get_definitions( ?string $post_type = null ): array {
        $raw = get_option( 'space_core_custom_fields', '[]' );
        $def = json_decode( $raw, true );
        if ( ! is_array( $def ) ) {
            return [];
        }

        $definitions = array_values( array_filter( array_map( [ self::class, 'normalize_definition' ], $def ) ) );

        if ( null === $post_type || '' === $post_type ) {
            return $definitions;
        }

        $post_type = sanitize_key( $post_type );

        return array_values(
            array_filter(
                $definitions,
                static fn( array $field ): bool => $post_type === ( $field['post_type'] ?? '' )
            )
        );
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
            $label_en = sanitize_text_field( $row['label_en'] ?? '' );
            $label_ar = sanitize_text_field( $row['label_ar'] ?? '' );

            if ( 'select' === $type ) {
                $choices = self::sanitize_choices(
                    (string) ( $row['choices_en'] ?? '' ),
                    (string) ( $row['choices_ar'] ?? '' )
                );
            }

            $clean[] = [
                'key'       => $key,
                'label'     => $label_en ?: $label_ar ?: sanitize_text_field( $row['label'] ?? $key ),
                'label_en'  => $label_en,
                'label_ar'  => $label_ar,
                'type'      => $type,
                'post_type' => sanitize_key( $row['post_type'] ?? 'post' ),
                'choices'   => $choices,
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

    public static function get_value( string $key, ?int $post_id = null ): mixed {
        $key = sanitize_key( $key );

        if ( '' === $key ) {
            return '';
        }

        $post_id = $post_id ?: self::resolve_post_id();

        if ( $post_id <= 0 ) {
            return '';
        }

        return get_post_meta( $post_id, '_sc_' . $key, true );
    }

    public static function get_display_value( string $key, ?int $post_id = null, ?string $lang = null ): string {
        $value = self::get_value( $key, $post_id );

        if ( is_array( $value ) || is_object( $value ) ) {
            return '';
        }

        $value = (string) $value;

        if ( '' === $value ) {
            return '';
        }

        $field = self::get_definition_by_key( $key, $post_id );

        if ( ! $field || 'select' !== ( $field['type'] ?? '' ) ) {
            return $value;
        }

        foreach ( (array) ( $field['choices'] ?? [] ) as $choice ) {
            $choice = self::normalize_choice( $choice );
            if ( $value === (string) ( $choice['value'] ?? '' ) ) {
                return self::get_choice_label( $choice, $lang );
            }
        }

        return $value;
    }

    public static function get_field_label( string $key, ?string $lang = null, ?int $post_id = null ): string {
        $field = self::get_definition_by_key( $key, $post_id );

        if ( ! $field ) {
            return sanitize_text_field( $key );
        }

        return self::get_definition_label( $field, $lang );
    }

    public function render_shortcode( array $atts ): string {
        $atts = shortcode_atts(
            [
                'key'      => '',
                'post_id'  => '',
                'format'   => 'value',
                'fallback' => '',
            ],
            $atts,
            'sc_custom_field'
        );

        $key     = sanitize_key( (string) $atts['key'] );
        $post_id = '' !== $atts['post_id'] ? absint( $atts['post_id'] ) : null;
        $format  = sanitize_key( (string) $atts['format'] );

        if ( '' === $key ) {
            return '';
        }

        $value = self::get_display_value( $key, $post_id );

        if ( '' === $value ) {
            return esc_html( (string) $atts['fallback'] );
        }

        if ( in_array( $format, [ 'label', 'label_value', 'label_key' ], true ) ) {
            $label = self::get_field_label( $key, null, $post_id );
            return esc_html( $label . ': ' . $value );
        }

        return esc_html( $value );
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

    private static function sanitize_choices( string $choices_en_raw, string $choices_ar_raw ): array {
        $choices_en = array_map( 'trim', preg_split( '/\r\n|\r|\n/', $choices_en_raw ) ?: [] );
        $choices_ar = array_map( 'trim', preg_split( '/\r\n|\r|\n/', $choices_ar_raw ) ?: [] );
        $total      = max( count( $choices_en ), count( $choices_ar ) );
        $clean      = [];

        for ( $i = 0; $i < $total; $i++ ) {
            $label_en = sanitize_text_field( $choices_en[ $i ] ?? '' );
            $label_ar = sanitize_text_field( $choices_ar[ $i ] ?? '' );

            if ( '' === $label_en && '' === $label_ar ) {
                continue;
            }

            $clean[] = [
                'value'    => $label_en ?: $label_ar,
                'label_en' => $label_en,
                'label_ar' => $label_ar,
            ];
        }

        return $clean;
    }

    private static function normalize_definition( mixed $field ): array {
        if ( ! is_array( $field ) ) {
            return [];
        }

        $label_en = sanitize_text_field( $field['label_en'] ?? '' );
        $label_ar = sanitize_text_field( $field['label_ar'] ?? '' );
        $label    = sanitize_text_field( $field['label'] ?? '' );
        $choices  = [];

        foreach ( (array) ( $field['choices'] ?? [] ) as $choice ) {
            $normalized = self::normalize_choice( $choice );
            if ( [] !== $normalized ) {
                $choices[] = $normalized;
            }
        }

        return [
            'key'       => sanitize_key( $field['key'] ?? '' ),
            'label'     => $label_en ?: $label_ar ?: $label,
            'label_en'  => $label_en ?: ( ! preg_match( '/[\x{0600}-\x{06FF}]/u', $label ) ? $label : '' ),
            'label_ar'  => $label_ar ?: ( preg_match( '/[\x{0600}-\x{06FF}]/u', $label ) ? $label : '' ),
            'type'      => sanitize_key( $field['type'] ?? 'text' ),
            'post_type' => sanitize_key( $field['post_type'] ?? 'post' ),
            'choices'   => $choices,
        ];
    }

    private static function normalize_choice( mixed $choice ): array {
        if ( is_string( $choice ) ) {
            $value = sanitize_text_field( $choice );

            if ( '' === $value ) {
                return [];
            }

            return [
                'value'    => $value,
                'label_en' => $value,
                'label_ar' => '',
            ];
        }

        if ( ! is_array( $choice ) ) {
            return [];
        }

        $label_en = sanitize_text_field( $choice['label_en'] ?? '' );
        $label_ar = sanitize_text_field( $choice['label_ar'] ?? '' );
        $value    = sanitize_text_field( $choice['value'] ?? ( $label_en ?: $label_ar ) );

        if ( '' === $value && '' === $label_en && '' === $label_ar ) {
            return [];
        }

        return [
            'value'    => $value ?: $label_en ?: $label_ar,
            'label_en' => $label_en ?: $value,
            'label_ar' => $label_ar,
        ];
    }

    private static function get_definition_by_key( string $key, ?int $post_id = null ): ?array {
        $key = sanitize_key( $key );

        if ( '' === $key ) {
            return null;
        }

        $raw = get_option( 'space_core_custom_fields', '[]' );
        $def = json_decode( $raw, true );

        if ( ! is_array( $def ) ) {
            return null;
        }

        $post_type = '';

        if ( $post_id ) {
            $post_type = get_post_type( $post_id ) ?: '';
        }

        foreach ( $def as $field ) {
            $field = self::normalize_definition( $field );
            if ( $key === ( $field['key'] ?? '' ) && ( '' === $post_type || $post_type === ( $field['post_type'] ?? '' ) ) ) {
                return $field;
            }
        }

        foreach ( $def as $field ) {
            $field = self::normalize_definition( $field );
            if ( $key === ( $field['key'] ?? '' ) ) {
                return $field;
            }
        }

        return null;
    }

    private static function get_definition_label( array $field, ?string $lang = null ): string {
        $lang = self::normalize_lang( $lang );

        if ( 'ar' === $lang && ! empty( $field['label_ar'] ) ) {
            return (string) $field['label_ar'];
        }

        if ( ! empty( $field['label_en'] ) ) {
            return (string) $field['label_en'];
        }

        if ( ! empty( $field['label_ar'] ) ) {
            return (string) $field['label_ar'];
        }

        return (string) ( $field['label'] ?? $field['key'] ?? '' );
    }

    private static function get_choice_label( array $choice, ?string $lang = null ): string {
        $lang = self::normalize_lang( $lang );

        if ( 'ar' === $lang && ! empty( $choice['label_ar'] ) ) {
            return (string) $choice['label_ar'];
        }

        if ( ! empty( $choice['label_en'] ) ) {
            return (string) $choice['label_en'];
        }

        if ( ! empty( $choice['label_ar'] ) ) {
            return (string) $choice['label_ar'];
        }

        return (string) ( $choice['value'] ?? '' );
    }

    private static function normalize_lang( ?string $lang = null ): string {
        $lang = $lang ? sanitize_key( $lang ) : substr( get_locale(), 0, 2 );
        return 'ar' === $lang ? 'ar' : 'en';
    }

    private static function resolve_post_id(): int {
        $post_id = get_the_ID();

        if ( $post_id ) {
            return (int) $post_id;
        }

        $queried_object_id = get_queried_object_id();

        return $queried_object_id ? (int) $queried_object_id : 0;
    }
}
