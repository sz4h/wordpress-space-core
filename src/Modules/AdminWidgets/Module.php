<?php

namespace Space\Core\Modules\AdminWidgets;

defined( 'ABSPATH' ) || exit;

use Space\Core\Abstracts\AbstractModule;

/**
 * Admin Widgets:
 *  - sc-admin-widgets: hide dashboard widgets
 */
class Module extends AbstractModule {

    public function get_label(): string {
        return __( 'Admin Widgets', 'space-core' );
    }

    public function get_description(): string {
        return __( 'Hide / reorder dashboard widgets and admin menu items.', 'space-core' );
    }

    public function boot(): void {
        add_action( 'admin_menu', [ $this, 'register_pages' ] );
        add_action( 'wp_dashboard_setup', [ $this, 'snapshot_dashboard_widgets' ], 5 );
        add_action( 'wp_dashboard_setup', [ $this, 'remove_dashboard_widgets' ], 9999 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_sc_save_admin_widgets', [ $this, 'ajax_save' ] );
        add_action( 'wp_ajax_sc_refresh_widgets', [ $this, 'ajax_refresh_widgets' ] );
    }

    /**
     * Force a fresh snapshot of dashboard widgets by manually invoking
     * wp_dashboard_setup() from outside the dashboard page.
     */
    public function ajax_refresh_widgets(): void {
        check_ajax_referer( 'space_core_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [], 403 );
        }

        require_once ABSPATH . 'wp-admin/includes/dashboard.php';
        set_current_screen( 'dashboard' );

        // Temporarily disable our own removal so the snapshot captures everything.
        remove_action( 'wp_dashboard_setup', [ $this, 'remove_dashboard_widgets' ], 999 );

        global $wp_meta_boxes;
        $wp_meta_boxes['dashboard'] = [];
        wp_dashboard_setup();

        $this->snapshot_dashboard_widgets();
        wp_send_json_success( [
                'widgets' => get_option( 'space_core_widget_snapshot', [] ),
                'message' => __( 'Widgets refreshed.', 'space-core' ),
        ] );
    }

    public function snapshot_dashboard_widgets(): void {
        global $wp_meta_boxes;
        $widgets = [];
        foreach ( (array) ( $wp_meta_boxes['dashboard'] ?? [] ) as $context => $priorities ) {
            foreach ( $priorities as $boxes ) {
                foreach ( $boxes as $id => $box ) {
                    $widgets[ $id ] = wp_strip_all_tags( $box['title'] ?: $id );
                }
            }
        }
        if ( ! empty( $widgets ) ) {
            $existing = get_option( 'space_core_widget_snapshot', [] );
            if ( $existing !== $widgets ) {
                update_option( 'space_core_widget_snapshot', $widgets, false );
            }
        }
    }

    // ── Register two submenu pages ────────────────────────────────

    public function register_pages(): void {
        add_submenu_page(
                'space-core',
                __( 'Admin Widgets', 'space-core' ),
                __( 'Admin Widgets', 'space-core' ),
                'manage_options',
                'sc-admin-widgets',
                [ $this, 'render_widgets_page' ]
        );
    }

    public function enqueue_assets( string $hook ): void {
        if ( ! in_array( $hook, [ 'space-core_page_sc-admin-widgets' ], true ) ) {
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

    public function remove_dashboard_widgets(): void {
        $o      = $this->get_options();
        $hidden = $o ?? [];
        if ( empty( $hidden ) ) {
            return;
        }
        foreach ( $hidden as $widget_id ) {
            foreach ( [ 'normal', 'side', 'column3', 'column4' ] as $context ) {
                remove_meta_box( $widget_id, 'dashboard', $context );
            }
        }
    }

    private function get_options(): array {
        $saved = get_option( 'space_core_admin_widgets', [] );

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

        // Merge: only update keys present in the posted data (widgets page vs menu page).
        $current = array_map( 'sanitize_key', (array) $data );

        update_option( 'space_core_admin_widgets', $current );
        wp_send_json_success( [ 'message' => __( 'Settings saved.', 'space-core' ) ] );
    }

    // ── Page: Admin Widgets ───────────────────────────────────────

    public function render_widgets_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $widget_snapshot = get_option( 'space_core_widget_snapshot', [] );
        $hidden_widgets  = $this->get_options() ?? [];
        $nonce           = wp_create_nonce( 'space_core_admin' );
        ?>
        <div class="wrap sc-wrap">
            <h1>
                <span class="dashicons dashicons-star-filled sc-logo-icon"></span>
                <?php esc_html_e( 'Admin Widgets', 'space-core' ); ?>
                <span class="sc-by"><?php esc_html_e( 'by Space Zone', 'space-core' ); ?></span>
            </h1>
            <div class="sc-tab-content" style="border-top:1px solid #c3c4c7;margin-top:16px;">
                <p class="description"><?php esc_html_e( 'Check widgets to hide from the WordPress dashboard.', 'space-core' ); ?></p>
                <p>
                    <button type="button" class="button" id="sc-widgets-refresh">
                        <span class="dashicons dashicons-update" style="vertical-align:text-top;"></span>
                        <?php esc_html_e( 'Refresh Widget List', 'space-core' ); ?>
                    </button>
                    <span class="description"
                          style="margin-left:8px;"><?php esc_html_e( 'Click after installing or enabling plugins that add dashboard widgets.', 'space-core' ); ?></span>
                </p>
                <?php if ( empty( $widget_snapshot ) ) : ?>
                    <div class="notice notice-info inline">
                        <p><?php esc_html_e( 'No widgets detected yet. Click "Refresh Widget List" above.', 'space-core' ); ?></p>
                    </div>
                <?php else : ?>
                    <ul class="sc-cleaner-list" style="max-width:600px;">
                        <?php foreach ( $widget_snapshot as $id => $title ) : ?>
                            <li>
                                <label>
                                    <input type="checkbox" class="sc-hidden-widget"
                                           value="<?php echo esc_attr( $id ); ?>"
                                            <?php checked( in_array( $id, $hidden_widgets, true ) ); ?>>
                                    <?php echo esc_html( $title ); ?>
                                    <code style="font-size:.8rem;color:#888;"><?php echo esc_html( $id ); ?></code>
                                </label>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <p style="margin-top:16px;">
                        <button type="button" class="button button-primary" id="sc-widgets-save">
                            <?php esc_html_e( 'Save Widget Settings', 'space-core' ); ?>
                        </button>
                        <span id="sc-widgets-status" style="margin-left:10px;font-weight:600;"></span>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <script>
            jQuery(function ($) {
                $('#sc-widgets-refresh').on('click', function () {
                    var $btn = $(this);
                    $btn.prop('disabled', true);
                    $.post(spaceCore.ajaxUrl, {
                        action: 'sc_refresh_widgets',
                        nonce: spaceCore.nonce,
                    }, function (res) {
                        if (res.success) {
                            location.reload();
                        } else {
                            alert('<?php echo esc_js( __( 'Could not refresh widgets.', 'space-core' ) ); ?>');
                        }
                    }).always(function () {
                        $btn.prop('disabled', false);
                    });
                });

                $('#sc-widgets-save').on('click', function () {
                    var $btn = $(this);
                    var hiddenWidgets = [];
                    $('.sc-hidden-widget:checked').each(function () {
                        hiddenWidgets.push($(this).val());
                    });
                    $btn.prop('disabled', true);
                    $.post(spaceCore.ajaxUrl, {
                        action: 'sc_save_admin_widgets',
                        nonce: spaceCore.nonce,
                        data: JSON.stringify(hiddenWidgets),
                    }, function (res) {
                        $('#sc-widgets-status').text(res.success ? '<?php echo esc_js( __( 'Saved!', 'space-core' ) ); ?>' : '<?php echo esc_js( __( 'Error.', 'space-core' ) ); ?>')
                            .css('color', res.success ? '#2e7d32' : '#c62828');
                        setTimeout(function () {
                            $('#sc-widgets-status').text('');
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
