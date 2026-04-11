<?php

namespace Space\Core\Modules\OrderStatuses;

defined( 'ABSPATH' ) || exit;

use Space\Core\Abstracts\AbstractModule;

/**
 * Custom WooCommerce Order Statuses.
 *
 * Stores statuses in option `space_core_order_statuses` as:
 * [ [ 'slug' => 'wc-packed', 'label' => 'Packed', 'color' => '#f90' ], ... ]
 */
class Module extends AbstractModule {

	public function get_label(): string {
		return __( 'Order Statuses', 'space-core' );
	}

	public function get_description(): string {
		return __( 'Create custom WooCommerce order statuses with labels and colors.', 'space-core' );
	}

	public function boot(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		add_action( 'init', [ $this, 'register_statuses' ] );
		add_filter( 'wc_order_statuses', [ $this, 'add_to_wc_statuses' ] );
		add_filter( 'woocommerce_reports_order_statuses', [ $this, 'add_to_reports' ] );
		add_action( 'admin_head', [ $this, 'inject_status_colors' ] );
		add_action( 'wp_ajax_sc_save_order_statuses', [ $this, 'ajax_save' ] );
		add_action( 'wp_ajax_sc_delete_order_status', [ $this, 'ajax_delete' ] );
	}

	public function register_statuses(): void {
		foreach ( $this->get_statuses() as $s ) {
			$slug = sanitize_key( $s['slug'] ?? '' );
			if ( ! $slug ) {
				continue;
			}
			if ( ! str_starts_with( $slug, 'wc-' ) ) {
				$slug = 'wc-' . $slug;
			}
			$label = $this->resolve_label( $s['label'] ?? $slug );
			register_post_status( $slug, [
				'label'                     => $label,
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: count */
				'label_count'               => _n_noop( $label . ' <span class="count">(%s)</span>', $label . ' <span class="count">(%s)</span>', 'space-core' ),
			] );
		}
	}

	private function get_statuses(): array {
		$s = get_option( 'space_core_order_statuses', [] );

		return is_array( $s ) ? $s : [];
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

	public function add_to_wc_statuses( array $statuses ): array {
		foreach ( $this->get_statuses() as $s ) {
			$slug = sanitize_key( $s['slug'] ?? '' );
			if ( ! $slug ) {
				continue;
			}
			if ( ! str_starts_with( $slug, 'wc-' ) ) {
				$slug = 'wc-' . $slug;
			}
			$statuses[ $slug ] = $this->resolve_label( $s['label'] ?? $slug );
		}

		return $statuses;
	}

	public function add_to_reports( array $statuses ): array {
		foreach ( $this->get_statuses() as $s ) {
			$slug = sanitize_key( $s['slug'] ?? '' );
			if ( ! $slug ) {
				continue;
			}
			if ( ! str_starts_with( $slug, 'wc-' ) ) {
				$slug = 'wc-' . $slug;
			}
			$statuses[] = $slug;
		}

		return array_unique( $statuses );
	}

	public function inject_status_colors(): void {
		$statuses = $this->get_statuses();
		if ( empty( $statuses ) ) {
			return;
		}
		echo '<style>';
		foreach ( $statuses as $s ) {
			$slug  = sanitize_key( $s['slug'] ?? '' );
			$color = sanitize_hex_color( $s['color'] ?? '#888' ) ?? '#888';
			if ( ! $slug ) {
				continue;
			}
			if ( ! str_starts_with( $slug, 'wc-' ) ) {
				$slug = 'wc-' . $slug;
			}
			// Style the status badge in order list.
			echo '.order-status.status-' . esc_attr( ltrim( $slug, 'wc-' ) ) . '{background:' . esc_attr( $color ) . '20;color:' . esc_attr( $color ) . ';border-color:' . esc_attr( $color ) . ';}';
		}
		echo '</style>';
	}

	public function ajax_save(): void {
		check_ajax_referer( 'space_core_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [], 403 );
		}

		$raw      = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : '{}'; // phpcs:ignore
		$statuses = json_decode( $raw, true );
		if ( ! is_array( $statuses ) ) {
			wp_send_json_error();
		}

		$clean = [];
		foreach ( $statuses as $s ) {
			$slug = sanitize_key( $s['slug'] ?? '' );
			if ( ! $slug ) {
				continue;
			}
			$label_raw = $s['label'] ?? [];
			if ( is_array( $label_raw ) ) {
				$label = [
					'en' => sanitize_text_field( $label_raw['en'] ?? '' ),
					'ar' => sanitize_text_field( $label_raw['ar'] ?? '' ),
				];
			} else {
				$label = [
					'en' => sanitize_text_field( (string) $label_raw ),
					'ar' => '',
				];
			}
			$clean[] = [
				'slug'  => $slug,
				'label' => $label,
				'color' => sanitize_hex_color( $s['color'] ?? '' ) ?? '#888888',
			];
		}

		update_option( 'space_core_order_statuses', $clean );
		wp_send_json_success( [ 'message' => __( 'Saved!', 'space-core' ) ] );
	}

	public function ajax_delete(): void {
		check_ajax_referer( 'space_core_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [], 403 );
		}
		$slug     = sanitize_key( wp_unslash( $_POST['slug'] ?? '' ) );
		$statuses = $this->get_statuses();
		$statuses = array_filter( $statuses, fn( $s ) => ( $s['slug'] ?? '' ) !== $slug );
		update_option( 'space_core_order_statuses', array_values( $statuses ) );
		wp_send_json_success();
	}

	public function render_settings(): void {
		$statuses = $this->get_statuses();
		$nonce    = wp_create_nonce( 'space_core_admin' );
		echo $this->view( 'admin/settings', [
			'statuses' => $statuses,
			'nonce'    => $nonce,
		] );
	}
}
