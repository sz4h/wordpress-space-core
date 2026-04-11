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
        echo $this->view( 'admin/page', [
            'widget_snapshot' => $widget_snapshot,
            'hidden_widgets'  => $hidden_widgets,
            'nonce'           => $nonce,
        ] );
    }
}
