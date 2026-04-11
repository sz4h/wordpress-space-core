<?php

namespace Space\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin bootstrap class (singleton).
 */
final class Plugin {

    private static ?self $instance = null;

    private ModuleManager $module_manager;

    private function __construct() {
        $this->load_textdomain();
        $this->module_manager = new ModuleManager();
        $this->module_manager->init();
    }

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function load_textdomain(): void {
        load_plugin_textdomain(
            'space-core',
            false,
            dirname( plugin_basename( SPACE_CORE_FILE ) ) . '/languages'
        );
    }

    // -------------------------------------------------------------------------
    // Lifecycle hooks
    // -------------------------------------------------------------------------

    public static function activate(): void {
        // Run each module's activation routine.
        $manager = new ModuleManager();
        foreach ( $manager->all_modules() as $module ) {
            if ( method_exists( $module, 'on_activate' ) ) {
                $module->on_activate();
            }
        }
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        // Unschedule crons.
        foreach ( [ 'sc_stock_notify', 'sc_resolve_visitor_countries', 'sc_fetch_currency_rates' ] as $hook ) {
            $ts = wp_next_scheduled( $hook );
            if ( $ts ) wp_unschedule_event( $ts, $hook );
        }
        flush_rewrite_rules();
    }

    public static function uninstall(): void {
        global $wpdb;

        // Drop plugin tables.
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sc_stock_subscribers" ); // phpcs:ignore
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sc_ls_areas" );          // phpcs:ignore
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sc_ls_cities" );         // phpcs:ignore
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sc_visitors" );          // phpcs:ignore
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sc_store_notices" );     // phpcs:ignore
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sc_mc_currencies" );    // phpcs:ignore

        // Remove all plugin options.
        $option_keys = [
            'space_core_modules',
            'space_core_cpts',
            'space_core_cpt_relations',
            'space_core_taxonomies',
            'space_core_custom_fields',
            'space_core_menu_snapshot',
            'space_core_widget_snapshot',
            'space_core_woo_checkout_fields',
            'space_core_safe_svg',
            'space_core_whatsapp_float',
            'space_core_pwa',
            'space_core_custom_code',
            'space_core_stock_notifier',
            'space_core_admin_cleaner',
            'space_core_local_shipping',
            'space_core_main_config',
            'space_core_gift_wrap',
            'space_core_order_statuses',
            'space_core_store_notices',
            'space_core_admin_nav',
            'space_core_print_orders',
            'space_core_multi_currency',
            'space_core_menu_snapshot',
            'space_core_widget_snapshot',
        ];
        foreach ( $option_keys as $key ) {
            delete_option( $key );
        }
    }
}
