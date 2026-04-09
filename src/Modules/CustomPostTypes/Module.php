<?php

namespace Space\Core\Modules\CustomPostTypes;

defined( 'ABSPATH' ) || exit;

use Space\Core\Abstracts\AbstractModule;

class Module extends AbstractModule {

    public function get_label(): string {
        return __( 'Custom Post Types', 'space-core' );
    }

    public function get_description(): string {
        return __( 'Register custom post types and define relationships between them.', 'space-core' );
    }

    public function boot(): void {
        add_action( 'init', [ $this, 'register_post_types' ], 5 );
        add_action( 'admin_notices', [ $this, 'flush_notice' ] );
        add_action( 'wp_ajax_sc_save_cpts', [ $this, 'ajax_save' ] );
        add_action( 'wp_ajax_sc_delete_cpt', [ $this, 'ajax_delete' ] );
    }

    public static function get_definitions(): array {
        $raw = get_option( 'space_core_cpts', '[]' );
        $def = json_decode( $raw, true );
        return is_array( $def ) ? $def : [];
    }

    public function register_post_types(): void {
        foreach ( self::get_definitions() as $cpt ) {
            if ( empty( $cpt['slug'] ) ) continue;
            $this->register_single( $cpt );
        }
    }

    private function register_single( array $cpt ): void {
        $slug   = sanitize_key( $cpt['slug'] );
        $name   = sanitize_text_field( $cpt['name'] ?? $slug );
        $plural = sanitize_text_field( $cpt['plural'] ?? $name . 's' );

        $labels = [
            'name'               => $plural,
            'singular_name'      => $name,
            'add_new'            => sprintf( __( 'Add New %s', 'space-core' ), $name ),
            'add_new_item'       => sprintf( __( 'Add New %s', 'space-core' ), $name ),
            'edit_item'          => sprintf( __( 'Edit %s', 'space-core' ), $name ),
            'new_item'           => sprintf( __( 'New %s', 'space-core' ), $name ),
            'view_item'          => sprintf( __( 'View %s', 'space-core' ), $name ),
            'search_items'       => sprintf( __( 'Search %s', 'space-core' ), $plural ),
            'not_found'          => sprintf( __( 'No %s found.', 'space-core' ), strtolower( $plural ) ),
            'not_found_in_trash' => sprintf( __( 'No %s found in Trash.', 'space-core' ), strtolower( $plural ) ),
            'menu_name'          => $plural,
        ];

        $supports = $cpt['supports'] ?? [ 'title', 'editor', 'thumbnail' ];
        if ( is_string( $supports ) ) {
            $supports = array_filter( array_map( 'trim', explode( ',', $supports ) ) );
        }

        register_post_type( $slug, [
            'labels'       => $labels,
            'public'       => (bool) ( $cpt['public'] ?? true ),
            'show_ui'      => true,
            'show_in_menu' => true,
            'supports'     => array_values( $supports ),
            'has_archive'  => (bool) ( $cpt['has_archive'] ?? false ),
            'rewrite'      => [ 'slug' => $slug ],
            'show_in_rest' => (bool) ( $cpt['show_in_rest'] ?? true ),
            'menu_icon'    => sanitize_text_field( $cpt['menu_icon'] ?? 'dashicons-admin-post' ),
        ] );
    }

    public function flush_notice(): void {
        if ( get_option( 'space_core_cpts_flush' ) ) {
            delete_option( 'space_core_cpts_flush' );
            flush_rewrite_rules();
        }
    }

    // ── AJAX ─────────────────────────────────────────────────────

