<?php

namespace Space\Core\Modules\MainConfig;

defined( 'ABSPATH' ) || exit;

use Space\Core\Abstracts\AbstractModule;
use WP_Admin_Bar;

/**
 * Main Config — global WordPress/WooCommerce tweaks.
 *
 * Options stored under: space_core_main_config
 */
class Module extends AbstractModule {

    public function get_label(): string {
        return __( 'Main Config', 'space-core' );
    }

    public function get_description(): string {
        return __( 'Whitelabel WP admin, disable comments, change admin color, remove version info, and more.', 'space-core' );
    }

    public function boot(): void {
        $o = $this->opts();

        // ── Help / Screen Options ──────────────────────────────────
        if ( ! empty( $o['disable_help'] ) ) {
            add_action( 'admin_head', function () {
                echo '<style>#contextual-help-link-wrap,#screen-options-link-wrap{display:none!important;}</style>';
            } );
            add_filter( 'screen_options_show_screen', '__return_false' );
        }

        // ── Whitelabel: WP logo in admin bar ──────────────────────
        if ( ! empty( $o['whitelabel_logo'] ) ) {
            add_action( 'admin_bar_menu', [ $this, 'replace_wp_logo' ], 11 );
        }

        // ── Remove WP version from footer ─────────────────────────
        if ( ! empty( $o['remove_version'] ) ) {
            add_filter( 'update_footer', '__return_empty_string', 11 );
            add_filter( 'admin_footer_text', [ $this, 'custom_footer_text' ] );
        }

        // ── Remove WP version meta tag & generator ────────────────
        if ( ! empty( $o['remove_version'] ) ) {
            remove_action( 'wp_head', 'wp_generator' );
        }

        // ── Admin color scheme ─────────────────────────────────────
        if ( ! empty( $o['admin_color'] ) ) {
            add_filter( 'get_user_option_admin_color', function () use ( $o ) {
                return sanitize_key( $o['admin_color'] );
            } );
        }

        // ── Disable comments ──────────────────────────────────────
        if ( ! empty( $o['disable_comments'] ) ) {
            $post_types = (array) ( $o['disable_comments_post_types'] ?? [] );
            add_action( 'admin_menu', [ $this, 'remove_comments_admin_menu' ] );
            add_action( 'admin_bar_menu', [ $this, 'remove_comments_admin_bar' ], 999 );
            add_action( 'admin_head', [ $this, 'hide_comments_admin_bar_css' ] );
            add_action( 'wp_head', [ $this, 'hide_comments_admin_bar_css' ] );
            add_action( 'init', function () use ( $post_types ) {
                $types = empty( $post_types ) ? get_post_types() : $post_types;
                foreach ( $types as $pt ) {
                    if ( post_type_supports( $pt, 'comments' ) ) {
                        remove_post_type_support( $pt, 'comments' );
                        remove_post_type_support( $pt, 'trackbacks' );
                    }
                }
            } );
            add_filter( 'comments_open', '__return_false', 20, 2 );
            add_filter( 'pings_open', '__return_false', 20, 2 );
            add_filter( 'comments_array', '__return_empty_array', 10, 2 );
            add_action( 'admin_init', [ $this, 'redirect_comments_admin' ] );
        }

        // ── Disable pingback ──────────────────────────────────────
        if ( ! empty( $o['disable_pingback'] ) ) {
            // Remove pingback from XML-RPC.
            add_filter( 'xmlrpc_methods', function ( array $methods ): array {
                unset( $methods['pingback.ping'], $methods['pingback.extensions.getPingbacks'] );

                return $methods;
            } );
            // Remove X-Pingback header.
            add_filter( 'wp_headers', function ( array $headers ): array {
                unset( $headers['X-Pingback'] );

                return $headers;
            } );
            // Prevent self-pingbacks.
            add_action( 'pre_ping', function ( array &$links ): void {
                $home = get_option( 'home' );
                foreach ( $links as $key => $link ) {
                    if ( str_starts_with( $link, $home ) ) {
                        unset( $links[ $key ] );
                    }
                }
            } );
        }

        // ── Login page customization ──────────────────────────────
        $has_login_custom = ! empty( $o['login_custom_logo'] ) || ! empty( $o['login_bg_color'] )
                            || ! empty( $o['login_bg_image'] ) || ! empty( $o['login_layout'] );
        if ( $has_login_custom ) {
            add_action( 'login_head', [ $this, 'inject_login_styles' ] );
        }
        if ( ! empty( $o['login_custom_logo'] ) ) {
            add_filter( 'login_headerurl', fn() => home_url( '/' ) );
            add_filter( 'login_headertext', fn() => get_bloginfo( 'name' ) );
        }
        if ( ( $o['login_layout'] ?? 'standard' ) === 'side' ) {
            add_filter( 'login_body_class', [ $this, 'add_login_body_class' ] );
            add_action( 'login_header', [ $this, 'inject_login_brand_panel' ] );
        }

        // ── Disable checkout shipping ─────────────────────────────
        if ( ! empty( $o['disable_checkout_shipping'] ) ) {
            add_filter( 'woocommerce_cart_needs_shipping', '__return_false' );
        }
        if ( ! empty( $o['disable_checkout_shipping_address'] ) ) {
            add_filter( 'woocommerce_cart_needs_shipping_address', '__return_false' );
        }

        // ── Settings save ─────────────────────────────────────────
        add_action( 'wp_ajax_sc_save_main_config', [ $this, 'ajax_save' ] );
    }

