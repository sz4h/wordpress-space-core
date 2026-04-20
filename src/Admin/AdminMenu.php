<?php

namespace Space\Core\Admin;

defined( 'ABSPATH' ) || exit;

use Space\Core\ModuleManager;

/**
 * Registers the top-level Space Core admin menu and per-module submenu pages.
 */
class AdminMenu {

    /** Modules that register their own pages and should NOT get a Space Core submenu. */
    private const SELF_MANAGED = [
            'stats',
            'local_shipping',
            'guest_orders',
            'admin_cleaner',
            'admin_menu',
            'store_notices'
    ];

    private ModuleManager $manager;

    public function __construct( ModuleManager $manager ) {
        $this->manager = $manager;
    }

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_menu', [ $this, 'register_tools_menu' ], 999 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'wp_ajax_sc_export_options', [ $this, 'ajax_export' ] );
        add_action( 'wp_ajax_sc_import_options', [ $this, 'ajax_import' ] );
    }

    public function register_menu(): void {
        add_menu_page(
                __( 'Space Core', 'space-core' ),
                __( 'Space Core', 'space-core' ),
                'manage_options',
                'space-core',
                [ $this, 'render_page' ],
                'dashicons-star-filled',
                60
        );

        // Rename first submenu from "Space Core" → "Modules".
        add_submenu_page( 'space-core', __( 'Modules', 'space-core' ), __( 'Modules', 'space-core' ), 'manage_options', 'space-core', [
                $this,
                'render_page'
        ] );

        // One submenu per enabled module that has render_settings() and isn't self-managed.
        $registry = $this->manager->registry();
        $enabled  = $this->manager->enabled_slugs();

        foreach ( $registry as $slug => $class ) {
            if ( ! in_array( $slug, $enabled, true ) ) {
                continue;
            }
            if ( in_array( $slug, self::SELF_MANAGED, true ) ) {
                continue;
            }

            $module = new $class( $slug );
            if ( ! method_exists( $module, 'render_settings' ) ) {
                continue;
            }

            $menu_slug = 'sc-' . str_replace( '_', '-', $slug );
            add_submenu_page(
                    'space-core',
                    $module->get_label(),
                    $module->get_label(),
                    'manage_options',
                    $menu_slug,
                    function () use ( $module ) {
                        $this->render_module_settings_page( $module );
                    }
            );
        }

    }

    private function render_module_settings_page( object $module ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap sc-wrap">
            <h1>
                <span class="dashicons dashicons-star-filled sc-logo-icon"></span>
                <?php echo esc_html( $module->get_label() ); ?>
                <span class="sc-by"><?php esc_html_e( 'by Space Zone', 'space-core' ); ?></span>
            </h1>
            <div class="sc-tab-content" style="border-top:1px solid #c3c4c7;margin-top:16px;">
                <?php $module->render_settings(); ?>
            </div>
        </div>
        <?php
    }

    public function register_tools_menu(): void {
        add_submenu_page( 'space-core', __( 'Tools', 'space-core' ), __( 'Tools', 'space-core' ), 'manage_options', 'sc-tools', [
                $this,
                'render_tools_page'
        ] );
    }

    public function enqueue_assets( string $hook ): void {

        wp_enqueue_style(
                'space-core-admin',
                SPACE_CORE_URL . 'assets/css/admin.css',
                [],
                SPACE_CORE_VERSION
        );
        wp_enqueue_script(
                'space-core-admin',
                SPACE_CORE_URL . 'assets/js/admin.js',
                [ 'jquery', 'jquery-ui-sortable', 'wp-color-picker', 'media-upload', 'thickbox' ],
                SPACE_CORE_VERSION,
                true
        );
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_media();
        wp_localize_script( 'space-core-admin', 'spaceCore', [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'space_core_admin' ),
        ] );
    }

    public function register_settings(): void {
        register_setting( 'space_core_modules_group', 'space_core_modules', [
                'type'              => 'array',
                'sanitize_callback' => [ $this, 'sanitize_modules' ],
                'default'           => [],
        ] );
    }

    // ── Export / Import ──────────────────────────────────────────

    public function sanitize_modules( mixed $value ): array {
        if ( ! is_array( $value ) ) {
            return [];
        }

        return array_map( 'absint', $value );
    }

    public function ajax_export(): void {
        check_ajax_referer( 'space_core_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [], 403 );
        }

        global $wpdb;
        $option_keys = $wpdb->get_col(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'space\_core\_%'"
        );
        $data        = [];
        foreach ( $option_keys as $key ) {
            $data[ $key ] = get_option( $key );
        }
        wp_send_json_success( [ 'options' => $data ] );
    }

    // ── Page renderers ───────────────────────────────────────────

    public function ajax_import(): void {
        check_ajax_referer( 'space_core_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [], 403 );
        }

        $raw  = isset( $_POST['options'] ) ? wp_unslash( $_POST['options'] ) : '{}'; // phpcs:ignore
        $data = json_decode( $raw, true );
        if ( ! is_array( $data ) ) {
            wp_send_json_error( [ 'message' => 'Invalid JSON.' ] );
        }

        $imported = 0;
        foreach ( $data as $key => $value ) {
            if ( ! str_starts_with( $key, 'space_core_' ) ) {
                continue;
            }
            update_option( sanitize_key( $key ), $value );
            $imported ++;
        }
        wp_send_json_success( [ 'message' => sprintf( __( 'Imported %d options.', 'space-core' ), $imported ) ] );
    }

    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $registry = $this->manager->registry();
        $enabled  = $this->manager->enabled_slugs();
        ?>
        <div class="wrap sc-wrap">
            <h1>
                <span class="dashicons dashicons-star-filled sc-logo-icon"></span>
                <?php esc_html_e( 'Space Core', 'space-core' ); ?>
                <span class="sc-by"><?php esc_html_e( 'by Space Zone', 'space-core' ); ?></span>
            </h1>
            <div class="sc-tab-content" style="border-top:1px solid #c3c4c7;margin-top:16px;">
                <?php $this->render_modules_tab( $registry, $enabled ); ?>
            </div>
        </div>
        <?php
    }

    private function render_modules_tab( array $registry, array $enabled ): void {
        ?>
        <form method="post" action="options.php" style="padding-top:24px;">
            <?php settings_fields( 'space_core_modules_group' ); ?>
            <div class="sc-modules-grid">
                <?php foreach ( $registry as $slug => $class ) :
                    $module = new $class( $slug );
                    $checked = in_array( $slug, $enabled, true );
                    ?>
                    <div class="sc-module-card <?php echo $checked ? 'sc-module-enabled' : ''; ?>">
                        <label class="sc-module-toggle">
                            <input type="checkbox"
                                   name="space_core_modules[<?php echo esc_attr( $slug ); ?>]"
                                   value="1"
                                    <?php checked( $checked ); ?> />
                            <span class="sc-toggle-slider"></span>
                        </label>
                        <div class="sc-module-info">
                            <strong><?php echo esc_html( $module->get_label() ); ?></strong>
                            <p><?php echo esc_html( $module->get_description() ); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php submit_button( __( 'Save Modules', 'space-core' ) ); ?>
        </form>
        <?php
    }

    public function render_tools_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $nonce = wp_create_nonce( 'space_core_admin' );
        ?>
        <div class="wrap sc-wrap">
            <h1>
                <span class="dashicons dashicons-star-filled sc-logo-icon"></span>
                <?php esc_html_e( 'Tools', 'space-core' ); ?>
                <span class="sc-by"><?php esc_html_e( 'by Space Zone', 'space-core' ); ?></span>
            </h1>
            <div class="sc-tab-content" style="border-top:1px solid #c3c4c7;margin-top:16px;">
                <div style="max-width:650px;padding-top:16px;">
                    <h2><?php esc_html_e( 'Export / Import Settings', 'space-core' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Export all Space Core settings to a JSON file, or import from a previously exported file.', 'space-core' ); ?></p>

                    <h3><?php esc_html_e( 'Export', 'space-core' ); ?></h3>
                    <p>
                        <button type="button" id="sc-tools-export" class="button button-primary"
                                data-nonce="<?php echo esc_attr( $nonce ); ?>">
                            <?php esc_html_e( 'Download Settings JSON', 'space-core' ); ?>
                        </button>
                    </p>

                    <hr style="margin:24px 0;">

                    <h3><?php esc_html_e( 'Import', 'space-core' ); ?></h3>
                    <p>
                        <input type="file" id="sc-tools-import-file" accept=".json"
                               style="display:block;margin-bottom:10px;">
                        <button type="button" id="sc-tools-import" class="button button-primary"
                                data-nonce="<?php echo esc_attr( $nonce ); ?>">
                            <?php esc_html_e( 'Import Settings', 'space-core' ); ?>
                        </button>
                        <span id="sc-tools-import-status" style="margin-left:10px;font-weight:600;"></span>
                    </p>
                    <div class="notice notice-warning inline">
                        <p><?php esc_html_e( 'Warning: importing will overwrite all current Space Core settings.', 'space-core' ); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <script>
            jQuery(function ($) {
                $('#sc-tools-export').on('click', function () {
                    var nonce = $(this).data('nonce');
                    $.post(spaceCore.ajaxUrl, {action: 'sc_export_options', nonce: nonce}, function (res) {
                        if (!res.success) {
                            alert('Export failed.');
                            return;
                        }
                        var blob = new Blob([JSON.stringify(res.data.options, null, 2)], {type: 'application/json'});
                        var a = document.createElement('a');
                        a.href = URL.createObjectURL(blob);
                        a.download = 'space-core-settings-<?php echo esc_js( gmdate( 'Y-m-d' ) ); ?>.json';
                        a.click();
                        URL.revokeObjectURL(a.href);
                    });
                });

                $('#sc-tools-import').on('click', function () {
                    var file = $('#sc-tools-import-file')[0].files[0];
                    if (!file) {
                        alert('Select a JSON file first.');
                        return;
                    }
                    var nonce = $(this).data('nonce');
                    var $btn = $(this);
                    var $status = $('#sc-tools-import-status');
                    var reader = new FileReader();
                    reader.onload = function (e) {
                        if (!confirm('<?php echo esc_js( __( 'This will overwrite all Space Core settings. Continue?', 'space-core' ) ); ?>')) return;
                        $btn.prop('disabled', true);
                        $.post(spaceCore.ajaxUrl, {
                            action: 'sc_import_options',
                            nonce: nonce,
                            options: e.target.result,
                        }, function (res) {
                            $status.text(res.success ? res.data.message : (res.data.message || 'Error'))
                                .css('color', res.success ? '#2e7d32' : '#c62828');
                            setTimeout(function () {
                                $status.text('');
                            }, 4000);
                        }).always(function () {
                            $btn.prop('disabled', false);
                        });
                    };
                    reader.readAsText(file);
                });
            });
        </script>
        <?php
    }
}