    public function ajax_save(): void {
        check_ajax_referer( 'sc_cpts_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'space-core' ) ] );
        }

        $rows = json_decode( stripslashes( $_POST['rows'] ?? '[]' ), true );
        if ( ! is_array( $rows ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid data.', 'space-core' ) ] );
        }

        $supports_valid = [ 'title', 'editor', 'thumbnail', 'excerpt', 'comments', 'revisions', 'page-attributes', 'author', 'custom-fields' ];

        $clean = [];
        foreach ( $rows as $row ) {
            $slug = sanitize_key( $row['slug'] ?? '' );
            if ( empty( $slug ) ) continue;

            $supports_raw = array_map( 'sanitize_key', (array) ( $row['supports'] ?? [] ) );
            $supports     = array_values( array_intersect( $supports_raw, $supports_valid ) );
            if ( empty( $supports ) ) $supports = [ 'title', 'editor' ];

            $clean[] = [
                'slug'        => $slug,
                'name'        => sanitize_text_field( $row['name'] ?? $slug ),
                'plural'      => sanitize_text_field( $row['plural'] ?? '' ),
                'menu_icon'   => sanitize_text_field( $row['menu_icon'] ?? 'dashicons-admin-post' ),
                'public'      => (bool) ( $row['public'] ?? true ),
                'has_archive' => (bool) ( $row['has_archive'] ?? false ),
                'show_in_rest'=> (bool) ( $row['show_in_rest'] ?? true ),
                'supports'    => $supports,
            ];
        }

        update_option( 'space_core_cpts', wp_json_encode( $clean ) );
        update_option( 'space_core_cpts_flush', 1 );
        wp_send_json_success( [ 'message' => __( 'Post types saved.', 'space-core' ), 'rows' => $clean ] );
    }

    public function ajax_delete(): void {
        check_ajax_referer( 'sc_cpts_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error();
        }
        $slug = sanitize_key( $_POST['slug'] ?? '' );
        $defs = self::get_definitions();
        $defs = array_values( array_filter( $defs, fn( $d ) => $d['slug'] !== $slug ) );
        update_option( 'space_core_cpts', wp_json_encode( $defs ) );
        update_option( 'space_core_cpts_flush', 1 );
        wp_send_json_success();
    }

    // ── Settings UI ───────────────────────────────────────────────

    public function render_settings(): void {
        $nonce    = wp_create_nonce( 'sc_cpts_nonce' );
        $defs     = self::get_definitions();
        $supports_all = [
            'title'            => __( 'Title', 'space-core' ),
            'editor'           => __( 'Editor', 'space-core' ),
            'thumbnail'        => __( 'Featured Image', 'space-core' ),
            'excerpt'          => __( 'Excerpt', 'space-core' ),
            'comments'         => __( 'Comments', 'space-core' ),
            'revisions'        => __( 'Revisions', 'space-core' ),
            'page-attributes'  => __( 'Page Attributes', 'space-core' ),
            'author'           => __( 'Author', 'space-core' ),
            'custom-fields'    => __( 'Custom Fields', 'space-core' ),
        ];
        ?>
        <div class="sc-module-section">
            <h2><?php esc_html_e( 'Custom Post Types', 'space-core' ); ?></h2>
            <p><?php esc_html_e( 'Add, edit, and delete custom post types. Changes take effect immediately after saving.', 'space-core' ); ?></p>

            <div class="sc-table-wrap">
                <table class="widefat striped sc-ajax-table sc-responsive-table" id="sc-cpt-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Name', 'space-core' ); ?></th>
                            <th><?php esc_html_e( 'Plural', 'space-core' ); ?></th>
                            <th><?php esc_html_e( 'Slug', 'space-core' ); ?></th>
                            <th><?php esc_html_e( 'Menu Icon', 'space-core' ); ?></th>
                            <th><?php esc_html_e( 'Supports', 'space-core' ); ?></th>
                            <th><?php esc_html_e( 'Public', 'space-core' ); ?></th>
                            <th><?php esc_html_e( 'Archive', 'space-core' ); ?></th>
                            <th>REST</th>
                            <th><?php esc_html_e( 'Actions', 'space-core' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $defs as $i => $cpt ) : ?>
                        <tr class="sc-table-row" data-index="<?php echo esc_attr( $i ); ?>">
                            <td data-label="<?php esc_attr_e( 'Name', 'space-core' ); ?>">
                                <input type="text" class="sc-field" data-field="name" value="<?php echo esc_attr( $cpt['name'] ); ?>" placeholder="Project" />
                            </td>
                            <td data-label="<?php esc_attr_e( 'Plural', 'space-core' ); ?>">
                                <input type="text" class="sc-field" data-field="plural" value="<?php echo esc_attr( $cpt['plural'] ); ?>" placeholder="Projects" />
                            </td>
                            <td data-label="<?php esc_attr_e( 'Slug', 'space-core' ); ?>">
                                <input type="text" class="sc-field sc-slug-field" data-field="slug" value="<?php echo esc_attr( $cpt['slug'] ); ?>" placeholder="project" />
                            </td>
                            <td data-label="<?php esc_attr_e( 'Menu Icon', 'space-core' ); ?>">
                                <input type="text" class="sc-field" data-field="menu_icon" value="<?php echo esc_attr( $cpt['menu_icon'] ?? 'dashicons-admin-post' ); ?>" placeholder="dashicons-admin-post" />
                            </td>
                            <td data-label="<?php esc_attr_e( 'Supports', 'space-core' ); ?>">
                                <select class="sc-field sc-multiselect" data-field="supports" multiple size="4">
                                    <?php foreach ( $supports_all as $s_key => $s_label ) : ?>
                                        <option value="<?php echo esc_attr( $s_key ); ?>" <?php echo in_array( $s_key, (array) ( $cpt['supports'] ?? [] ), true ) ? 'selected' : ''; ?>>
                                            <?php echo esc_html( $s_label ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td data-label="<?php esc_attr_e( 'Public', 'space-core' ); ?>">
                                <input type="checkbox" class="sc-field sc-bool-field" data-field="public" <?php checked( $cpt['public'] ?? true ); ?> />
                            </td>
                            <td data-label="<?php esc_attr_e( 'Archive', 'space-core' ); ?>">
                                <input type="checkbox" class="sc-field sc-bool-field" data-field="has_archive" <?php checked( $cpt['has_archive'] ?? false ); ?> />
                            </td>
                            <td data-label="REST">
                                <input type="checkbox" class="sc-field sc-bool-field" data-field="show_in_rest" <?php checked( $cpt['show_in_rest'] ?? true ); ?> />
                            </td>
                            <td class="sc-row-actions" data-label="<?php esc_attr_e( 'Actions', 'space-core' ); ?>">
                                <button type="button" class="button button-small sc-delete-row" data-action="sc_delete_cpt" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-slug="<?php echo esc_attr( $cpt['slug'] ); ?>">
                                    <?php esc_html_e( 'Delete', 'space-core' ); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="sc-table-footer">
                    <button type="button" class="button sc-add-row" data-table="sc-cpt-table" data-template="sc-cpt-row-tpl">
                        + <?php esc_html_e( 'Add Post Type', 'space-core' ); ?>
                    </button>
                    <button type="button" class="button button-primary sc-save-table" data-table="sc-cpt-table" data-action="sc_save_cpts" data-nonce="<?php echo esc_attr( $nonce ); ?>">
                        <?php esc_html_e( 'Save All', 'space-core' ); ?>
                    </button>
                    <span class="sc-save-status"></span>
                </div>
            </div>
        </div>

        <script type="text/html" id="sc-cpt-row-tpl">
        <tr class="sc-table-row sc-new-row" data-index="__INDEX__">
            <td data-label="<?php esc_attr_e( 'Name', 'space-core' ); ?>">
                <input type="text" class="sc-field" data-field="name" value="" placeholder="Project" />
            </td>
            <td data-label="<?php esc_attr_e( 'Plural', 'space-core' ); ?>">
                <input type="text" class="sc-field" data-field="plural" value="" placeholder="Projects" />
            </td>
            <td data-label="<?php esc_attr_e( 'Slug', 'space-core' ); ?>">
                <input type="text" class="sc-field sc-slug-field" data-field="slug" value="" placeholder="project" />
            </td>
            <td data-label="<?php esc_attr_e( 'Menu Icon', 'space-core' ); ?>">
                <input type="text" class="sc-field" data-field="menu_icon" value="dashicons-admin-post" placeholder="dashicons-admin-post" />
            </td>
            <td data-label="<?php esc_attr_e( 'Supports', 'space-core' ); ?>">
                <select class="sc-field sc-multiselect" data-field="supports" multiple size="4">
                    <?php foreach ( $supports_all as $s_key => $s_label ) : ?>
                        <option value="<?php echo esc_attr( $s_key ); ?>" <?php selected( in_array( $s_key, [ 'title', 'editor', 'thumbnail' ], true ) ); ?>>
                            <?php echo esc_html( $s_label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td data-label="<?php esc_attr_e( 'Public', 'space-core' ); ?>">
                <input type="checkbox" class="sc-field sc-bool-field" data-field="public" checked />
            </td>
            <td data-label="<?php esc_attr_e( 'Archive', 'space-core' ); ?>">
                <input type="checkbox" class="sc-field sc-bool-field" data-field="has_archive" />
            </td>
            <td data-label="REST">
                <input type="checkbox" class="sc-field sc-bool-field" data-field="show_in_rest" checked />
            </td>
            <td class="sc-row-actions" data-label="<?php esc_attr_e( 'Actions', 'space-core' ); ?>">
                <button type="button" class="button button-small sc-remove-new-row"><?php esc_html_e( 'Remove', 'space-core' ); ?></button>
            </td>
        </tr>
        </script>
        <?php
    }
}