    private function opts(): array {
        $o = get_option( 'space_core_main_config', [] );

        return is_array( $o ) ? $o : [];
    }

    public function replace_wp_logo( WP_Admin_Bar $admin_bar ): void {
        $o    = $this->opts();
        $site = sanitize_text_field( $o['site_name'] ?? get_bloginfo( 'name' ) );
        $admin_bar->remove_node( 'wp-logo' );
        $admin_bar->add_node( [
                'id'    => 'sc-site-logo',
                'title' => '<span style="color:#fff;font-weight:700;font-size:13px;">' . esc_html( $site ) . '</span>',
                'href'  => admin_url(),
                'meta'  => [ 'class' => 'sc-whitelabel-logo' ],
        ] );
    }

    public function custom_footer_text(): string {
        $o    = $this->opts();
        $text = sanitize_text_field( $o['footer_text'] ?? '' );
        $url  = esc_url( $o['footer_url'] ?? '' );

        if ( '' === $text ) {
            return '';
        }

        if ( '' !== $url ) {
            return sprintf(
                    '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                    esc_url( $url ),
                    esc_html( $text )
            );
        }

        return esc_html( $text );
    }

    public function remove_comments_admin_menu(): void {
        remove_menu_page( 'edit-comments.php' );
        remove_submenu_page( 'options-general.php', 'options-discussion.php' );
    }

    public function remove_comments_admin_bar( WP_Admin_Bar $admin_bar ): void {
        $admin_bar->remove_node( 'comments' );
    }

    public function hide_comments_admin_bar_css(): void {
        echo '<style id="sc-hide-admin-bar-comments">#wp-admin-bar-comments{display:none!important;}</style>';
    }

    public function redirect_comments_admin(): void {
        global $pagenow;
        if ( 'edit-comments.php' === $pagenow || 'options-discussion.php' === $pagenow ) {
            wp_safe_redirect( admin_url() );
            exit;
        }
    }

    // ── Login page ───────────────────────────────────────────────

    public function inject_login_styles(): void {
        $o          = $this->opts();
        $logo_id    = absint( $o['login_custom_logo'] ?? 0 );
        $bg_col     = sanitize_hex_color( $o['login_bg_color'] ?? '' ) ?: '';
        $bg_img_id  = absint( $o['login_bg_image'] ?? 0 );
        $layout     = $o['login_layout'] ?? 'standard';
        $logo_url   = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
        $bg_img_url = $bg_img_id ? wp_get_attachment_image_url( $bg_img_id, 'full' ) : '';

        $vars = '';
        if ( $bg_col ) {
            $vars .= '--sc-login-bg-color:' . esc_attr( $bg_col ) . ';';
        }
        if ( $bg_img_url ) {
            $vars .= "--sc-login-bg-img:url('" . esc_url( $bg_img_url ) . "');";
        }
        if ( $logo_url ) {
            $vars .= "--sc-login-logo-url:url('" . esc_url( $logo_url ) . "');";
        }

        if ( $vars ) {
            echo '<style id="sc-login-vars">:root{' . $vars . '}</style>';
        }

        if ( 'side' === $layout ) {
            ?>
            <style id="sc-login-side">
                body.login.sc-login-side {
                    display: flex;
                    min-height: 100vh;
                    padding: 0;
                    margin: 0;
                    background: var(--sc-login-bg-color, #1a1a2e);
                }

                .sc-login-brand {
                    flex: 0 0 45%;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background: var(--sc-login-bg-color, #1a1a2e) var(--sc-login-bg-img, none) center/cover no-repeat;
                    padding: 40px;
                }

                .sc-login-brand img {
                    max-width: 200px;
                    max-height: 160px;
                    object-fit: contain;
                }

                body.login.sc-login-side #login {
                    flex: 1;
                    display: grid;
                    grid-template-columns: repeat(2, 1fr);
                    align-items: center;
                    justify-content: center;
                    flex-direction: column;
                    background: #f0f0f1;
                    padding: 40px 20px;
                    min-height: 100vh;
                }

                body.login.sc-login-side #login h1 {
                    display: none;
                }

                body.login.sc-login-side #login form {
                    background: #fff;
                    grid-column-start: 1;
                    grid-column-end: 3;
                }

                body.login.sc-login-side .privacy-policy-page-link {
                    display: none;
                }

                body.login.sc-login-side #nav, body.login.sc-login-side #backtoblog {
                    margin: 0;
                    padding: 0;
                    text-align: center;
                }

