<?php

namespace Space\Core\Modules\AdminNav;

defined( 'ABSPATH' ) || exit;

use Space\Core\Abstracts\AbstractModule;

/**
 * Admin Bottom Nav — configurable mobile-only fixed bottom navigation bar.
 *
 * Uses locally bundled Material Icons font.
 * Home link goes to SC Stats for shop managers, plain dashboard for lower roles.
 * Each nav item (label, icon, URL) is configurable in render_settings().
 */
class Module extends AbstractModule {

    public function get_label(): string {
        return __( 'Admin Bottom Nav', 'space-core' );
    }

    public function get_description(): string {
        return __( 'Adds a configurable mobile-friendly bottom navigation bar to the WordPress admin.', 'space-core' );
    }

    private function resolve_label( mixed $label ): string {
        if ( is_string( $label ) ) {
            return $label;
        }
        if ( ! is_array( $label ) ) {
            return '';
        }
        $lang = substr( get_locale(), 0, 2 );
        return $label[ $lang ] ?? $label['en'] ?? (string) reset( $label );
    }

    private function default_items(): array {
        $home_url = current_user_can( 'manage_woocommerce' )
            ? admin_url( 'admin.php?page=sc-stats' )
            : admin_url( 'index.php' );

        return [
            [ 'label' => [ 'en' => 'Home',     'ar' => 'الرئيسية'  ], 'url' => $home_url,                                    'icon' => 'home',          'match' => 'sc-stats' ],
            [ 'label' => [ 'en' => 'Orders',   'ar' => 'الطلبات'   ], 'url' => admin_url( 'edit.php?post_type=shop_order' ),  'icon' => 'shopping_cart', 'match' => 'post_type=shop_order' ],
            [ 'label' => [ 'en' => 'Products', 'ar' => 'المنتجات'  ], 'url' => admin_url( 'edit.php?post_type=product' ),     'icon' => 'inventory_2',   'match' => 'post_type=product' ],
            [ 'label' => [ 'en' => 'Coupons',  'ar' => 'الكوبونات' ], 'url' => admin_url( 'edit.php?post_type=shop_coupon' ), 'icon' => 'sell',          'match' => 'post_type=shop_coupon' ],
            [ 'label' => [ 'en' => 'Posts',    'ar' => 'المقالات'  ], 'url' => admin_url( 'edit.php' ),                       'icon' => 'article',       'match' => 'edit.php' ],
        ];
    }

    private function get_items(): array {
        $saved = get_option( 'space_core_admin_nav', [] );
        if ( ! is_array( $saved ) || empty( $saved ) ) {
            return $this->default_items();
        }
        // Resolve home URL dynamically regardless of saved URL.
        foreach ( $saved as &$item ) {
            if ( ( $item['match'] ?? '' ) === 'sc-stats' ) {
                $item['url'] = current_user_can( 'manage_woocommerce' )
                    ? admin_url( 'admin.php?page=sc-stats' )
                    : admin_url( 'index.php' );
            }
        }
        return $saved;
    }

    public function boot(): void {
        add_action( 'admin_footer', [ $this, 'render_nav' ], 100 );
        add_action( 'admin_head',   [ $this, 'render_styles' ] );
        add_action( 'wp_ajax_sc_save_admin_nav', [ $this, 'ajax_save' ] );
    }

    public function render_styles(): void {
        ?>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">
        <style id="sc-admin-nav-css">
        .sc-material-icon {
            font-family: 'Material Symbols Outlined';
            font-weight: normal;
            font-style: normal;
            font-size: 22px;
            line-height: 1;
            letter-spacing: normal;
            text-transform: none;
            display: inline-block;
            white-space: nowrap;
            word-wrap: normal;
            -webkit-font-feature-settings: 'liga';
            font-feature-settings: 'liga';
            -webkit-font-smoothing: antialiased;
        }
        body.wp-admin #wpcontent,
        body.wp-admin #wpfooter { padding-bottom: 80px !important; }

        #sc-admin-bottom-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 8px;
            right: 8px;
            z-index: 99999;
            background: #fff;
            border-radius: 16px 16px 0 0;
            box-shadow: 0 -2px 12px rgba(0,0,0,.12);
            height: 60px;
            align-items: stretch;
            overflow: hidden;
        }
        @media (max-width: 782px) {
            #sc-admin-bottom-nav { display: flex !important; }
        }
        #sc-admin-bottom-nav a {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #9e9e9e;
            text-decoration: none;
            font-size: 10px;
            gap: 2px;
            transition: color .15s, background .15s;
            padding: 6px 4px 4px;
        }
        #sc-admin-bottom-nav a:hover,
        #sc-admin-bottom-nav a.sc-nav-active {
            color: #2271b1;
            background: #f0f6ff;
        }
        #sc-admin-bottom-nav a.sc-nav-active .sc-material-icon {
            font-variation-settings: 'FILL' 1;
        }
        #sc-admin-bottom-nav a span.sc-nav-label {
            font-size: 9px;
            line-height: 1;
            font-weight: 500;
        }
        </style>
        <?php
    }

    public function render_nav(): void {
        $items   = $this->get_items();
        $current = $this->current_url();

        echo '<nav id="sc-admin-bottom-nav" aria-label="' . esc_attr__( 'Bottom Navigation', 'space-core' ) . '">';
        foreach ( $items as $item ) {
            $match  = $item['match'] ?? '';
            $active = $match && str_contains( $current, $match ) ? ' sc-nav-active' : '';
            $label  = $this->resolve_label( $item['label'] ?? '' );
            printf(
                '<a href="%s" class="%s" title="%s">'
                . '<span class="sc-material-icon" aria-hidden="true">%s</span>'
                . '<span class="sc-nav-label">%s</span>'
                . '</a>',
                esc_url( $item['url'] ),
                esc_attr( trim( $active ) ),
                esc_attr( $label ),
                esc_html( $item['icon'] ),
                esc_html( $label )
            );
        }
        echo '</nav>';
    }

    private function current_url(): string {
        global $pagenow;
        $qs = http_build_query( $_GET ); // phpcs:ignore
        return $pagenow . ( $qs ? '?' . $qs : '' );
    }

    public function ajax_save(): void {
        check_ajax_referer( 'space_core_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [], 403 );

        $raw   = isset( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : '[]'; // phpcs:ignore
        $items = json_decode( $raw, true );
        if ( ! is_array( $items ) ) wp_send_json_error();

        $clean = [];
        foreach ( $items as $item ) {
            $label_raw = $item['label'] ?? '';
            if ( is_array( $label_raw ) ) {
                $label = [
                    'en' => sanitize_text_field( $label_raw['en'] ?? '' ),
                    'ar' => sanitize_text_field( $label_raw['ar'] ?? '' ),
                ];
            } else {
                $label = sanitize_text_field( (string) $label_raw );
            }
            $clean[] = [
                'label' => $label,
                'url'   => esc_url_raw( $item['url'] ?? '' ),
                'icon'  => sanitize_text_field( $item['icon'] ?? 'home' ),
                'match' => sanitize_text_field( $item['match'] ?? '' ),
            ];
        }
        update_option( 'space_core_admin_nav', $clean );
        wp_send_json_success( [ 'message' => __( 'Saved!', 'space-core' ) ] );
    }

    public function render_settings(): void {
        $items = $this->get_items();
        $nonce = wp_create_nonce( 'space_core_admin' );
        echo $this->view( 'admin/settings', [
            'items' => $items,
            'nonce' => $nonce,
        ] );
    }
}
