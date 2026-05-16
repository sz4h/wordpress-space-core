<?php

namespace Space\Core\Modules\BulkManageContent;

defined( 'ABSPATH' ) || exit;

use Space\Core\Abstracts\AbstractModule;
use Space\Core\Modules\CustomFields\Module as CustomFieldsModule;
use WP_Query;

class Module extends AbstractModule {

    private const NONCE = 'sc_bulk_manage_nonce';
    private const OPTION_KEY = 'space_core_bulk_manage_content';

    // ── Identity ──────────────────────────────────────────────────

    public function get_description(): string {
        return __( 'Inline bulk-edit posts and taxonomy terms with custom meta fields and multilingual support.', 'space-core' );
    }

    public function boot(): void {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_bulk_assets' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_settings_assets' ] );

        // Settings AJAX.
        add_action( 'wp_ajax_sc_save_bulk_settings', [ $this, 'ajax_save_settings' ] );
        add_action( 'wp_ajax_sc_delete_bulk_field', [ $this, 'ajax_delete_field' ] );

        // Posts AJAX.
        add_action( 'wp_ajax_sc_bulk_load_posts', [ $this, 'ajax_load_posts' ] );
        add_action( 'wp_ajax_sc_save_post_field', [ $this, 'ajax_save_post_field' ] );
        add_action( 'wp_ajax_sc_add_post', [ $this, 'ajax_add_post' ] );

        // Terms AJAX.
        add_action( 'wp_ajax_sc_bulk_load_terms', [ $this, 'ajax_load_terms' ] );
        add_action( 'wp_ajax_sc_save_term_field', [ $this, 'ajax_save_term_field' ] );
        add_action( 'wp_ajax_sc_add_term', [ $this, 'ajax_add_term' ] );

        // Temporary product export/import tools.
        add_action( 'admin_post_sc_bmc_export_products', [ $this, 'handle_export_products' ] );
        add_action( 'admin_post_sc_bmc_import_products', [ $this, 'handle_import_products' ] );
    }

    // ── Boot ──────────────────────────────────────────────────────

    public function register_menu(): void {
        if ( ! $this->current_user_can_access_bulk_management() ) {
            return;
        }

        add_menu_page(
                __( 'Bulk Management', 'space-core' ),
                __( 'Bulk Management', 'space-core' ),
                'read',
                'sc-bulk-management',
                [ $this, 'render_bulk_page' ],
                'dashicons-editor-table',
                25
        );

        add_submenu_page(
                'space-core',
                __( 'Bulk Manage Content', 'space-core' ),
                __( 'Bulk Manage Content', 'space-core' ),
                'manage_options',
                'sc-bulk-manage-content',
                [ $this, 'render_settings_wrapper' ]
        );
    }

    // ── Menu ──────────────────────────────────────────────────────

    public function render_settings_wrapper(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap sc-wrap">
            <h1>
                <span class="dashicons dashicons-star-filled sc-logo-icon"></span>
                <?php echo esc_html( $this->get_label() ); ?>
                <span class="sc-by"><?php esc_html_e( 'by Space Zone', 'space-core' ); ?></span>
            </h1>
            <div class="sc-tab-content" style="border-top:1px solid #c3c4c7;margin-top:16px;">
                <?php $this->render_settings(); ?>
            </div>
        </div>
        <?php
    }

    // ── Page renderers ────────────────────────────────────────────

    public function get_label(): string {
        return __( 'Bulk Manage Content', 'space-core' );
    }

    public function render_settings(): void {
        $nonce                      = wp_create_nonce( self::NONCE );
        $config                     = $this->get_config();
        $post_types                 = $this->get_all_post_types();
        $taxonomies                 = $this->get_all_taxonomies();
        $custom_fields_by_post_type = [];

        foreach ( array_keys( $post_types ) as $post_type ) {
            $custom_fields_by_post_type[ $post_type ] = CustomFieldsModule::get_definitions( $post_type );
        }

        echo $this->view( 'admin/settings', compact( 'nonce', 'config', 'post_types', 'taxonomies', 'custom_fields_by_post_type' ) );
    }

    private function get_config(): array {
        $raw = get_option( self::OPTION_KEY, '' );
        if ( empty( $raw ) ) {
            return [ 'post_types' => [], 'taxonomies' => [] ];
        }
        $cfg = json_decode( $raw, true );
        if ( ! is_array( $cfg ) ) {
            return [ 'post_types' => [], 'taxonomies' => [] ];
        }
        $cfg['post_types'] = $cfg['post_types'] ?? [];
        $cfg['taxonomies'] = $cfg['taxonomies'] ?? [];

        return $cfg;
    }

    // ── Asset enqueueing ──────────────────────────────────────────

    private function get_all_post_types(): array {
        $pts = get_post_types( [ 'show_ui' => true ], 'objects' );
        unset( $pts['attachment'] );

        return $pts;
    }

    private function get_all_taxonomies(): array {
        return get_taxonomies( [ 'show_ui' => true ], 'objects' );
    }

    // ── Config helpers ────────────────────────────────────────────

    public function render_bulk_page(): void {
        if ( ! $this->current_user_can_access_bulk_management() ) {
            return;
        }

        $nonce        = wp_create_nonce( self::NONCE );
        $enabled_pts  = $this->get_enabled_post_types();
        $enabled_taxs = $this->get_enabled_taxonomies();

        echo $this->view( 'admin/bulk-page', [
                'nonce'           => $nonce,
                'enabled_pts'     => $enabled_pts,
                'enabled_taxs'    => $enabled_taxs,
                'is_multilingual' => MultilingualHelper::is_active(),
        ] );
    }

    private function get_enabled_post_types(): array {
        $config  = $this->get_config();
        $enabled = [];
        foreach ( $this->get_all_post_types() as $slug => $pt ) {
            if ( ! empty( $config['post_types'][ $slug ]['enabled'] ) ) {
                $enabled[ $slug ] = $pt;
            }
        }

        return $enabled;
    }

    private function get_enabled_taxonomies(): array {
        $config  = $this->get_config();
        $enabled = [];
        foreach ( $this->get_all_taxonomies() as $slug => $tax ) {
            if ( ! empty( $config['taxonomies'][ $slug ]['enabled'] ) ) {
                $enabled[ $slug ] = $tax;
            }
        }

        return $enabled;
    }

    public function enqueue_bulk_assets( string $hook ): void {
        if ( 'toplevel_page_sc-bulk-management' !== $hook ) {
            return;
        }

        $config  = $this->get_config();
        $pt_data = [];
        foreach ( $this->get_enabled_post_types() as $slug => $pt ) {
            $pt_data[] = [
                    'slug'   => $slug,
                    'label'  => $pt->labels->singular_name,
                    'fields' => $config['post_types'][ $slug ]['fields'] ?? [],
            ];
        }
        $tax_data = [];
        foreach ( $this->get_enabled_taxonomies() as $slug => $tax ) {
            $tax_data[] = [
                    'slug'   => $slug,
                    'label'  => $tax->labels->singular_name,
                    'fields' => $config['taxonomies'][ $slug ]['fields'] ?? [],
            ];
        }

        wp_enqueue_style( 'space-core-admin', SPACE_CORE_URL . 'assets/css/admin.css', [], SPACE_CORE_VERSION );
        wp_enqueue_script( 'space-core-admin', SPACE_CORE_URL . 'assets/js/admin.js', [ 'jquery' ], SPACE_CORE_VERSION, true );
        wp_localize_script( 'space-core-admin', 'spaceCore', [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'space_core_admin' ),
        ] );

        wp_enqueue_style(
                'sc-bulk-manage-content',
                SPACE_CORE_URL . 'assets/css/bulk-manage-content.css',
                [ 'space-core-admin' ],
                SPACE_CORE_VERSION
        );
        wp_enqueue_script(
                'sc-bulk-manage-content',
                SPACE_CORE_URL . 'assets/js/bulk-manage-content.js',
                [ 'jquery', 'space-core-admin' ],
                SPACE_CORE_VERSION,
                true
        );
        wp_localize_script( 'sc-bulk-manage-content', 'scBMC', [
                'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
                'nonce'          => wp_create_nonce( self::NONCE ),
                'isMultilingual' => MultilingualHelper::is_active() ? 1 : 0,
                'postTypes'      => $pt_data,
                'taxonomies'     => $tax_data,
                'i18n'           => [
                        'saving'   => __( 'Saving…', 'space-core' ),
                        'saved'    => __( 'Saved', 'space-core' ),
                        'error'    => __( 'Error saving.', 'space-core' ),
                        'noItems'  => __( 'No items found.', 'space-core' ),
                        'saveNew'  => __( 'Save New', 'space-core' ),
                        'cancel'   => __( 'Cancel', 'space-core' ),
                        'required' => __( 'Title / Name is required.', 'space-core' ),
                ],
        ] );
    }

    public function enqueue_settings_assets( string $hook ): void {
        if ( false === strpos( $hook, 'sc-bulk-manage-content' ) ) {
            return;
        }

        wp_add_inline_script( 'space-core-admin', sprintf(
                '(function(){window.scBMCSettings={nonce:%s,ajaxUrl:%s};}());',
                wp_json_encode( wp_create_nonce( self::NONCE ) ),
                wp_json_encode( admin_url( 'admin-ajax.php' ) )
        ), 'after' );
    }

    // ── AJAX: settings ────────────────────────────────────────────

    public function ajax_save_settings(): void {
        $this->verify_nonce();

        $raw  = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : '{}'; // phpcs:ignore
        $data = json_decode( $raw, true );
        if ( ! is_array( $data ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid data.', 'space-core' ) ] );
        }

        $clean    = [ 'post_types' => [], 'taxonomies' => [] ];
        $all_pts  = array_keys( $this->get_all_post_types() );
        $all_taxs = array_keys( $this->get_all_taxonomies() );

        foreach ( ( $data['post_types'] ?? [] ) as $slug => $cfg ) {
            $slug = sanitize_key( $slug );
            if ( ! in_array( $slug, $all_pts, true ) ) {
                continue;
            }
            $raw_field_keys = array_values(
                    array_unique(
                            array_filter(
                                    array_map( 'sanitize_key', is_array( $cfg['field_keys'] ?? null ) ? $cfg['field_keys'] : [] )
                            )
                    )
            );
            $custom_fields                = $this->map_selected_custom_fields( $slug, $raw_field_keys );
            $manual_fields                = $this->sanitize_fields( $cfg['manual_fields'] ?? [] );
            $clean['post_types'][ $slug ] = [
                    'enabled'          => ! empty( $cfg['enabled'] ),
                    'field_keys'       => $raw_field_keys,
                    'manual_fields'    => $manual_fields,
                    'fields'           => $this->merge_fields( $custom_fields, $manual_fields ),
                    'exclude_meta_key' => sanitize_key( $cfg['exclude_meta_key'] ?? '' ),
            ];
        }

        foreach ( ( $data['taxonomies'] ?? [] ) as $slug => $cfg ) {
            $slug = sanitize_key( $slug );
            if ( ! in_array( $slug, $all_taxs, true ) ) {
                continue;
            }
            $clean['taxonomies'][ $slug ] = [
                    'enabled' => ! empty( $cfg['enabled'] ),
                    'fields'  => $this->sanitize_fields( $cfg['fields'] ?? [] ),
            ];
        }

        update_option( self::OPTION_KEY, wp_json_encode( $clean ) );
        wp_send_json_success( [ 'message' => __( 'Settings saved.', 'space-core' ) ] );
    }

    private function verify_nonce(): void {
        check_ajax_referer( self::NONCE, 'nonce' );
        if ( ! $this->current_user_can_access_bulk_management() ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'space-core' ) ] );
        }
    }

    private function current_user_can_access_bulk_management(): bool {
        if ( current_user_can( 'edit_posts' ) ) {
            return true;
        }

        $user = wp_get_current_user();

        return $user instanceof \WP_User && in_array( 'shop_manager', (array) $user->roles, true );
    }

    // ── AJAX: posts ───────────────────────────────────────────────

    private function map_selected_custom_fields( string $post_type, mixed $field_keys ): array {
        $selected_keys = array_values(
                array_unique(
                        array_filter(
                                array_map( 'sanitize_key', is_array( $field_keys ) ? $field_keys : [] )
                        )
                )
        );

        if ( empty( $selected_keys ) ) {
            return [];
        }

        $available = [];
        foreach ( CustomFieldsModule::get_definitions( $post_type ) as $field ) {
            $raw_key = sanitize_key( $field['key'] ?? '' );
            if ( '' === $raw_key ) {
                continue;
            }
            $available[ $raw_key ] = [
                    'key'      => '_sc_' . $raw_key,
                    'label_en' => sanitize_text_field( $field['label_en'] ?? $field['label'] ?? $raw_key ),
                    'label_ar' => sanitize_text_field( $field['label_ar'] ?? '' ),
                    'type'     => sanitize_key( $field['type'] ?? 'text' ),
                    'choices'  => $this->sanitize_field_choices( $field['choices'] ?? [] ),
            ];
        }

        $clean = [];
        foreach ( $selected_keys as $key ) {
            if ( isset( $available[ $key ] ) ) {
                $clean[] = $available[ $key ];
            }
        }

        return $clean;
    }

    private function sanitize_field_choices( mixed $choices ): array {
        $clean = [];

        foreach ( is_array( $choices ) ? $choices : [] as $choice ) {
            if ( ! is_array( $choice ) && ! is_string( $choice ) ) {
                continue;
            }

            if ( is_string( $choice ) ) {
                $value = sanitize_text_field( $choice );
                if ( '' === $value ) {
                    continue;
                }

                $clean[] = [
                        'value'    => $value,
                        'label_en' => $value,
                        'label_ar' => '',
                ];
                continue;
            }

            $value    = sanitize_text_field( $choice['value'] ?? '' );
            $label_en = sanitize_text_field( $choice['label_en'] ?? $value );
            $label_ar = sanitize_text_field( $choice['label_ar'] ?? '' );

            if ( '' === $value && '' === $label_en && '' === $label_ar ) {
                continue;
            }

            $clean[] = [
                    'value'    => $value ?: $label_en ?: $label_ar,
                    'label_en' => $label_en ?: $value,
                    'label_ar' => $label_ar,
            ];
        }

        return $clean;
    }

    private function sanitize_fields( array $fields ): array {
        $clean = [];
        foreach ( $fields as $field ) {
            $key = sanitize_key( $field['key'] ?? '' );
            if ( empty( $key ) ) {
                continue;
            }
            $clean[] = [
                    'key'      => $key,
                    'label_en' => sanitize_text_field( $field['label_en'] ?? $key ),
                    'label_ar' => sanitize_text_field( $field['label_ar'] ?? '' ),
                    'type'     => sanitize_key( $field['type'] ?? 'text' ),
                    'choices'  => $this->sanitize_field_choices( $field['choices'] ?? [] ),
            ];
        }

        return $clean;
    }

    // ── AJAX: terms ───────────────────────────────────────────────

    private function merge_fields( array $custom_fields, array $manual_fields ): array {
        $merged = [];

        foreach ( array_merge( $custom_fields, $manual_fields ) as $field ) {
            $key = sanitize_key( $field['key'] ?? '' );

            if ( '' === $key || isset( $merged[ $key ] ) ) {
                continue;
            }

            $merged[ $key ] = $field;
        }

        return array_values( $merged );
    }

    public function ajax_delete_field(): void {
        $this->verify_nonce();

        $object_type = in_array( $_POST['object_type'] ?? '', [ 'post_type', 'taxonomy' ], true ) // phpcs:ignore
                ? $_POST['object_type'] : ''; // phpcs:ignore
        $object_slug = sanitize_key( $_POST['object_slug'] ?? '' ); // phpcs:ignore
        $field_key   = sanitize_key( $_POST['field_key'] ?? '' ); // phpcs:ignore

        if ( empty( $object_type ) || empty( $object_slug ) || empty( $field_key ) ) {
            wp_send_json_error();
        }

        $config = $this->get_config();
        $group  = 'post_type' === $object_type ? 'post_types' : 'taxonomies';

        if ( isset( $config[ $group ][ $object_slug ]['fields'] ) ) {
            $config[ $group ][ $object_slug ]['fields'] = array_values(
                    array_filter(
                            $config[ $group ][ $object_slug ]['fields'],
                            fn( $f ) => $f['key'] !== $field_key
                    )
            );
            update_option( self::OPTION_KEY, wp_json_encode( $config ) );
        }

        wp_send_json_success();
    }

    public function ajax_load_posts(): void {
        $this->verify_nonce();

        $post_type   = sanitize_key( $_POST['post_type'] ?? '' ); // phpcs:ignore
        $paged       = max( 1, absint( $_POST['paged'] ?? 1 ) ); // phpcs:ignore
        $per_page    = $this->sanitize_per_page( $_POST['per_page'] ?? 25 ); // phpcs:ignore
        $config      = $this->get_config();
        $fields      = $config['post_types'][ $post_type ]['fields'] ?? [];
        $enabled_pts = array_keys( $this->get_enabled_post_types() );

        if ( empty( $post_type ) || ! in_array( $post_type, $enabled_pts, true ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid post type.', 'space-core' ) ] );
        }

        $query_args = [
                'post_type'      => $post_type,
                'posts_per_page' => $per_page,
                'paged'          => $paged,
                'post_status'    => [ 'publish', 'draft', 'pending', 'future', 'private' ],
                'orderby'        => 'date',
                'order'          => 'DESC',
        ];

        $exclude_key = $config['post_types'][ $post_type ]['exclude_meta_key'] ?? '';
        if ( '' !== $exclude_key ) {
            $query_args['meta_query'] = [
                    'relation' => 'OR',
                    [ 'key' => $exclude_key, 'compare' => 'NOT EXISTS' ],
                    [ 'key' => $exclude_key, 'value' => '', 'compare' => '=' ],
            ];
        }

        $query        = new WP_Query( $query_args );
        $hasThumbnail = false;
        if ( isset( $query->posts ) && is_array( $query->posts ) && $hasThumbnail = has_post_thumbnail( $query->posts[0] ) ) {
            $hasThumbnail = true;
        }

        $html = $this->view( 'admin/partials/post-table', [
                'posts'           => $query->posts,
                'fields'          => $fields,
                'is_multilingual' => MultilingualHelper::is_active(),
                'has_thumbnail'   => $hasThumbnail,
                'post_type'       => $post_type,
                'pagination'      => [
                        'total_pages' => (int) $query->max_num_pages,
                        'total_items' => (int) $query->found_posts,
                        'current'     => $paged,
                ],
                'per_page'        => $per_page,
        ] );

        wp_reset_postdata();

        wp_send_json_success( [
                'html'         => $html,
                'total_pages'  => (int) $query->max_num_pages,
                'total_items'  => (int) $query->found_posts,
                'current_page' => $paged,
        ] );
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function sanitize_per_page( mixed $value ): int {
        $n = absint( $value );

        return in_array( $n, [ 10, 25, 50, 100 ], true ) ? $n : 25;
    }

    public function ajax_save_post_field(): void {
        $this->verify_nonce();

        $post_id   = absint( $_POST['post_id'] ?? 0 ); // phpcs:ignore
        $field_key = sanitize_key( $_POST['field_key'] ?? '' ); // phpcs:ignore
        $value     = wp_unslash( $_POST['value'] ?? '' ); // phpcs:ignore

        if ( ! $post_id || ! $field_key ) {
            wp_send_json_error();
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'space-core' ) ] );
        }

        if ( 'post_title' === $field_key ) {
            wp_update_post( [ 'ID' => $post_id, 'post_title' => $value ] );
        } elseif ( 'post_title_ar' === $field_key ) {
            $post = get_post( $post_id );
            MultilingualHelper::update_post_title_in_lang( $post_id, $value, 'ar', $post ? $post->post_type : 'post' );
        } elseif ( 'post_status' === $field_key ) {
            $allowed = [ 'publish', 'draft', 'pending', 'private' ];
            $value   = sanitize_key( (string) $value );
            wp_update_post( [
                    'ID'          => $post_id,
                    'post_status' => in_array( $value, $allowed, true ) ? $value : 'draft'
            ] );
        } else {
            $post_type = get_post_type( $post_id ) ?: 'post';
            $field     = $this->get_configured_field( 'post_types', $post_type, $field_key );

            if ( ! $field ) {
                wp_send_json_error( [ 'message' => __( 'Invalid field.', 'space-core' ) ] );
            }

            update_post_meta( $post_id, $field_key, $this->sanitize_field_value( $value, $field ) );
        }

        wp_send_json_success();
    }

    private function get_configured_field( string $group, string $slug, string $field_key ): ?array {
        $config = $this->get_config();

        foreach ( (array) ( $config[ $group ][ $slug ]['fields'] ?? [] ) as $field ) {
            if ( $field_key === sanitize_key( $field['key'] ?? '' ) ) {
                return $field;
            }
        }

        return null;
    }

    private function sanitize_field_value( mixed $value, array $field ): string|int|float {
        $type = sanitize_key( $field['type'] ?? 'text' );

        return match ( $type ) {
            'textarea' => sanitize_textarea_field( wp_unslash( (string) $value ) ),
            'number' => '' === trim( (string) $value ) ? '' : (float) $value,
            'url' => esc_url_raw( wp_unslash( (string) $value ) ),
            'image' => absint( $value ),
            'checkbox' => ! empty( $value ) ? '1' : '',
            'select' => $this->sanitize_select_value( $value, $field ),
            default => sanitize_text_field( wp_unslash( (string) $value ) ),
        };
    }

    private function sanitize_select_value( mixed $value, array $field ): string {
        $value = sanitize_text_field( wp_unslash( (string) $value ) );

        if ( '' === $value ) {
            return '';
        }

        foreach ( (array) ( $field['choices'] ?? [] ) as $choice ) {
            $choice_value = sanitize_text_field( is_array( $choice ) ? (string) ( $choice['value'] ?? '' ) : (string) $choice );
            if ( $value === $choice_value ) {
                return $value;
            }
        }

        return '';
    }

    public function ajax_add_post(): void {
        $this->verify_nonce();

        $post_type = sanitize_key( $_POST['post_type'] ?? 'post' ); // phpcs:ignore
        $title_en  = sanitize_text_field( wp_unslash( $_POST['title_en'] ?? '' ) ); // phpcs:ignore
        $title_ar  = sanitize_text_field( wp_unslash( $_POST['title_ar'] ?? '' ) ); // phpcs:ignore
        $status    = sanitize_key( $_POST['status'] ?? 'draft' ); // phpcs:ignore
        $meta      = json_decode( wp_unslash( $_POST['meta'] ?? '{}' ), true ); // phpcs:ignore

        $allowed_statuses = [ 'publish', 'draft', 'pending' ];
        if ( ! in_array( $status, $allowed_statuses, true ) ) {
            $status = 'draft';
        }

        if ( empty( $title_en ) ) {
            wp_send_json_error( [ 'message' => __( 'Title is required.', 'space-core' ) ] );
        }

        $post_id = wp_insert_post( [
                'post_type'   => $post_type,
                'post_title'  => $title_en,
                'post_status' => $status,
        ], true );

        if ( is_wp_error( $post_id ) ) {
            wp_send_json_error( [ 'message' => $post_id->get_error_message() ] );
        }

        $config       = $this->get_config();
        $allowed_keys = array_column( $config['post_types'][ $post_type ]['fields'] ?? [], 'key' );
        if ( is_array( $meta ) ) {
            foreach ( $meta as $key => $val ) {
                $key = sanitize_key( $key );
                if ( in_array( $key, $allowed_keys, true ) ) {
                    $field = $this->get_configured_field( 'post_types', $post_type, $key );
                    if ( $field ) {
                        update_post_meta( $post_id, $key, $this->sanitize_field_value( $val, $field ) );
                    }
                }
            }
        }

        if ( $title_ar && MultilingualHelper::is_active() ) {
            MultilingualHelper::update_post_title_in_lang( $post_id, $title_ar, 'ar', $post_type );
        }

        $fields = $config['post_types'][ $post_type ]['fields'] ?? [];

        $post         = get_post( $post_id );
        $hasThumbnail = has_post_thumbnail( $post_id );
        $html_row     = $this->view( 'admin/partials/post-row', [
                'post'            => $post,
                'fields'          => $fields,
                'has_thumbnail'   => $hasThumbnail,
                'is_multilingual' => MultilingualHelper::is_active(),
                'post_type'       => $post_type,
        ] );

        wp_send_json_success( [ 'id' => $post_id, 'html_row' => $html_row ] );
    }

    public function ajax_load_terms(): void {
        $this->verify_nonce();

        $taxonomy     = sanitize_key( $_POST['taxonomy'] ?? '' ); // phpcs:ignore
        $paged        = max( 1, absint( $_POST['paged'] ?? 1 ) ); // phpcs:ignore
        $per_page     = $this->sanitize_per_page( $_POST['per_page'] ?? 25 ); // phpcs:ignore
        $offset       = ( $paged - 1 ) * $per_page;
        $enabled_taxs = array_keys( $this->get_enabled_taxonomies() );
        $config       = $this->get_config();
        $fields       = $config['taxonomies'][ $taxonomy ]['fields'] ?? [];

        if ( empty( $taxonomy ) || ! in_array( $taxonomy, $enabled_taxs, true ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid taxonomy.', 'space-core' ) ] );
        }

        $total_items = wp_count_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => false ] );
        $total_items = is_wp_error( $total_items ) ? 0 : (int) $total_items;
        $total_pages = $per_page > 0 ? (int) ceil( $total_items / $per_page ) : 1;

        $terms = get_terms( [
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
                'number'     => $per_page,
                'offset'     => $offset,
        ] );
        if ( is_wp_error( $terms ) ) {
            $terms = [];
        }

        $html = $this->view( 'admin/partials/term-table', [
                'terms'           => $terms,
                'fields'          => $fields,
                'is_multilingual' => MultilingualHelper::is_active(),
                'taxonomy'        => $taxonomy,
                'pagination'      => [
                        'total_pages' => $total_pages,
                        'total_items' => $total_items,
                        'current'     => $paged,
                ],
                'per_page'        => $per_page,
        ] );

        wp_send_json_success( [
                'html'         => $html,
                'total_pages'  => $total_pages,
                'total_items'  => $total_items,
                'current_page' => $paged,
        ] );
    }

    public function ajax_save_term_field(): void {
        $this->verify_nonce();

        $term_id   = absint( $_POST['term_id'] ?? 0 ); // phpcs:ignore
        $taxonomy  = sanitize_key( $_POST['taxonomy'] ?? '' ); // phpcs:ignore
        $field_key = sanitize_key( $_POST['field_key'] ?? '' ); // phpcs:ignore
        $value     = wp_unslash( $_POST['value'] ?? '' ); // phpcs:ignore

        if ( ! $term_id || ! $field_key || ! $taxonomy ) {
            wp_send_json_error();
        }

        if ( 'name' === $field_key ) {
            wp_update_term( $term_id, $taxonomy, [ 'name' => $value ] );
        } elseif ( 'name_ar' === $field_key ) {
            MultilingualHelper::update_term_name_in_lang( $term_id, $value, 'ar', $taxonomy );
        } elseif ( 'slug' === $field_key ) {
            wp_update_term( $term_id, $taxonomy, [ 'slug' => sanitize_title( $value ) ] );
        } else {
            $field = $this->get_configured_field( 'taxonomies', $taxonomy, $field_key );

            if ( ! $field ) {
                wp_send_json_error( [ 'message' => __( 'Invalid field.', 'space-core' ) ] );
            }

            update_term_meta( $term_id, $field_key, $this->sanitize_field_value( $value, $field ) );
        }

        wp_send_json_success();
    }

    public function ajax_add_term(): void {
        $this->verify_nonce();

        $taxonomy = sanitize_key( $_POST['taxonomy'] ?? '' ); // phpcs:ignore
        $name_en  = sanitize_text_field( wp_unslash( $_POST['name_en'] ?? '' ) ); // phpcs:ignore
        $name_ar  = sanitize_text_field( wp_unslash( $_POST['name_ar'] ?? '' ) ); // phpcs:ignore
        $meta     = json_decode( wp_unslash( $_POST['meta'] ?? '{}' ), true ); // phpcs:ignore

        if ( empty( $name_en ) || empty( $taxonomy ) ) {
            wp_send_json_error( [ 'message' => __( 'Name and taxonomy are required.', 'space-core' ) ] );
        }

        $result = wp_insert_term( $name_en, $taxonomy );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        $term_id      = (int) $result['term_id'];
        $config       = $this->get_config();
        $allowed_keys = array_column( $config['taxonomies'][ $taxonomy ]['fields'] ?? [], 'key' );

        if ( is_array( $meta ) ) {
            foreach ( $meta as $key => $val ) {
                $key = sanitize_key( $key );
                if ( in_array( $key, $allowed_keys, true ) ) {
                    $field = $this->get_configured_field( 'taxonomies', $taxonomy, $key );
                    if ( $field ) {
                        update_term_meta( $term_id, $key, $this->sanitize_field_value( $val, $field ) );
                    }
                }
            }
        }

        if ( $name_ar && MultilingualHelper::is_active() ) {
            MultilingualHelper::update_term_name_in_lang( $term_id, $name_ar, 'ar', $taxonomy );
        }

        $fields   = $config['taxonomies'][ $taxonomy ]['fields'] ?? [];
        $html_row = $this->view( 'admin/partials/term-row', [
                'term'            => get_term( $term_id, $taxonomy ),
                'fields'          => $fields,
                'is_multilingual' => MultilingualHelper::is_active(),
                'taxonomy'        => $taxonomy,
        ] );

        wp_send_json_success( [ 'id' => $term_id, 'html_row' => $html_row ] );
    }

    private function render_bulk_field_input( array $field, mixed $value, array $args = [] ): string {
        $type       = sanitize_key( $field['type'] ?? 'text' );
        $value      = is_scalar( $value ) ? (string) $value : '';
        $name       = isset( $args['name'] ) ? (string) $args['name'] : '';
        $class      = isset( $args['class'] ) ? trim( (string) $args['class'] ) : 'sc-bmc-inline-field';
        $aria_label = sanitize_text_field( $args['aria_label'] ?? ( $field['label_en'] ?? $field['key'] ?? '' ) );
        $attrs      = $args['attrs'] ?? [];

        $attributes = '';
        foreach ( is_array( $attrs ) ? $attrs : [] as $attr_key => $attr_value ) {
            if ( null === $attr_value || false === $attr_value || '' === $attr_value ) {
                continue;
            }

            $attributes .= sprintf( ' %s="%s"', esc_attr( (string) $attr_key ), esc_attr( (string) $attr_value ) );
        }

        $common = sprintf(
                ' class="%s"%s%s',
                esc_attr( $class ),
                '' !== $name ? ' name="' . esc_attr( $name ) . '"' : '',
                $attributes
        );

        return match ( $type ) {
            'textarea' => sprintf(
                    '<textarea%s aria-label="%s">%s</textarea>',
                    $common,
                    esc_attr( $aria_label ),
                    esc_textarea( $value )
            ),
            'select' => $this->render_bulk_select_input( $field, $value, $common, $aria_label ),
            'checkbox' => sprintf(
                    '<input type="checkbox"%s value="1" aria-label="%s" %s />',
                    $common,
                    esc_attr( $aria_label ),
                    checked( $value, '1', false )
            ),
            'date' => sprintf(
                    '<input type="date"%s value="%s" aria-label="%s" />',
                    $common,
                    esc_attr( $value ),
                    esc_attr( $aria_label )
            ),
            'number' => sprintf(
                    '<input type="number"%s value="%s" aria-label="%s" />',
                    $common,
                    esc_attr( $value ),
                    esc_attr( $aria_label )
            ),
            'url' => sprintf(
                    '<input type="url"%s value="%s" aria-label="%s" />',
                    $common,
                    esc_attr( $value ),
                    esc_attr( $aria_label )
            ),
            'image' => sprintf(
                    '<input type="number"%s value="%s" aria-label="%s" placeholder="%s" min="0" />',
                    $common,
                    esc_attr( $value ),
                    esc_attr( $aria_label ),
                    esc_attr__( 'Attachment ID', 'space-core' )
            ),
            default => sprintf(
                    '<input type="text"%s value="%s" aria-label="%s" />',
                    $common,
                    esc_attr( $value ),
                    esc_attr( $aria_label )
            ),
        };
    }

    private function render_bulk_select_input( array $field, string $value, string $common, string $aria_label ): string {
        $html = sprintf( '<select%s aria-label="%s">', $common, esc_attr( $aria_label ) );
        $html .= '<option value=""></option>';

        foreach ( (array) ( $field['choices'] ?? [] ) as $choice ) {
            $choice_value = sanitize_text_field( is_array( $choice ) ? (string) ( $choice['value'] ?? '' ) : (string) $choice );
            $choice_label = is_array( $choice )
                    ? sanitize_text_field( (string) ( $choice['label_en'] ?? $choice_value ) )
                    : $choice_value;

            if ( '' === $choice_value && '' === $choice_label ) {
                continue;
            }

            $html .= sprintf(
                    '<option value="%s" %s>%s</option>',
                    esc_attr( $choice_value ),
                    selected( $value, $choice_value, false ),
                    esc_html( $choice_label )
            );
        }

        $html .= '</select>';

        return $html;
    }

    // ── Temporary product export/import tools ────────────────────

    public function handle_export_products(): void {
        if ( ! $this->current_user_can_access_bulk_management() ) {
            wp_die( esc_html__( 'Permission denied.', 'space-core' ) );
        }
        check_admin_referer( 'sc_bmc_export_products' );

        if ( ! post_type_exists( 'product' ) ) {
            wp_die( esc_html__( 'WooCommerce products not available.', 'space-core' ) );
        }

        $query = new WP_Query( [
                'post_type'      => 'product',
                'post_status'    => [ 'publish', 'draft', 'pending', 'future', 'private' ],
                'posts_per_page' => -1,
                'meta_query'     => [
                        [ 'key' => '_price', 'value' => '', 'compare' => '!=' ],
                ],
                'fields'         => 'ids',
                'no_found_rows'  => true,
        ] );

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="products-' . gmdate( 'Ymd-His' ) . '.csv"' );

        $out = fopen( 'php://output', 'w' );
        // UTF-8 BOM so Excel reads Arabic correctly.
        fwrite( $out, "\xEF\xBB\xBF" );
        fputcsv( $out, [ 'id', 'title_en', 'title_ar' ] );

        foreach ( $query->posts as $post_id ) {
            $post = get_post( $post_id );
            if ( ! $post || ! str_starts_with( (string) $post->post_title, '#' ) ) {
                continue;
            }
            $title_ar = MultilingualHelper::is_active()
                    ? MultilingualHelper::get_post_title_in_lang( $post_id, 'ar', 'product' )
                    : '';
            fputcsv( $out, [ $post_id, $post->post_title, $title_ar ] );
        }

        fclose( $out );
        exit;
    }

    public function handle_import_products(): void {
        if ( ! $this->current_user_can_access_bulk_management() ) {
            wp_die( esc_html__( 'Permission denied.', 'space-core' ) );
        }
        check_admin_referer( 'sc_bmc_import_products' );

        $redirect = admin_url( 'admin.php?page=sc-bulk-management' );

        if ( empty( $_FILES['csv']['tmp_name'] ) || ! is_uploaded_file( $_FILES['csv']['tmp_name'] ) ) {
            wp_safe_redirect( add_query_arg( 'sc_import', 'no_file', $redirect ) );
            exit;
        }

        $fh = fopen( $_FILES['csv']['tmp_name'], 'r' );
        if ( ! $fh ) {
            wp_safe_redirect( add_query_arg( 'sc_import', 'open_failed', $redirect ) );
            exit;
        }

        $header = fgetcsv( $fh );
        if ( ! $header ) {
            fclose( $fh );
            wp_safe_redirect( add_query_arg( 'sc_import', 'empty', $redirect ) );
            exit;
        }

        // Strip BOM from first header cell.
        if ( isset( $header[0] ) ) {
            $header[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $header[0] );
        }
        $cols = array_flip( array_map( 'strtolower', array_map( 'trim', $header ) ) );

        if ( ! isset( $cols['id'], $cols['title_en'] ) ) {
            fclose( $fh );
            wp_safe_redirect( add_query_arg( 'sc_import', 'bad_header', $redirect ) );
            exit;
        }

        $updated = 0;
        $skipped = 0;
        while ( ( $row = fgetcsv( $fh ) ) !== false ) {
            $id       = absint( $row[ $cols['id'] ] ?? 0 );
            $title_en = isset( $row[ $cols['title_en'] ] ) ? wp_strip_all_tags( (string) $row[ $cols['title_en'] ] ) : '';

            if ( ! $id || '' === $title_en ) {
                $skipped++;
                continue;
            }
            $post = get_post( $id );
            if ( ! $post || 'product' !== $post->post_type ) {
                $skipped++;
                continue;
            }
            wp_update_post( [ 'ID' => $id, 'post_title' => $title_en ] );
            $updated++;
        }
        fclose( $fh );

        wp_safe_redirect( add_query_arg( [
                'sc_import'         => 'ok',
                'sc_import_updated' => $updated,
                'sc_import_skipped' => $skipped,
        ], $redirect ) );
        exit;
    }
}
