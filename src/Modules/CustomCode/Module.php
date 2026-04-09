<?php

namespace Space\Core\Modules\CustomCode;

defined( 'ABSPATH' ) || exit;

use Space\Core\Abstracts\AbstractModule;
use Space\Core\Admin\SettingsAPI;

class Module extends AbstractModule {

    public function get_label(): string {
        return __( 'Custom Code', 'space-core' );
    }

    public function get_description(): string {
        return __( 'Inject custom CSS and JavaScript into your site frontend or admin.', 'space-core' );
    }

    public function boot(): void {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'wp_head', [ $this, 'output_frontend_css' ], 999 );
        add_action( 'wp_footer', [ $this, 'output_frontend_js' ], 999 );
        add_action( 'admin_head', [ $this, 'output_admin_css' ], 999 );
        add_action( 'admin_footer', [ $this, 'output_admin_js' ], 999 );
    }

    private function get_options(): array {
        $defaults = [
            'css'        => '',
            'js'         => '',
            'css_scope'  => 'frontend',
            'js_scope'   => 'frontend',
        ];
        $saved = get_option( 'space_core_custom_code', [] );
        return is_array( $saved ) ? array_merge( $defaults, $saved ) : $defaults;
    }

    public function register_settings(): void {
        register_setting( 'space_core_code_group', 'space_core_custom_code', [
            'type'              => 'array',
            'sanitize_callback' => [ $this, 'sanitize_options' ],
            'default'           => [],
        ] );
    }

    public function sanitize_options( mixed $input ): array {
        if ( ! is_array( $input ) ) {
            return [];
        }
        // CSS: strip <style> tags if accidentally included; allow raw CSS.
        $css = wp_strip_all_tags( $input['css'] ?? '' );
        // JS: strip <script> tags if accidentally included.
        $js  = wp_strip_all_tags( $input['js'] ?? '' );
        return [
            'css'       => $css,
            'js'        => $js,
            'css_scope' => sanitize_key( $input['css_scope'] ?? 'frontend' ),
            'js_scope'  => sanitize_key( $input['js_scope'] ?? 'frontend' ),
        ];
    }

    public function output_frontend_css(): void {
        $o = $this->get_options();
        if ( empty( $o['css'] ) ) {
            return;
        }
        if ( ! in_array( $o['css_scope'], [ 'frontend', 'both' ], true ) ) {
            return;
        }
        echo '<style id="sc-custom-css">' . wp_strip_all_tags( $o['css'] ) . '</style>' . "\n"; // phpcs:ignore
    }

    public function output_frontend_js(): void {
        $o = $this->get_options();
        if ( empty( $o['js'] ) ) {
            return;
        }
        if ( ! in_array( $o['js_scope'], [ 'frontend', 'both' ], true ) ) {
            return;
        }
        echo '<script id="sc-custom-js">' . $o['js'] . '</script>' . "\n"; // phpcs:ignore
    }

    public function output_admin_css(): void {
        $o = $this->get_options();
        if ( empty( $o['css'] ) ) {
            return;
        }
        if ( ! in_array( $o['css_scope'], [ 'admin', 'both' ], true ) ) {
            return;
        }
        echo '<style id="sc-custom-admin-css">' . wp_strip_all_tags( $o['css'] ) . '</style>' . "\n"; // phpcs:ignore
    }

    public function output_admin_js(): void {
        $o = $this->get_options();
        if ( empty( $o['js'] ) ) {
            return;
        }
        if ( ! in_array( $o['js_scope'], [ 'admin', 'both' ], true ) ) {
            return;
        }
        echo '<script id="sc-custom-admin-js">' . $o['js'] . '</script>' . "\n"; // phpcs:ignore
    }

    public function render_settings(): void {
        $o = $this->get_options();
        $scope_options = [
            'frontend' => __( 'Frontend only', 'space-core' ),
            'admin'    => __( 'Admin only', 'space-core' ),
            'both'     => __( 'Both', 'space-core' ),
        ];

        SettingsAPI::open_form( 'space_core_code_group' );
        ?>
        <h2><?php esc_html_e( 'Custom CSS', 'space-core' ); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th><?php esc_html_e( 'Scope', 'space-core' ); ?></th>
                <td><?php SettingsAPI::select( 'space_core_custom_code', 'css_scope', $o['css_scope'], $scope_options ); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'CSS Code', 'space-core' ); ?></th>
                <td><?php SettingsAPI::textarea( 'space_core_custom_code', 'css', $o['css'], 12 ); ?>
                <p class="description"><?php esc_html_e( 'Enter raw CSS without style tags.', 'space-core' ); ?></p></td>
            </tr>
        </table>

        <h2><?php esc_html_e( 'Custom JavaScript', 'space-core' ); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th><?php esc_html_e( 'Scope', 'space-core' ); ?></th>
                <td><?php SettingsAPI::select( 'space_core_custom_code', 'js_scope', $o['js_scope'], $scope_options ); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'JS Code', 'space-core' ); ?></th>
                <td><?php SettingsAPI::textarea( 'space_core_custom_code', 'js', $o['js'], 12 ); ?>
                <p class="description"><?php esc_html_e( 'Enter raw JavaScript without script tags.', 'space-core' ); ?></p></td>
            </tr>
        </table>
        <?php
        SettingsAPI::close_form();
    }
}
