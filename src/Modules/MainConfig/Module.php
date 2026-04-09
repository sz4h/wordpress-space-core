<?php

namespace Space\Core\Modules\MainConfig;

defined( 'ABSPATH' ) || exit;

use Space\Core\Abstracts\AbstractModule;

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

    private function opts(): array {
        $o = get_option( 'space_core_main_config', [] );
        return is_array( $o ) ? $o : [];
    }

    public function boot(): void {
        $o = $this->opts();

        // ── Help / Screen Options ──────────────────────────────────
        if ( ! empty( $o['disable_help'] ) ) {
            add_action( 'admin_head', function() {
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
            add_filter( 'get_user_option_admin_color', function() use ( $o ) {
                return sanitize_key( $o['admin_color'] );
            } );
        }

        // ── Disable comments ──────────────────────────────────────
        if ( ! empty( $o['disable_comments'] ) ) {
            $post_types = (array) ( $o['disable_comments_post_types'] ?? [] );
            add_action( 'admin_menu',   [ $this, 'remove_comments_admin_menu' ] );
            add_action( 'init',         function() use ( $post_types ) {
                $types = empty( $post_types ) ? get_post_types() : $post_types;
                foreach ( $types as $pt ) {
                    if ( post_type_supports( $pt, 'comments' ) ) {
                        remove_post_type_support( $pt, 'comments' );
                        remove_post_type_support( $pt, 'trackbacks' );
                    }
                }
            } );
            add_filter( 'comments_open', '__return_false', 20, 2 );
            add_filter( 'pings_open',    '__return_false', 20, 2 );
            add_filter( 'comments_array', '__return_empty_array', 10, 2 );
            add_action( 'admin_init',   [ $this, 'redirect_comments_admin' ] );
        }

        // ── Disable pingback ──────────────────────────────────────
        if ( ! empty( $o['disable_pingback'] ) ) {
            // Remove pingback from XML-RPC.
            add_filter( 'xmlrpc_methods', function( array $methods ): array {
                unset( $methods['pingback.ping'], $methods['pingback.extensions.getPingbacks'] );
                return $methods;
            } );
            // Remove X-Pingback header.
            add_filter( 'wp_headers', function( array $headers ): array {
                unset( $headers['X-Pingback'] );
                return $headers;
            } );
            // Prevent self-pingbacks.
            add_action( 'pre_ping', function( array &$links ): void {
                $home = get_option( 'home' );
                foreach ( $links as $key => $link ) {
                    if ( str_starts_with( $link, $home ) ) {
                        unset( $links[ $key ] );
                    }
                }
            } );
        }

        // ── Settings save ─────────────────────────────────────────
        add_action( 'wp_ajax_sc_save_main_config', [ $this, 'ajax_save' ] );
    }

    public function replace_wp_logo( \WP_Admin_Bar $admin_bar ): void {
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
        $o = $this->opts();
        return esc_html( sanitize_text_field( $o['footer_text'] ?? '' ) );
    }

    public function remove_comments_admin_menu(): void {
        remove_menu_page( 'edit-comments.php' );
        remove_submenu_page( 'options-general.php', 'options-discussion.php' );
    }

    public function redirect_comments_admin(): void {
        global $pagenow;
        if ( 'edit-comments.php' === $pagenow || 'options-discussion.php' === $pagenow ) {
            wp_safe_redirect( admin_url() );
            exit;
        }
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

        $bools = [ 'disable_help', 'whitelabel_logo', 'remove_version', 'disable_comments', 'disable_pingback' ];
        $clean = [];
        foreach ( $bools as $key ) {
            $clean[ $key ] = ! empty( $data[ $key ] ) ? 1 : 0;
        }
        $clean['admin_color']                  = sanitize_key( $data['admin_color'] ?? '' );
        $clean['site_name']                    = sanitize_text_field( $data['site_name'] ?? '' );
        $clean['footer_text']                  = sanitize_text_field( $data['footer_text'] ?? '' );
        $clean['disable_comments_post_types']  = array_map( 'sanitize_key', (array) ( $data['disable_comments_post_types'] ?? [] ) );

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
        ?>
        <div class="sc-main-config-wrap" style="max-width:700px;">
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Disable Help & Screen Options', 'space-core' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" id="sc-mc-disable-help" <?php checked( ! empty( $o['disable_help'] ) ); ?>>
                            <?php esc_html_e( 'Hide the Help tab and Screen Options button for all users.', 'space-core' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Whitelabel WP Logo', 'space-core' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" id="sc-mc-whitelabel" <?php checked( ! empty( $o['whitelabel_logo'] ) ); ?>>
                            <?php esc_html_e( 'Replace the WP logo in the admin bar with a site name.', 'space-core' ); ?>
                        </label>
                        <br><br>
                        <label><?php esc_html_e( 'Site name to display:', 'space-core' ); ?>
                            <input type="text" id="sc-mc-site-name" class="regular-text"
                                   value="<?php echo esc_attr( $o['site_name'] ?? get_bloginfo( 'name' ) ); ?>"
                                   placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Remove WP Version & Copyrights', 'space-core' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" id="sc-mc-remove-version" <?php checked( ! empty( $o['remove_version'] ) ); ?>>
                            <?php esc_html_e( 'Remove WordPress version from admin footer and generator meta tag.', 'space-core' ); ?>
                        </label>
                        <br><br>
                        <label><?php esc_html_e( 'Custom footer text:', 'space-core' ); ?>
                            <input type="text" id="sc-mc-footer-text" class="regular-text"
                                   value="<?php echo esc_attr( $o['footer_text'] ?? '' ); ?>"
                                   placeholder="<?php esc_attr_e( 'Powered by Your Brand', 'space-core' ); ?>">
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Admin Color Scheme', 'space-core' ); ?></th>
                    <td>
                        <select id="sc-mc-admin-color">
                            <option value=""><?php esc_html_e( '— User default —', 'space-core' ); ?></option>
                            <?php foreach ( $colors as $val => $label ) : ?>
                                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $o['admin_color'] ?? '', $val ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e( 'Forces this color scheme for all users.', 'space-core' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Disable Comments', 'space-core' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" id="sc-mc-disable-comments" <?php checked( ! empty( $o['disable_comments'] ) ); ?>>
                            <?php esc_html_e( 'Completely disable comments.', 'space-core' ); ?>
                        </label>
                        <br><br>
                        <p class="description"><?php esc_html_e( 'Limit to specific post types (leave empty = all):', 'space-core' ); ?></p>
                        <?php
                        $disabled_pts = (array) ( $o['disable_comments_post_types'] ?? [] );
                        foreach ( $post_types as $pt ) : ?>
                            <label style="display:inline-block;margin-right:12px;">
                                <input type="checkbox" class="sc-mc-disable-pt" value="<?php echo esc_attr( $pt->name ); ?>"
                                       <?php checked( in_array( $pt->name, $disabled_pts, true ) ); ?>>
                                <?php echo esc_html( $pt->label ); ?>
                            </label>
                        <?php endforeach; ?>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Disable Pingback & Trackback', 'space-core' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" id="sc-mc-disable-pingback" <?php checked( ! empty( $o['disable_pingback'] ) ); ?>>
                            <?php esc_html_e( 'Remove pingback from XML-RPC, strip X-Pingback header, prevent self-pingbacks.', 'space-core' ); ?>
                        </label>
                    </td>
                </tr>
            </table>

            <p>
                <button type="button" id="sc-mc-save" class="button button-primary">
                    <?php esc_html_e( 'Save Configuration', 'space-core' ); ?>
                </button>
                <span id="sc-mc-status" style="margin-left:10px;font-weight:600;"></span>
            </p>
        </div>

        <script>
        jQuery(function($){
            $('#sc-mc-save').on('click', function(){
                var $btn = $(this);
                var pts  = [];
                $('.sc-mc-disable-pt:checked').each(function(){ pts.push($(this).val()); });

                $btn.prop('disabled', true);
                $.post(spaceCore.ajaxUrl, {
                    action: 'sc_save_main_config',
                    nonce:  spaceCore.nonce,
                    data:   JSON.stringify({
                        disable_help:                 $('#sc-mc-disable-help').is(':checked') ? 1 : 0,
                        whitelabel_logo:              $('#sc-mc-whitelabel').is(':checked') ? 1 : 0,
                        site_name:                    $('#sc-mc-site-name').val(),
                        remove_version:               $('#sc-mc-remove-version').is(':checked') ? 1 : 0,
                        footer_text:                  $('#sc-mc-footer-text').val(),
                        admin_color:                  $('#sc-mc-admin-color').val(),
                        disable_comments:             $('#sc-mc-disable-comments').is(':checked') ? 1 : 0,
                        disable_comments_post_types:  pts,
                        disable_pingback:             $('#sc-mc-disable-pingback').is(':checked') ? 1 : 0,
                    }),
                }, function(res){
                    $('#sc-mc-status').text(res.success ? '<?php echo esc_js( __( 'Saved!', 'space-core' ) ); ?>' : '<?php echo esc_js( __( 'Error.', 'space-core' ) ); ?>')
                                     .css('color', res.success ? '#2e7d32' : '#c62828');
                    setTimeout(function(){ $('#sc-mc-status').text(''); }, 3000);
                }).always(function(){ $btn.prop('disabled', false); });
            });
        });
        </script>
        <?php
    }
}
