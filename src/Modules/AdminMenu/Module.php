<?php

namespace Space\Core\Modules\AdminMenu;

defined( 'ABSPATH' ) || exit;

use Space\Core\Abstracts\AbstractModule;

/**
 * Admin Menu
 *  - sc-admin-menu: reorder, rename, hide, and add admin menu items.
 */
class Module extends AbstractModule {

    private const OPTION = 'space_core_admin_menu';
    private const SNAPSHOT_OPTION = 'space_core_menu_snapshot';

    /** @var array<int,array{selector:string,url:string,open_new:bool}> */
    private array $link_rewrites = [];

    public function get_label(): string {
        return __( 'Admin Menu', 'space-core' );
    }

    public function get_description(): string {
        return __( 'Organize, hide, rename, and add WordPress admin menu links.', 'space-core' );
    }

    public function boot(): void {
        add_action( 'admin_menu', [ $this, 'register_pages' ] );
        add_action( 'admin_menu', [ $this, 'snapshot_menu' ], 998 );
        add_action( 'admin_menu', [ $this, 'apply_menu_customisations' ], 999 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_footer', [ $this, 'render_link_rewrite_script' ], 1000 );
        add_action( 'wp_ajax_sc_save_admin_menu', [ $this, 'ajax_save' ] );
        add_action( 'wp_ajax_sc_reset_admin_menu', [ $this, 'ajax_reset' ] );
    }

    public function register_pages(): void {
        add_submenu_page(
                'space-core',
                __( 'Admin Menu', 'space-core' ),
                __( 'Admin Menu', 'space-core' ),
                'manage_options',
                'sc-admin-menu',
                [ $this, 'render_menu_page' ]
        );
    }

    public function enqueue_assets( string $hook ): void {
        if ( ! in_array( $hook, [ 'space-core_page_sc-admin-menu' ], true ) ) {
            return;
        }

        wp_enqueue_script(
                'space-core-admin',
                SPACE_CORE_URL . 'assets/js/admin.js',
                [ 'jquery', 'jquery-ui-sortable' ],
                SPACE_CORE_VERSION,
                true
        );
        wp_enqueue_style( 'space-core-admin', SPACE_CORE_URL . 'assets/css/admin.css', [], SPACE_CORE_VERSION );
        wp_localize_script( 'space-core-admin', 'spaceCore', [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'space_core_admin' ),
        ] );
        wp_localize_script( 'space-core-admin', 'spaceAdminMenu', [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'space_core_admin' ),
                'roles'   => $this->get_role_labels(),
                'i18n'    => [
                        'saved'         => __( 'Saved!', 'space-core' ),
                        'error'         => __( 'Error.', 'space-core' ),
                        'saving'        => __( 'Saving...', 'space-core' ),
                        'reset_confirm' => __( 'Reset all admin menu customizations?', 'space-core' ),
                        'resetting'     => __( 'Resetting...', 'space-core' ),
                        'separator'     => __( '-- Separator --', 'space-core' ),
                        'custom_link'   => __( 'Custom Link', 'space-core' ),
                        'custom_url'    => __( 'Custom URL', 'space-core' ),
                        'submenu'       => __( 'Submenu', 'space-core' ),
                        'moved_out'     => __( 'Moved out', 'space-core' ),
                        'open_new'      => __( 'Open in new window', 'space-core' ),
                        'visibility'    => __( 'Visibility', 'space-core' ),
                        'roles'         => __( 'Roles', 'space-core' ),
                        'icon'          => __( 'Dashicon', 'space-core' ),
                ],
        ] );
    }

    private function get_role_labels(): array {
        $roles = function_exists( 'get_editable_roles' ) ? get_editable_roles() : wp_roles()->roles;
        $out   = [];
        foreach ( $roles as $role => $details ) {
            $out[ $role ] = translate_user_role( $details['name'] ?? $role );
        }

        return $out;
    }

    public function snapshot_menu(): void {
        global $menu, $submenu;

        $snapshot = [];
        foreach ( (array) $menu as $pos => $item ) {
            $slug = (string) ( $item[2] ?? '' );
            if ( '' === $slug ) {
                continue;
            }

            $classes      = (string) ( $item[4] ?? '' );
            $is_separator = str_contains( $classes, 'wp-menu-separator' ) || str_starts_with( $slug, 'separator' );
            $label        = $this->clean_menu_label( $item[0] ?? '' );
            if ( '' === $label ) {
                $label = $is_separator ? __( '-- Separator --', 'space-core' ) : $slug;
            }

            $subs = [];
            foreach ( (array) ( $submenu[ $slug ] ?? [] ) as $sub_pos => $sub ) {
                $sub_slug = (string) ( $sub[2] ?? '' );
                if ( '' === $sub_slug ) {
                    continue;
                }

                $sub_label = $this->clean_menu_label( $sub[0] ?? '' );
                $subs[]    = [
                        'id'         => $this->submenu_item_id( $slug, $sub_slug ),
                        'type'       => 'submenu',
                        'parent'     => $slug,
                        'slug'       => $sub_slug,
                        'label'      => $sub_label ?: $sub_slug,
                        'capability' => (string) ( $sub[1] ?? 'read' ),
                        'page_title' => $this->clean_menu_label( $sub[3] ?? $sub_label ),
                        'classes'    => (string) ( $sub[4] ?? '' ),
                        'pos'        => (string) $sub_pos,
                ];
            }

            $snapshot[] = [
                    'id'         => $this->menu_item_id( $slug ),
                    'type'       => $is_separator ? 'separator' : 'menu',
                    'slug'       => $slug,
                    'label'      => $label,
                    'capability' => (string) ( $item[1] ?? 'read' ),
                    'page_title' => $this->clean_menu_label( $item[3] ?? $label ),
                    'classes'    => $classes,
                    'hook'       => (string) ( $item[5] ?? '' ),
                    'icon'       => (string) ( $item[6] ?? '' ),
                    'pos'        => (string) $pos,
                    'subs'       => $subs,
            ];
        }

        if ( get_option( self::SNAPSHOT_OPTION, [] ) !== $snapshot ) {
            update_option( self::SNAPSHOT_OPTION, $snapshot, false );
        }
    }

    private function clean_menu_label( mixed $label ): string {
        return trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $label ) ) );
    }

    private function submenu_item_id( string $parent_slug, string $slug ): string {
        return 'submenu:' . $this->encode_id_value( $parent_slug ) . ':' . $this->encode_id_value( $slug );
    }

    private function encode_id_value( string $value ): string {
        return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' );
    }

    private function menu_item_id( string $slug ): string {
        return 'menu:' . $this->encode_id_value( $slug );
    }

    public function apply_menu_customisations(): void {
        global $menu, $submenu;

        $options = $this->get_options();
        $items   = $options['items'] ?? [];

        $this->register_virtual_top_level_items( $menu, $items );
        $this->apply_top_level_overrides( $menu, $items );
        $this->apply_submenu_overrides( $submenu, $items );
        $this->apply_submenu_order( $submenu, $options['submenu_order'] ?? [] );
        $this->apply_top_level_order( $options );
    }

    private function get_options(): array {
        $saved = get_option( self::OPTION, [] );
        if ( ! is_array( $saved ) ) {
            return $this->default_options();
        }

        if ( 2 === (int) ( $saved['version'] ?? 0 ) ) {
            return $this->sanitize_v2_options( $saved );
        }

        return $this->migrate_legacy_options( $saved );
    }

    private function default_options(): array {
        return [
                'version'       => 2,
                'order'         => [],
                'items'         => [],
                'submenu_order' => [],
        ];
    }

    private function sanitize_v2_options( array $data ): array {
        $clean = $this->default_options();

        foreach ( (array) ( $data['order'] ?? [] ) as $id ) {
            $id = sanitize_text_field( (string) $id );
            if ( '' !== $id ) {
                $clean['order'][] = $id;
            }
        }

        foreach ( (array) ( $data['items'] ?? [] ) as $id => $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $id = sanitize_text_field( (string) $id );
            if ( '' === $id ) {
                continue;
            }

            $type = sanitize_key( $item['type'] ?? 'menu' );
            if ( ! in_array( $type, [ 'menu', 'submenu', 'custom', 'separator', 'promoted_submenu' ], true ) ) {
                $type = 'menu';
            }

            $mode = sanitize_key( $item['visibility_mode'] ?? 'show' );
            if ( ! in_array( $mode, [ 'show', 'hide_all', 'hide_roles', 'hide_except_roles' ], true ) ) {
                $mode = 'show';
            }

            $clean['items'][ $id ] = [
                    'type'            => $type,
                    'slug'            => sanitize_text_field( $item['slug'] ?? '' ),
                    'parent'          => sanitize_text_field( $item['parent'] ?? '' ),
                    'label'           => sanitize_text_field( $item['label'] ?? '' ),
                    'url'             => esc_url_raw( $item['url'] ?? '' ),
                    'open_new'        => ! empty( $item['open_new'] ) ? 1 : 0,
                    'icon'            => sanitize_text_field( $item['icon'] ?? '' ),
                    'visibility_mode' => $mode,
                    'roles'           => array_values( array_unique( array_map( 'sanitize_key', (array) ( $item['roles'] ?? [] ) ) ) ),
            ];
        }

        foreach ( (array) ( $data['submenu_order'] ?? [] ) as $parent_slug => $ids ) {
            $parent_slug = sanitize_text_field( (string) $parent_slug );
            if ( '' === $parent_slug ) {
                continue;
            }

            $clean['submenu_order'][ $parent_slug ] = array_values( array_filter( array_map( 'sanitize_text_field', (array) $ids ) ) );
        }

        return $clean;
    }

    private function migrate_legacy_options( array $saved ): array {
        $options = $this->default_options();

        foreach ( (array) ( $saved['menu_order'] ?? [] ) as $slug ) {
            $options['order'][] = $this->menu_item_id( (string) $slug );
        }

        foreach ( (array) ( $saved['hidden_menus'] ?? [] ) as $slug ) {
            $id                      = $this->menu_item_id( (string) $slug );
            $options['items'][ $id ] = [
                    'type'            => 'menu',
                    'slug'            => (string) $slug,
                    'visibility_mode' => 'hide_all',
                    'roles'           => [],
            ];
        }

        foreach ( (array) ( $saved['hidden_submenus'] ?? [] ) as $entry ) {
            $parts = explode( '||', (string) $entry, 2 );
            if ( 2 !== count( $parts ) ) {
                continue;
            }

            $id                      = $this->submenu_item_id( $parts[0], $parts[1] );
            $options['items'][ $id ] = [
                    'type'            => 'submenu',
                    'parent'          => $parts[0],
                    'slug'            => $parts[1],
                    'visibility_mode' => 'hide_all',
                    'roles'           => [],
            ];
        }

        foreach ( (array) ( $saved['custom_menus'] ?? [] ) as $index => $custom ) {
            $url   = esc_url_raw( $custom['url'] ?? '' );
            $label = sanitize_text_field( $custom['label'] ?? '' );
            if ( '' === $url || '' === $label ) {
                continue;
            }

            $id                      = 'custom:' . md5( $label . '|' . $url . '|' . $index );
            $options['items'][ $id ] = [
                    'type'            => 'custom',
                    'label'           => $label,
                    'url'             => $url,
                    'icon'            => sanitize_text_field( $custom['icon'] ?? 'dashicons-admin-links' ),
                    'open_new'        => 0,
                    'visibility_mode' => 'show',
                    'roles'           => [],
            ];
            $options['order'][]      = $id;
        }

        foreach ( array_keys( (array) ( $saved['promoted_subs'] ?? [] ) ) as $entry ) {
            $parts = explode( '||', (string) $entry, 2 );
            if ( 2 !== count( $parts ) ) {
                continue;
            }

            $snapshot = $this->find_submenu_snapshot_item( $parts[0], $parts[1] );
            $label    = $snapshot['label'] ?? $parts[1];
            $url      = $this->menu_slug_to_url( $parts[1] );
            $id       = 'custom:' . md5( 'promoted|' . $entry );

            $options['items'][ $id ] = [
                    'type'            => 'custom',
                    'label'           => $label,
                    'url'             => $url,
                    'icon'            => 'dashicons-arrow-right-alt2',
                    'open_new'        => 0,
                    'visibility_mode' => 'show',
                    'roles'           => [],
            ];
            $options['order'][]      = $id;
        }

        return $this->sanitize_v2_options( $options );
    }

    private function find_submenu_snapshot_item( string $parent_slug, string $slug ): array {
        $snapshot = get_option( self::SNAPSHOT_OPTION, [] );
        if ( ! is_array( $snapshot ) ) {
            return [];
        }

        foreach ( $snapshot as $item ) {
            if ( ( $item['slug'] ?? '' ) !== $parent_slug ) {
                continue;
            }

            foreach ( (array) ( $item['subs'] ?? [] ) as $sub ) {
                if ( ( $sub['slug'] ?? '' ) === $slug ) {
                    return $sub;
                }
            }
        }

        return [];
    }

    private function menu_slug_to_url( string $slug ): string {
        $slug = html_entity_decode( $slug, ENT_QUOTES, 'UTF-8' );
        if ( '' === $slug || str_starts_with( $slug, 'separator' ) ) {
            return '';
        }

        if ( str_starts_with( $slug, 'http://' ) || str_starts_with( $slug, 'https://' ) ) {
            return $slug;
        }

        if ( str_contains( $slug, '.php' ) || str_contains( $slug, '?' ) ) {
            return admin_url( $slug );
        }

        return admin_url( 'admin.php?page=' . $slug );
    }

    private function register_virtual_top_level_items( array &$menu, array $items ): void {
        foreach ( $items as $id => $item ) {
            $type = $item['type'] ?? '';
            if ( ! in_array( $type, [
                            'custom',
                            'separator',
                            'promoted_submenu'
                    ], true ) || ! $this->is_item_visible( $item ) ) {
                continue;
            }
            if ( str_starts_with( (string) $id, 'menu:' ) ) {
                continue;
            }

            $class = $this->rewrite_class( $id );

            if ( 'separator' === $type ) {
                $menu[] = [
                        '',
                        'read',
                        $this->custom_separator_slug( $id ),
                        '',
                        'wp-menu-separator sc-am-virtual-separator ' . $class,
                        '',
                        '',
                ];
                continue;
            }

            if ( 'promoted_submenu' === $type ) {
                $parent_slug = sanitize_text_field( $item['parent'] ?? '' );
                $slug        = sanitize_text_field( $item['slug'] ?? '' );
                $url         = $this->promoted_submenu_url( $item );
                if ( '' === $slug || '' === $url ) {
                    continue;
                }

                $snapshot = $this->find_submenu_snapshot_item( $parent_slug, $slug );
                $label    = sanitize_text_field( $item['label'] ?? '' );
                if ( '' === $label ) {
                    $label = sanitize_text_field( $snapshot['label'] ?? $slug );
                }

                $menu[] = [
                        $label,
                        sanitize_text_field( $snapshot['capability'] ?? 'read' ),
                        $url,
                        $label,
                        'menu-top sc-am-virtual-promoted ' . $class,
                        'sc-am-promoted-' . substr( md5( $id ), 0, 12 ),
                        'dashicons-arrow-right-alt2',
                ];

                if ( ! empty( $item['open_new'] ) ) {
                    $this->add_link_rewrite( $class, '', true );
                }
                continue;
            }

            $url = esc_url_raw( $item['url'] ?? '' );
            if ( '' === $url ) {
                continue;
            }

            $label = sanitize_text_field( $item['label'] ?? '' );
            $icon  = sanitize_text_field( $item['icon'] ?? 'dashicons-admin-links' );
            if ( '' === $label ) {
                $label = __( 'Custom Link', 'space-core' );
            }
            if ( '' === $icon ) {
                $icon = 'dashicons-admin-links';
            }

            $menu[] = [
                    $label,
                    'read',
                    $url,
                    $label,
                    'menu-top sc-am-virtual-custom ' . $class,
                    'sc-am-custom-' . substr( md5( $id ), 0, 12 ),
                    $icon,
            ];

            if ( ! empty( $item['open_new'] ) ) {
                $this->add_link_rewrite( $class, '', true );
            }
        }
    }

    private function is_item_visible( array $item ): bool {
        $mode = $item['visibility_mode'] ?? 'show';
        if ( 'show' === $mode ) {
            return true;
        }
        if ( 'hide_all' === $mode ) {
            return false;
        }

        $configured_roles = array_filter( (array) ( $item['roles'] ?? [] ) );
        if ( empty( $configured_roles ) ) {
            return true;
        }

        $user_roles = (array) wp_get_current_user()->roles;
        $matches    = (bool) array_intersect( $configured_roles, $user_roles );

        if ( 'hide_roles' === $mode ) {
            return ! $matches;
        }

        if ( 'hide_except_roles' === $mode ) {
            return $matches;
        }

        return true;
    }

    private function rewrite_class( string $id ): string {
        return 'sc-am-link-' . substr( md5( $id ), 0, 12 );
    }

    private function custom_separator_slug( string $id ): string {
        return 'separator-sc-am-' . substr( md5( $id ), 0, 12 );
    }

    private function promoted_submenu_url( array $item ): string {
        $url = esc_url_raw( $item['url'] ?? '' );
        if ( '' !== $url ) {
            return $url;
        }

        return esc_url_raw( $this->menu_slug_to_url( (string) ( $item['slug'] ?? '' ) ) );
    }

    private function add_link_rewrite( string $class, string $url, bool $open_new ): void {
        if ( '' === $url && ! $open_new ) {
            return;
        }

        $this->link_rewrites[] = [
                'selector' => '#adminmenu a.' . sanitize_html_class( $class ),
                'url'      => esc_url( $url ),
                'open_new' => $open_new,
        ];
    }

    private function apply_top_level_overrides( array &$menu, array $items ): void {
        foreach ( $menu as $key => &$item ) {
            $slug = (string) ( $item[2] ?? '' );
            if ( '' === $slug ) {
                continue;
            }

            $id = $this->menu_item_id( $slug );
            if ( empty( $items[ $id ] ) ) {
                continue;
            }

            $config = $items[ $id ];
            if ( ! $this->is_item_visible( $config ) ) {
                unset( $menu[ $key ] );
                continue;
            }

            $label = sanitize_text_field( $config['label'] ?? '' );
            if ( '' !== $label && 'separator' !== ( $config['type'] ?? '' ) ) {
                $item[0] = $label;
                $item[3] = $label;
            }

            $url      = esc_url_raw( $config['url'] ?? '' );
            $open_new = ! empty( $config['open_new'] );
            if ( '' !== $url || $open_new ) {
                $class = $this->add_menu_class( $item, $this->rewrite_class( $id ) );
                $this->add_link_rewrite( $class, $url, $open_new );
            }
        }
        unset( $item );
    }

    private function add_menu_class( array &$item, string $class ): string {
        $item[4] = trim( (string) ( $item[4] ?? '' ) . ' ' . $class );

        return $class;
    }

    private function apply_submenu_overrides( array &$submenu, array $items ): void {
        foreach ( $submenu as $parent_slug => &$subs ) {
            foreach ( $subs as $key => &$sub ) {
                $slug = (string) ( $sub[2] ?? '' );
                if ( '' === $slug ) {
                    continue;
                }

                $id = $this->submenu_item_id( (string) $parent_slug, $slug );
                if ( empty( $items[ $id ] ) ) {
                    continue;
                }

                $config = $items[ $id ];
                if ( 'promoted_submenu' === ( $config['type'] ?? '' ) ) {
                    unset( $subs[ $key ] );
                    continue;
                }

                if ( ! $this->is_item_visible( $config ) ) {
                    unset( $subs[ $key ] );
                    continue;
                }

                $label = sanitize_text_field( $config['label'] ?? '' );
                if ( '' !== $label ) {
                    $sub[0] = $label;
                    $sub[3] = $label;
                }

                $url      = esc_url_raw( $config['url'] ?? '' );
                $open_new = ! empty( $config['open_new'] );
                if ( '' !== $url || $open_new ) {
                    $class = $this->add_submenu_class( $sub, $this->rewrite_class( $id ) );
                    $this->add_link_rewrite( $class, $url, $open_new );
                }
            }
            unset( $sub );
            $subs = array_values( $subs );
        }
        unset( $subs );
    }

    private function add_submenu_class( array &$item, string $class ): string {
        $item[4] = trim( (string) ( $item[4] ?? '' ) . ' ' . $class );

        return $class;
    }

    private function apply_submenu_order( array &$submenu, array $submenu_order ): void {
        foreach ( $submenu_order as $parent_slug => $ordered_ids ) {
            if ( empty( $submenu[ $parent_slug ] ) || ! is_array( $ordered_ids ) ) {
                continue;
            }

            $by_id = [];
            foreach ( $submenu[ $parent_slug ] as $sub ) {
                $slug = (string) ( $sub[2] ?? '' );
                if ( '' !== $slug ) {
                    $by_id[ $this->submenu_item_id( (string) $parent_slug, $slug ) ] = $sub;
                }
            }

            $ordered = [];
            foreach ( $ordered_ids as $id ) {
                if ( isset( $by_id[ $id ] ) ) {
                    $ordered[] = $by_id[ $id ];
                    unset( $by_id[ $id ] );
                }
            }

            foreach ( $by_id as $sub ) {
                $ordered[] = $sub;
            }

            $submenu[ $parent_slug ] = $ordered;
        }
    }

    private function apply_top_level_order( array $options ): void {
        $desired_slugs = [];
        foreach ( (array) ( $options['order'] ?? [] ) as $id ) {
            $slug = $this->top_level_slug_for_order_id( (string) $id, (array) ( $options['items'] ?? [] ) );
            if ( '' !== $slug ) {
                $desired_slugs[] = $slug;
            }
        }

        if ( empty( $desired_slugs ) ) {
            return;
        }

        add_filter( 'custom_menu_order', '__return_true' );
        add_filter( 'menu_order', function ( array $current_order ) use ( $desired_slugs ): array {
            $ordered = [];
            foreach ( $desired_slugs as $slug ) {
                if ( in_array( $slug, $current_order, true ) && ! in_array( $slug, $ordered, true ) ) {
                    $ordered[] = $slug;
                }
            }

            foreach ( $current_order as $slug ) {
                if ( ! in_array( $slug, $ordered, true ) ) {
                    $ordered[] = $slug;
                }
            }

            return $ordered;
        } );
    }

    private function top_level_slug_for_order_id( string $id, array $items ): string {
        $item = $items[ $id ] ?? [];
        $type = $item['type'] ?? '';

        if ( 'custom' === $type ) {
            return esc_url_raw( $item['url'] ?? '' );
        }

        if ( 'promoted_submenu' === $type ) {
            return $this->promoted_submenu_url( $item );
        }

        if ( ! empty( $item['slug'] ) && ! ( 'separator' === $type && ! str_starts_with( $id, 'menu:' ) ) ) {
            return sanitize_text_field( $item['slug'] );
        }

        if ( 'separator' === $type && ! str_starts_with( $id, 'menu:' ) ) {
            return $this->custom_separator_slug( $id );
        }

        return $this->slug_from_menu_item_id( $id );
    }

    private function slug_from_menu_item_id( string $id ): string {
        return str_starts_with( $id, 'menu:' ) ? $this->decode_id_value( substr( $id, 5 ) ) : '';
    }

    private function decode_id_value( string $value ): string {
        $value   = strtr( $value, '-_', '+/' );
        $padding = strlen( $value ) % 4;
        if ( $padding ) {
            $value .= str_repeat( '=', 4 - $padding );
        }

        $decoded = base64_decode( $value, true );

        return false === $decoded ? '' : $decoded;
    }

    public function render_link_rewrite_script(): void {
        if ( empty( $this->link_rewrites ) ) {
            return;
        }

        $rewrites = wp_json_encode( $this->link_rewrites );
        if ( ! $rewrites ) {
            return;
        }
        ?>
        <script id="sc-am-link-rewrites">
            (function () {
                var rewrites = <?php echo $rewrites; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
                rewrites.forEach(function (rewrite) {
                    document.querySelectorAll(rewrite.selector).forEach(function (link) {
                        if (rewrite.url) {
                            link.setAttribute('href', rewrite.url);
                        }
                        if (rewrite.open_new) {
                            link.setAttribute('target', '_blank');
                            link.setAttribute('rel', 'noopener noreferrer');
                        }
                    });
                });
            })();
        </script>
        <?php
    }

    public function ajax_save(): void {
        check_ajax_referer( 'space_core_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [], 403 );
        }

        $raw  = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : '{}'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $data = json_decode( $raw, true );
        if ( ! is_array( $data ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid data.', 'space-core' ) ] );
        }

        update_option( self::OPTION, $this->sanitize_v2_options( $data ) );
        wp_send_json_success( [ 'message' => __( 'Settings saved.', 'space-core' ) ] );
    }

    public function ajax_reset(): void {
        check_ajax_referer( 'space_core_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [], 403 );
        }

        delete_option( self::OPTION );
        wp_send_json_success( [ 'message' => __( 'Menu reset.', 'space-core' ) ] );
    }

    public function render_menu_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $options  = $this->get_options();
        $snapshot = get_option( self::SNAPSHOT_OPTION, [] );
        $items    = $this->get_render_items( is_array( $snapshot ) ? $snapshot : [], $options );
        $roles    = $this->get_role_labels();
        echo $this->view( 'admin/page', [
            'items' => $items,
            'roles' => $roles,
        ] );
    }

    private function get_render_items( array $snapshot, array $options ): array {
        $items       = [];
        $render_map  = [];
        $config      = (array) ( $options['items'] ?? [] );
        $order       = (array) ( $options['order'] ?? [] );
        $submenu_map = (array) ( $options['submenu_order'] ?? [] );
        $promoted    = $this->promoted_submenu_ids( $config );

        foreach ( $snapshot as $item ) {
            if ( ! is_array( $item ) || empty( $item['id'] ) ) {
                continue;
            }

            $id                = (string) $item['id'];
            $item['subs']      = $this->get_render_submenus( (array) ( $item['subs'] ?? [] ), $config, $submenu_map, $promoted );
            $render_map[ $id ] = $this->merge_render_item_config( $item, $config[ $id ] ?? [] );
        }

        foreach ( $config as $id => $item ) {
            if ( ! is_array( $item ) || isset( $render_map[ $id ] ) || ! in_array( $item['type'] ?? '', [
                            'custom',
                            'separator',
                            'promoted_submenu'
                    ], true ) ) {
                continue;
            }

            if ( 'promoted_submenu' === ( $item['type'] ?? '' ) ) {
                $snapshot_item = $this->find_submenu_snapshot_item_in_snapshot(
                        $snapshot,
                        (string) $id,
                        (string) ( $item['parent'] ?? '' ),
                        (string) ( $item['slug'] ?? '' )
                );

                if ( empty( $snapshot_item ) ) {
                    continue;
                }

                $render_map[ $id ] = $this->merge_render_item_config( $snapshot_item, $item );
                continue;
            }

            $render_map[ $id ] = $this->merge_render_item_config(
                    [
                            'id'    => $id,
                            'type'  => $item['type'],
                            'slug'  => 'separator' === $item['type'] ? $this->custom_separator_slug( (string) $id ) : '',
                            'label' => 'separator' === $item['type'] ? __( '-- Separator --', 'space-core' ) : __( 'Custom Link', 'space-core' ),
                            'subs'  => [],
                    ],
                    $item
            );
        }

        foreach ( $order as $id ) {
            if ( isset( $render_map[ $id ] ) ) {
                $items[] = $render_map[ $id ];
                unset( $render_map[ $id ] );
            }
        }

        foreach ( $render_map as $item ) {
            $items[] = $item;
        }

        return $items;
    }

    private function promoted_submenu_ids( array $config ): array {
        $ids = [];
        foreach ( $config as $id => $item ) {
            if ( is_array( $item ) && 'promoted_submenu' === ( $item['type'] ?? '' ) ) {
                $ids[] = (string) $id;
            }
        }

        return $ids;
    }

    private function get_render_submenus( array $subs, array $config, array $submenu_map, array $promoted_ids ): array {
        $by_id      = [];
        $parent     = '';
        $ordered    = [];
        $render_sub = [];

        foreach ( $subs as $sub ) {
            if ( ! is_array( $sub ) || empty( $sub['id'] ) ) {
                continue;
            }
            if ( in_array( (string) $sub['id'], $promoted_ids, true ) ) {
                continue;
            }
            $parent                       = (string) ( $sub['parent'] ?? $parent );
            $by_id[ (string) $sub['id'] ] = $this->merge_render_item_config( $sub, $config[ $sub['id'] ] ?? [] );
        }

        foreach ( (array) ( $submenu_map[ $parent ] ?? [] ) as $id ) {
            if ( isset( $by_id[ $id ] ) ) {
                $ordered[] = $by_id[ $id ];
                unset( $by_id[ $id ] );
            }
        }

        foreach ( $by_id as $sub ) {
            $render_sub[] = $sub;
        }

        return array_merge( $ordered, $render_sub );
    }

    private function merge_render_item_config( array $item, array $config ): array {
        foreach (
                [
                        'label',
                        'url',
                        'open_new',
                        'icon',
                        'visibility_mode',
                        'roles',
                        'slug',
                        'parent',
                        'type'
                ] as $key
        ) {
            if ( array_key_exists( $key, $config ) && ( '' !== $config[ $key ] || in_array( $key, [
                                    'url',
                                    'roles',
                                    'open_new'
                            ], true ) ) ) {
                $item[ $key ] = $config[ $key ];
            }
        }

        $item['visibility_mode'] = $item['visibility_mode'] ?? 'show';
        $item['roles']           = (array) ( $item['roles'] ?? [] );
        $item['open_new']        = ! empty( $item['open_new'] ) ? 1 : 0;

        return $item;
    }

    private function find_submenu_snapshot_item_in_snapshot( array $snapshot, string $id, string $parent_slug, string $slug ): array {
        foreach ( $snapshot as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            foreach ( (array) ( $item['subs'] ?? [] ) as $sub ) {
                if ( ! is_array( $sub ) ) {
                    continue;
                }

                if (
                        ( $id !== '' && ( $sub['id'] ?? '' ) === $id )
                        || ( ( $sub['parent'] ?? '' ) === $parent_slug && ( $sub['slug'] ?? '' ) === $slug )
                ) {
                    return $sub;
                }
            }
        }

        return [];
    }

    private function render_top_level_item( array $item, array $roles ): void {
        echo $this->view( 'admin/item-top-level', [
            'item'  => $item,
            'roles' => $roles,
        ] );
    }

    private function render_item_controls( array $item, array $roles ): void {
        echo $this->view( 'admin/item-controls', [
            'item'  => $item,
            'roles' => $roles,
        ] );
    }

    private function render_submenu_item( array $item, array $roles ): void {
        echo $this->view( 'admin/item-submenu', [
            'item'  => $item,
            'roles' => $roles,
        ] );
    }
}
