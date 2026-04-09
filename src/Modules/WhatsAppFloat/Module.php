<?php

namespace Space\Core\Modules\WhatsAppFloat;

defined( 'ABSPATH' ) || exit;

use Space\Core\Abstracts\AbstractModule;
use Space\Core\Admin\SettingsAPI;

class Module extends AbstractModule {

    private array $opts = [];

    public function get_label(): string {
        return __( 'WhatsApp Float', 'space-core' );
    }

    public function get_description(): string {
        return __( 'Display a configurable floating WhatsApp button with animations and position control.', 'space-core' );
    }

    public function boot(): void {
        $this->opts = $this->get_options();
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'wp_footer', [ $this, 'render_button' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    private function get_options(): array {
        $defaults = [
            'phone'           => '',
            'message'         => '',
            'color'           => '#25D366',
            'load_animation'  => 'bounce',
            'hover_animation' => 'pulse',
            'position_ltr'    => 'right',
            'position_rtl'    => 'left',
            'margin_x'        => 20,
            'margin_y'        => 20,
        ];
        $saved = get_option( 'space_core_whatsapp_float', [] );
        if ( ! is_array( $saved ) ) {
            $saved = [];
        }
        return array_merge( $defaults, $saved );
    }

    public function register_settings(): void {
        register_setting( 'space_core_wa_group', 'space_core_whatsapp_float', [
            'type'              => 'array',
            'sanitize_callback' => [ $this, 'sanitize_options' ],
            'default'           => [],
        ] );
    }

    public function sanitize_options( mixed $input ): array {
        if ( ! is_array( $input ) ) {
            return [];
        }
        return [
            'phone'           => sanitize_text_field( $input['phone'] ?? '' ),
            'message'         => sanitize_text_field( $input['message'] ?? '' ),
            'color'           => sanitize_hex_color( $input['color'] ?? '#25D366' ) ?: '#25D366',
            'load_animation'  => sanitize_key( $input['load_animation'] ?? 'bounce' ),
            'hover_animation' => sanitize_key( $input['hover_animation'] ?? 'pulse' ),
            'position_ltr'    => in_array( $input['position_ltr'] ?? '', [ 'left', 'right' ], true ) ? $input['position_ltr'] : 'right',
            'position_rtl'    => in_array( $input['position_rtl'] ?? '', [ 'left', 'right' ], true ) ? $input['position_rtl'] : 'left',
            'margin_x'        => absint( $input['margin_x'] ?? 20 ),
            'margin_y'        => absint( $input['margin_y'] ?? 20 ),
        ];
    }

    public function enqueue_assets(): void {
        if ( empty( $this->opts['phone'] ) ) {
            return;
        }
        wp_enqueue_style(
            'space-core-front',
            SPACE_CORE_URL . 'assets/css/front.css',
            [],
            SPACE_CORE_VERSION
        );
        wp_enqueue_script(
            'space-core-front',
            SPACE_CORE_URL . 'assets/js/front.js',
            [],
            SPACE_CORE_VERSION,
            true
        );
        wp_localize_script( 'space-core-front', 'spaceCore', [
            'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
            'selectImage' => __( 'Select Image', 'space-core' ),
            'useImage'    => __( 'Use this image', 'space-core' ),
        ] );
    }

    public function render_button(): void {
        $o = $this->opts;
        if ( empty( $o['phone'] ) ) {
            return;
        }

        $phone   = esc_attr( preg_replace( '/[^0-9+]/', '', $o['phone'] ) );
        $message = urlencode( $o['message'] );
        $url     = "https://wa.me/{$phone}" . ( $message ? "?text={$message}" : '' );
        $is_rtl  = is_rtl();

        $position = $is_rtl ? $o['position_rtl'] : $o['position_ltr'];
        $mx       = absint( $o['margin_x'] );
        $my       = absint( $o['margin_y'] );
        $color    = esc_attr( $o['color'] );

        $load_anim  = sanitize_key( $o['load_animation'] );
        $hover_anim = sanitize_key( $o['hover_animation'] );

        // Build inline style.
        $style  = "background-color:{$color};";
        $style .= "bottom:{$my}px;";
        $style .= ( 'left' === $position ) ? "left:{$mx}px;" : "right:{$mx}px;";

        $classes = [ 'sc-wa-float' ];
        if ( $load_anim && 'none' !== $load_anim ) {
            $classes[] = 'sc-load-' . $load_anim;
        }
        if ( $hover_anim && 'none' !== $hover_anim ) {
            $classes[] = 'sc-hover-' . $hover_anim;
        }

        printf(
            '<a href="%s" class="%s" style="%s" target="_blank" rel="noopener noreferrer" aria-label="%s">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" width="28" height="28">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                </svg>
            </a>',
            esc_url( $url ),
            esc_attr( implode( ' ', $classes ) ),
            esc_attr( $style ),
            esc_attr__( 'Chat on WhatsApp', 'space-core' )
        );
    }

    public function render_settings(): void {
        $o = $this->get_options();
        $anim_options = [
            'none'   => __( 'None', 'space-core' ),
            'bounce' => __( 'Bounce', 'space-core' ),
            'fade'   => __( 'Fade In', 'space-core' ),
        ];
        $hover_options = [
            'none'  => __( 'None', 'space-core' ),
            'pulse' => __( 'Pulse', 'space-core' ),
            'shake' => __( 'Shake', 'space-core' ),
        ];
        $position_options = [
            'right' => __( 'Right', 'space-core' ),
            'left'  => __( 'Left', 'space-core' ),
        ];

        SettingsAPI::open_form( 'space_core_wa_group' );
        ?>
        <h2><?php esc_html_e( 'WhatsApp Floating Button', 'space-core' ); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th><?php esc_html_e( 'Phone Number', 'space-core' ); ?></th>
                <td><?php SettingsAPI::text( 'space_core_wa_group', 'space_core_whatsapp_float', 'phone', $o['phone'], '+9665XXXXXXXX' ); ?>
                <p class="description"><?php esc_html_e( 'International format without spaces. E.g. +9665XXXXXXXX', 'space-core' ); ?></p></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Pre-filled Message', 'space-core' ); ?></th>
                <td><?php SettingsAPI::text( 'space_core_wa_group', 'space_core_whatsapp_float', 'message', $o['message'] ); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Button Color', 'space-core' ); ?></th>
                <td><?php SettingsAPI::color( 'space_core_whatsapp_float', 'color', $o['color'] ); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Load Animation', 'space-core' ); ?></th>
                <td><?php SettingsAPI::select( 'space_core_whatsapp_float', 'load_animation', $o['load_animation'], $anim_options ); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Hover Animation', 'space-core' ); ?></th>
                <td><?php SettingsAPI::select( 'space_core_whatsapp_float', 'hover_animation', $o['hover_animation'], $hover_options ); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Position (LTR)', 'space-core' ); ?></th>
                <td><?php SettingsAPI::select( 'space_core_whatsapp_float', 'position_ltr', $o['position_ltr'], $position_options ); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Position (RTL)', 'space-core' ); ?></th>
                <td><?php SettingsAPI::select( 'space_core_whatsapp_float', 'position_rtl', $o['position_rtl'], $position_options ); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Horizontal Margin (px)', 'space-core' ); ?></th>
                <td><?php SettingsAPI::number( 'space_core_whatsapp_float', 'margin_x', $o['margin_x'], 0, 200 ); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Vertical Margin (px)', 'space-core' ); ?></th>
                <td><?php SettingsAPI::number( 'space_core_whatsapp_float', 'margin_y', $o['margin_y'], 0, 200 ); ?></td>
            </tr>
        </table>
        <?php
        SettingsAPI::close_form();
    }
}
