<?php

namespace Space\Core\Modules\WhatsAppFloat;

defined( 'ABSPATH' ) || exit;

use Space\Core\Abstracts\AbstractModule;

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
		$saved    = get_option( 'space_core_whatsapp_float', [] );
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
			'position_ltr'    => in_array( $input['position_ltr'] ?? '', [
				'left',
				'right'
			], true ) ? $input['position_ltr'] : 'right',
			'position_rtl'    => in_array( $input['position_rtl'] ?? '', [
				'left',
				'right'
			], true ) ? $input['position_rtl'] : 'left',
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
		$style = "background-color:{$color};";
		$style .= "bottom:{$my}px;";
		$style .= ( 'left' === $position ) ? "left:{$mx}px;" : "right:{$mx}px;";

		$classes = [ 'sc-wa-float' ];
		if ( $load_anim && 'none' !== $load_anim ) {
			$classes[] = 'sc-load-' . $load_anim;
		}
		if ( $hover_anim && 'none' !== $hover_anim ) {
			$classes[] = 'sc-hover-' . $hover_anim;
		}

		echo $this->view( 'front/whatsapp', [
			'url'     => esc_url( $url ),
			'classes' => esc_attr( implode( ' ', $classes ) ),
			'style'   => esc_attr( $style ),
			'label'   => esc_attr__( 'Chat on WhatsApp', 'space-core' )
		] );
	}

	public function render_settings(): void {
		$o                = $this->get_options();
		$anim_options     = [
			'none'   => __( 'None', 'space-core' ),
			'bounce' => __( 'Bounce', 'space-core' ),
			'fade'   => __( 'Fade In', 'space-core' ),
		];
		$hover_options    = [
			'none'  => __( 'None', 'space-core' ),
			'pulse' => __( 'Pulse', 'space-core' ),
			'shake' => __( 'Shake', 'space-core' ),
		];
		$position_options = [
			'right' => __( 'Right', 'space-core' ),
			'left'  => __( 'Left', 'space-core' ),
		];

		echo $this->view( 'admin/settings', [
			'options'          => $o,
			'anim_options'     => $anim_options,
			'hover_options'    => $hover_options,
			'position_options' => $position_options,
		] );
	}
}
