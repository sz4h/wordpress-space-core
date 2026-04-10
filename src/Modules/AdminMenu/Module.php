<?php

namespace Space\Core\Modules\AdminMenu;

defined( 'ABSPATH' ) || exit;

use Space\Core\Abstracts\AbstractModule;

/**
 * Admin Menu
 *  - sc-admin-menu: reorder, hide and promote admin menu items
 */
class Module extends AbstractModule {

    public function get_label(): string {
        return __( 'Admin Menu', 'space-core' );
    }

    public function get_description(): string {
        return __( 'Promote submenus to top-level links. Add more menu links. Sort menu links', 'space-core' );
    }

    public function boot(): void {
        add_action( 'admin_menu', [ $this, 'register_pages' ] );
        add_action( 'admin_menu', [ $this, 'snapshot_menu' ], 998 );
        add_action( 'admin_menu', [ $this, 'apply_menu_customisations' ], 999 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_sc_save_admin_menu', [ $this, 'ajax_save' ] );
    }


    // ── Register two submenu pages ────────────────────────────────

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
        wp_enqueue_script( 'space-core-admin', SPACE_CORE_URL . 'assets/js/admin.js', [
                'jquery',
                'jquery-ui-sortable'
        ], SPACE_CORE_VERSION, true );
        wp_enqueue_style( 'space-core-admin', SPACE_CORE_URL . 'assets/css/admin.css', [], SPACE_CORE_VERSION );
        wp_localize_script( 'space-core-admin', 'spaceCore', [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'space_core_admin' ),
        ] );
    }

    // ── Snapshot helpers ──────────────────────────────────────────

    public function snapshot_menu(): void {
        global $menu, $submenu;
        $snapshot = [];
        foreach ( (array) $menu as $pos => $item ) {
            $slug  = $item[2] ?? '';
            $label = wp_strip_all_tags( $item[0] ?? '' );
            if ( empty( $slug ) || empty( $label ) ) {
                continue;
            }

            $subs = [];
            foreach ( (array) ( $submenu[ $slug ] ?? [] ) as $sub ) {
                $sub_slug  = $sub[2] ?? '';
                $sub_label = wp_strip_all_tags( $sub[0] ?? '' );
                if ( $sub_slug ) {
                    $subs[] = [ 'slug' => $sub_slug, 'label' => $sub_label ?: $sub_slug ];
                }
            }
            $snapshot[] = [
                    'slug'  => $slug,
                    'label' => $label,
                    'pos'   => (int) $pos,
                    'subs'  => $subs,
            ];
        }
        $existing = get_option( 'space_core_menu_snapshot', [] );
        if ( $existing !== $snapshot ) {
            update_option( 'space_core_menu_snapshot', $snapshot, false );
        }
    }

    public function apply_menu_customisations(): void {
        $o               = $this->get_options();
        $hidden_menus    = $o['hidden_menus'] ?? [];
        $hidden_submenus = $o['hidden_submenus'] ?? [];
        $menu_order      = $o['menu_order'] ?? [];
        $promoted        = $o['promoted_subs'] ?? [];

        // 1. Remove hidden top-level menus.
        foreach ( $hidden_menus as $slug ) {
            remove_menu_page( $slug );
        }

        // 2. Remove hidden submenus.
        foreach ( $hidden_submenus as $entry ) {
            $parts = explode( '||', $entry, 2 );
            if ( count( $parts ) === 2 ) {
                remove_submenu_page( $parts[0], $parts[1] );
            }
        }

        // 2b. Register custom user-defined top-level menu items.
        $custom_menus = $o['custom_menus'] ?? [];
        foreach ( $custom_menus as $idx => $custom ) {
            $label = $custom['label'] ?? '';
            $url   = $custom['url'] ?? '';
            $icon  = $custom['icon'] ?? 'dashicons-admin-links';
            if ( ! $label || ! $url ) {
                continue;
            }
            $slug = 'sc-custom-' . $idx;
            add_menu_page(
                    $label, $label, 'read', $slug,
                    static function () use ( $url ) {
                        wp_safe_redirect( $url );
                        exit;
                    },
                    $icon,
                    82 + (int) $idx
            );
        }

        // 3. Promote submenus to top-level via redirect page.
        $i = 0;
        foreach ( array_keys( $promoted ) as $key ) {
            [ $parent_slug, $sub_slug ] = explode( '||', $key, 2 );
            global $submenu;
            foreach ( (array) ( $submenu[ $parent_slug ] ?? [] ) as $sub ) {
                if ( ( $sub[2] ?? '' ) !== $sub_slug ) {
                    continue;
                }
                $sub_label   = wp_strip_all_tags( $sub[0] ?? $sub_slug );
                $target_slug = $sub_slug;
                $promo_slug  = 'sc-promoted-' . $i;
                add_menu_page(
                        $sub_label, $sub_label, $sub[1] ?? 'manage_options',
                        $promo_slug,
                        static function () use ( $target_slug ) {
                            wp_safe_redirect( admin_url( 'admin.php?page=' . $target_slug ) );
                            exit;
                        },
                        'dashicons-arrow-right-alt2',
                        81 + $i
                );
                $i ++;
                break;
            }
        }

        // 4. Reorder — merge saved order with any new items not yet in list.
        if ( ! empty( $menu_order ) ) {
            add_filter( 'custom_menu_order', '__return_true' );
            add_filter( 'menu_order', function ( array $current_order ) use ( $menu_order ): array {
                $ordered = $menu_order;
                foreach ( $current_order as $item ) {
                    if ( ! in_array( $item, $ordered, true ) ) {
                        $ordered[] = $item;
                    }
                }

                return $ordered;
            } );
        }
    }

    // ── Apply customisations ──────────────────────────────────────

    private function get_options(): array {
        $saved = get_option( 'space_core_admin_menu', [] );

        return is_array( $saved ) ? $saved : [];
    }

    // ── AJAX ──────────────────────────────────────────────────────

    public function ajax_save(): void {
        check_ajax_referer( 'space_core_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [], 403 );
        }

        $raw  = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : '{}'; // phpcs:ignore
        $data = json_decode( $raw, true );
        if ( ! is_array( $data ) ) {
            wp_send_json_error( [ 'message' => 'Invalid data.' ] );
        }

        $current = $this->get_options();

        if ( array_key_exists( 'hidden_menus', $data ) ) {
            $current['hidden_menus']    = array_map( 'sanitize_text_field', (array) $data['hidden_menus'] );
            $current['hidden_submenus'] = array_map( 'sanitize_text_field', (array) ( $data['hidden_submenus'] ?? [] ) );
            $current['menu_order']      = array_map( 'sanitize_text_field', (array) ( $data['menu_order'] ?? [] ) );
            $current['promoted_subs']   = array_map(
                    '__return_true',
                    array_flip( array_map( 'sanitize_text_field', (array) ( $data['promoted_subs'] ?? [] ) ) )
            );
            $custom_menus               = [];
            foreach ( (array) ( $data['custom_menus'] ?? [] ) as $cm ) {
                $label = sanitize_text_field( $cm['label'] ?? '' );
                $url   = esc_url_raw( $cm['url'] ?? '' );
                $icon  = sanitize_text_field( $cm['icon'] ?? 'dashicons-admin-links' );
                if ( $label && $url ) {
                    $custom_menus[] = [ 'label' => $label, 'url' => $url, 'icon' => $icon ];
                }
            }
            $current['custom_menus'] = $custom_menus;
        }

        update_option( 'space_core_admin_menu', $current );
        wp_send_json_success( [ 'message' => __( 'Settings saved.', 'space-core' ) ] );
    }

    // ── Page: Admin Menu ──────────────────────────────────────────

    public function render_menu_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $o               = $this->get_options();
        $menu_snapshot   = get_option( 'space_core_menu_snapshot', [] );
        $hidden_menus    = $o['hidden_menus'] ?? [];
        $hidden_submenus = $o['hidden_submenus'] ?? [];
        $menu_order      = $o['menu_order'] ?? [];
        $promoted        = array_keys( $o['promoted_subs'] ?? [] );
        $custom_menus    = $o['custom_menus'] ?? [];

        // Sort snapshot according to saved order.
        if ( ! empty( $menu_order ) ) {
            usort( $menu_snapshot, function ( $a, $b ) use ( $menu_order ) {
                $ia = array_search( $a['slug'], $menu_order, true );
                $ib = array_search( $b['slug'], $menu_order, true );

                return ( false === $ia ? 999 : $ia ) <=> ( false === $ib ? 999 : $ib );
            } );
        }

        $nonce = wp_create_nonce( 'space_core_admin' );
        ?>
        <div class="wrap sc-wrap">
            <h1>
                <span class="dashicons dashicons-star-filled sc-logo-icon"></span>
                <?php esc_html_e( 'Admin Menu', 'space-core' ); ?>
                <span class="sc-by"><?php esc_html_e( 'by Space Zone', 'space-core' ); ?></span>
            </h1>
            <div class="sc-tab-content" style="border-top:1px solid #c3c4c7;margin-top:16px;">
                <p class="description">
                    <?php esc_html_e( 'Drag to reorder. Check to hide. Use ↑ Promote to create a top-level sidebar link for a submenu item.', 'space-core' ); ?>
                </p>
                <?php if ( empty( $menu_snapshot ) ) : ?>
                    <div class="notice notice-info inline">
                        <p><?php esc_html_e( 'Menu snapshot not yet captured. Reload this page.', 'space-core' ); ?></p>
                    </div>
                <?php else : ?>
                    <ul class="sc-menu-sortable" id="sc-menu-sortable">
                        <?php foreach ( $menu_snapshot as $item ) :
                            $slug = $item['slug'];
                            $label = $item['label'];
                            $is_hidden = in_array( $slug, $hidden_menus, true );
                            ?>
                            <li class="sc-menu-item<?php echo $is_hidden ? ' sc-item-hidden' : ''; ?>"
                                data-slug="<?php echo esc_attr( $slug ); ?>">
                                <span class="sc-drag-handle dashicons dashicons-move"></span>
                                <label class="sc-menu-label">
                                    <input type="checkbox" class="sc-hidden-menu"
                                           value="<?php echo esc_attr( $slug ); ?>"
                                            <?php checked( $is_hidden ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                    <?php if ( $is_hidden ) : ?><span
                                            class="sc-hidden-badge"><?php esc_html_e( 'hidden', 'space-core' ); ?></span><?php endif; ?>
                                </label>
                                <?php if ( ! empty( $item['subs'] ) ) : ?>
                                    <ul class="sc-submenu-list">
                                        <?php foreach ( $item['subs'] as $sub ) :
                                            $key = $slug . '||' . $sub['slug'];
                                            $sub_hidden = in_array( $key, $hidden_submenus, true );
                                            $is_promoted = in_array( $key, $promoted, true );
                                            ?>
                                            <li>
                                                <label>
                                                    <input type="checkbox" class="sc-hidden-submenu"
                                                           value="<?php echo esc_attr( $key ); ?>"
                                                            <?php checked( $sub_hidden ); ?>>
                                                    <?php echo esc_html( $sub['label'] ); ?>
                                                </label>
                                                <button type="button"
                                                        class="button button-small sc-promote-sub<?php echo $is_promoted ? ' sc-promoted' : ''; ?>"
                                                        data-key="<?php echo esc_attr( $key ); ?>">
                                                    <?php echo $is_promoted ? '★ ' . esc_html__( 'Promoted', 'space-core' ) : '↑ ' . esc_html__( 'Promote', 'space-core' ); ?>
                                                </button>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <h2 style="margin-top:32px;"><?php esc_html_e( 'Custom Top-Level Links', 'space-core' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Add custom top-level items to the admin sidebar. Useful when you need a submenu to appear standalone but cannot promote it.', 'space-core' ); ?></p>
                    <table class="widefat" id="sc-custom-menus" style="max-width:820px;margin-top:8px;">
                        <thead>
                        <tr>
                            <th style="width:28%;"><?php esc_html_e( 'Label', 'space-core' ); ?></th>
                            <th><?php esc_html_e( 'URL', 'space-core' ); ?></th>
                            <th style="width:22%;"><?php esc_html_e( 'Dashicon', 'space-core' ); ?></th>
                            <th style="width:80px;"></th>
                        </tr>
                        </thead>
                        <tbody id="sc-custom-menus-body">
                        <?php foreach ( $custom_menus as $cm ) : ?>
                            <tr class="sc-custom-menu-row">
                                <td><input type="text" class="sc-cm-label regular-text"
                                           value="<?php echo esc_attr( $cm['label'] ?? '' ); ?>"></td>
                                <td><input type="text" class="sc-cm-url regular-text"
                                           value="<?php echo esc_attr( $cm['url'] ?? '' ); ?>" placeholder="https://…">
                                </td>
                                <td><input type="text" class="sc-cm-icon"
                                           value="<?php echo esc_attr( $cm['icon'] ?? 'dashicons-admin-links' ); ?>"
                                           placeholder="dashicons-admin-links"> <span
                                            class="<?php echo esc_attr( $cm['icon'] ?? 'dashicons-admin-links' ); ?>"
                                            style="vertical-align:middle;"></span></td>
                                <td>
                                    <button type="button" class="button sc-cm-remove"
                                            style="color:#c62828;"><?php esc_html_e( 'Remove', 'space-core' ); ?></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p>
                        <button type="button" class="button" id="sc-cm-add">
                            + <?php esc_html_e( 'Add Custom Link', 'space-core' ); ?></button>
                    </p>

                    <p style="margin-top:16px;">
                        <button type="button" class="button button-primary" id="sc-menu-save">
                            <?php esc_html_e( 'Save Menu Settings', 'space-core' ); ?>
                        </button>
                        <span id="sc-menu-status" style="margin-left:10px;font-weight:600;"></span>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <script>
            jQuery(function ($) {
                if ($.fn.sortable) {
                    $('#sc-menu-sortable').sortable({
                        handle: '.sc-drag-handle',
                        axis: 'y',
                        cursor: 'move',
                        items: '> li.sc-menu-item',
                        containment: 'parent',
                        tolerance: 'pointer'
                    });
                }

                $(document).on('click', '.sc-promote-sub', function () {
                    $(this).toggleClass('sc-promoted');
                    $(this).text($(this).hasClass('sc-promoted')
                        ? '★ <?php echo esc_js( __( 'Promoted', 'space-core' ) ); ?>'
                        : '↑ <?php echo esc_js( __( 'Promote', 'space-core' ) ); ?>');
                });

                // Custom menus add/remove.
                $('#sc-cm-add').on('click', function () {
                    var row = '<tr class="sc-custom-menu-row">' +
                        '<td><input type="text" class="sc-cm-label regular-text" value=""></td>' +
                        '<td><input type="text" class="sc-cm-url regular-text" value="" placeholder="https://…"></td>' +
                        '<td><input type="text" class="sc-cm-icon" value="dashicons-admin-links"> <span class="dashicons-admin-links" style="vertical-align:middle;"></span></td>' +
                        '<td><button type="button" class="button sc-cm-remove" style="color:#c62828;"><?php echo esc_js( __( 'Remove', 'space-core' ) ); ?></button></td>' +
                        '</tr>';
                    $('#sc-custom-menus-body').append(row);
                });
                $(document).on('click', '.sc-cm-remove', function () {
                    $(this).closest('tr').remove();
                });

                $('#sc-menu-save').on('click', function () {
                    var $btn = $(this);
                    var hiddenMenus = [];
                    var hiddenSubs = [];
                    var menuOrder = [];
                    var promoted = [];
                    var customMenus = [];

                    $('.sc-hidden-menu:checked').each(function () {
                        hiddenMenus.push($(this).val());
                    });
                    $('.sc-hidden-submenu:checked').each(function () {
                        hiddenSubs.push($(this).val());
                    });
                    $('#sc-menu-sortable .sc-menu-item').each(function () {
                        menuOrder.push($(this).data('slug'));
                    });
                    $('.sc-promote-sub.sc-promoted').each(function () {
                        promoted.push($(this).data('key'));
                    });
                    $('#sc-custom-menus-body .sc-custom-menu-row').each(function () {
                        var $r = $(this);
                        var label = $r.find('.sc-cm-label').val().trim();
                        var url = $r.find('.sc-cm-url').val().trim();
                        if (!label || !url) return;
                        customMenus.push({
                            label: label,
                            url: url,
                            icon: $r.find('.sc-cm-icon').val().trim() || 'dashicons-admin-links',
                        });
                    });

                    $btn.prop('disabled', true);
                    $.post(spaceCore.ajaxUrl, {
                        action: 'sc_save_admin_menu',
                        nonce: spaceCore.nonce,
                        data: JSON.stringify({
                            hidden_menus: hiddenMenus,
                            hidden_submenus: hiddenSubs,
                            menu_order: menuOrder,
                            promoted_subs: promoted,
                            custom_menus: customMenus,
                        }),
                    }, function (res) {
                        $('#sc-menu-status').text(res.success ? '<?php echo esc_js( __( 'Saved!', 'space-core' ) ); ?>' : '<?php echo esc_js( __( 'Error.', 'space-core' ) ); ?>')
                            .css('color', res.success ? '#2e7d32' : '#c62828');
                        setTimeout(function () {
                            $('#sc-menu-status').text('');
                        }, 3000);
                    }).always(function () {
                        $btn.prop('disabled', false);
                    });
                });
            });
        </script>
        <?php
    }
}