                body.login.sc-login-side.wp-core-ui .button-primary {
                    background: var(--sc-login-bg-color, #1a1a2e);
                    border-color: var(--sc-login-bg-color, #1a1a2e);
                }
            </style>
            <?php
        }

        if ( $logo_url ) {
            echo '<style id="sc-login-logo">body.login #login h1 a {'
                 . 'background-image:var(--sc-login-logo-url)!important;'
                 . 'background-size:contain!important;background-position:center!important;'
                 . 'background-repeat:no-repeat!important;width:100%!important;height:80px!important;'
                 . '}</style>';
        }
    }

    public function add_login_body_class( array $classes ): array {
        $classes[] = 'sc-login-side';

        return $classes;
    }

    public function inject_login_brand_panel(): void {
        $o        = $this->opts();
        $logo_id  = absint( $o['login_custom_logo'] ?? 0 );
        $logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
        echo '<div class="sc-login-brand">';
        if ( $logo_url ) {
            echo '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '">';
        }
        echo '</div>';
    }

    public function ajax_save(): void {
        check_ajax_referer( 'space_core_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [], 403 );
        }

        $raw  = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : '{}'; // phpcs:ignore
        $data = json_decode( $raw, true );
        if ( ! is_array( $data ) ) {
            wp_send_json_error();
        }

        $bools = [ 'disable_help', 'whitelabel_logo', 'remove_version', 'disable_comments', 'disable_pingback', 'disable_checkout_shipping', 'disable_checkout_shipping_address' ];
        $clean = [];
        foreach ( $bools as $key ) {
            $clean[ $key ] = ! empty( $data[ $key ] ) ? 1 : 0;
        }
        $clean['admin_color']                 = sanitize_key( $data['admin_color'] ?? '' );
        $clean['site_name']                   = sanitize_text_field( $data['site_name'] ?? '' );
        $clean['footer_text']                 = sanitize_text_field( $data['footer_text'] ?? '' );
        $clean['footer_url']                  = esc_url_raw( $data['footer_url'] ?? '' );
        $clean['disable_comments_post_types'] = array_map( 'sanitize_key', (array) ( $data['disable_comments_post_types'] ?? [] ) );
        $clean['login_custom_logo']           = absint( $data['login_custom_logo'] ?? 0 );
        $clean['login_bg_color']              = sanitize_hex_color( $data['login_bg_color'] ?? '' ) ?? '';
        $clean['login_bg_image']              = absint( $data['login_bg_image'] ?? 0 );
        $clean['login_layout']                = in_array( $data['login_layout'] ?? '', [ 'standard', 'side' ], true )
                ? $data['login_layout'] : 'standard';

        update_option( 'space_core_main_config', $clean );
        wp_send_json_success( [ 'message' => __( 'Settings saved.', 'space-core' ) ] );
    }

    public function render_settings(): void {
        $o      = $this->opts();
        $nonce  = wp_create_nonce( 'space_core_admin' );
        $colors = [
                'fresh'     => __( 'Default (Blue)', 'space-core' ),
                'light'     => __( 'Light', 'space-core' ),
                'modern'    => __( 'Modern', 'space-core' ),
                'blue'      => __( 'Blue', 'space-core' ),
                'coffee'    => __( 'Coffee', 'space-core' ),
                'ectoplasm' => __( 'Ectoplasm', 'space-core' ),
                'midnight'  => __( 'Midnight', 'space-core' ),
                'ocean'     => __( 'Ocean', 'space-core' ),
                'sunrise'   => __( 'Sunrise', 'space-core' ),
        ];

        // Get post types for comment disable.
        $post_types = get_post_types( [ 'public' => true ], 'objects' );
        echo $this->view( 'admin/settings', [
                'options'    => $o,
                'nonce'      => $nonce,
                'colors'     => $colors,
                'post_types' => $post_types,
        ] );
    }
}
