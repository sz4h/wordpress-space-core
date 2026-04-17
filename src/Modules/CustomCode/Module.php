<?php

namespace Space\Core\Modules\CustomCode;

defined( 'ABSPATH' ) || exit;

use Space\Core\Abstracts\AbstractModule;

class Module extends AbstractModule {

    public function get_label(): string {
        return __( 'Custom Code', 'space-core' );
    }

    public function get_description(): string {
        return __( 'Inject custom code into your site header or footer on the frontend or in wp-admin.', 'space-core' );
    }

    public function boot(): void {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'wp_head', [ $this, 'output_frontend_header' ], 999 );
        add_action( 'wp_footer', [ $this, 'output_frontend_footer' ], 999 );
        add_action( 'admin_head', [ $this, 'output_admin_header' ], 999 );
        add_action( 'admin_footer', [ $this, 'output_admin_footer' ], 999 );
    }

    private function get_options(): array {
        $defaults = [
            'header_code'  => '',
            'footer_code'  => '',
            'header_scope' => 'frontend',
            'footer_scope' => 'frontend',
            'css'          => '',
            'js'           => '',
            'css_scope'    => 'frontend',
            'js_scope'     => 'frontend',
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
        $existing = $this->get_options();

        if ( ! is_array( $input ) ) {
            return $existing;
        }

        return [
            'header_code'  => $this->sanitize_raw_code( $input['header_code'] ?? '' ),
            'footer_code'  => $this->sanitize_raw_code( $input['footer_code'] ?? '' ),
            'header_scope' => $this->sanitize_scope( $input['header_scope'] ?? 'frontend' ),
            'footer_scope' => $this->sanitize_scope( $input['footer_scope'] ?? 'frontend' ),
            // Keep legacy values intact so existing CSS/JS snippets continue to work.
            'css'          => (string) $existing['css'],
            'js'           => (string) $existing['js'],
            'css_scope'    => $this->sanitize_scope( $existing['css_scope'] ?? 'frontend' ),
            'js_scope'     => $this->sanitize_scope( $existing['js_scope'] ?? 'frontend' ),
        ];
    }

    private function sanitize_scope( mixed $scope ): string {
        $scope = sanitize_key( (string) $scope );

        return in_array( $scope, [ 'frontend', 'admin', 'both' ], true ) ? $scope : 'frontend';
    }

    private function sanitize_raw_code( mixed $code ): string {
        $code = (string) $code;
        $code = wp_kses_no_null( $code );

        return str_replace( [ "\r\n", "\r" ], "\n", $code );
    }

    private function should_output_scope( string $scope, string $context ): bool {
        return 'both' === $scope || $context === $scope;
    }

    private function output_raw_code( string $code ): void {
        if ( '' === trim( $code ) ) {
            return;
        }

        echo $code . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    private function output_legacy_css( string $context ): void {
        $o = $this->get_options();
        if ( empty( $o['css'] ) ) {
            return;
        }
        if ( ! $this->should_output_scope( (string) $o['css_scope'], $context ) ) {
            return;
        }

        $id = 'frontend' === $context ? 'sc-custom-css' : 'sc-custom-admin-css';
        echo '<style id="' . esc_attr( $id ) . '">' . wp_strip_all_tags( (string) $o['css'] ) . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    private function output_legacy_js( string $context ): void {
        $o = $this->get_options();
        if ( empty( $o['js'] ) ) {
            return;
        }
        if ( ! $this->should_output_scope( (string) $o['js_scope'], $context ) ) {
            return;
        }

        $id = 'frontend' === $context ? 'sc-custom-js' : 'sc-custom-admin-js';
        echo '<script id="' . esc_attr( $id ) . '">' . (string) $o['js'] . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function output_frontend_header(): void {
        $o = $this->get_options();

        if ( $this->should_output_scope( (string) $o['header_scope'], 'frontend' ) ) {
            $this->output_raw_code( (string) $o['header_code'] );
        }

        $this->output_legacy_css( 'frontend' );
    }

    public function output_frontend_footer(): void {
        $o = $this->get_options();

        if ( $this->should_output_scope( (string) $o['footer_scope'], 'frontend' ) ) {
            $this->output_raw_code( (string) $o['footer_code'] );
        }

        $this->output_legacy_js( 'frontend' );
    }

    public function output_admin_header(): void {
        $o = $this->get_options();

        if ( $this->should_output_scope( (string) $o['header_scope'], 'admin' ) ) {
            $this->output_raw_code( (string) $o['header_code'] );
        }

        $this->output_legacy_css( 'admin' );
    }

    public function output_admin_footer(): void {
        $o = $this->get_options();

        if ( $this->should_output_scope( (string) $o['footer_scope'], 'admin' ) ) {
            $this->output_raw_code( (string) $o['footer_code'] );
        }

        $this->output_legacy_js( 'admin' );
    }

    public function render_settings(): void {
        $o = $this->get_options();
        $scope_options = [
            'frontend' => __( 'Frontend only', 'space-core' ),
            'admin'    => __( 'Admin only', 'space-core' ),
            'both'     => __( 'Both', 'space-core' ),
        ];

        echo $this->view( 'admin/settings', [
            'options'       => $o,
            'scope_options' => $scope_options,
        ] );
    }
}
