<?php

namespace Space\Core\Modules\AdminBar;

defined( 'ABSPATH' ) || exit;

use Space\Core\Abstracts\AbstractModule;

class Module extends AbstractModule {

	private array $opts = [];

	public function get_label(): string {
		return __( 'Admin Bar Manager', 'space-core' );
	}

	public function get_description(): string {
		return __( 'Control WordPress admin bar visibility — hide it by role or remove specific toolbar nodes.', 'space-core' );
	}

	public function boot(): void {
		$this->opts = $this->get_options();
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_filter( 'show_admin_bar', [ $this, 'filter_show_admin_bar' ] );
		add_action( 'wp_before_admin_bar_render', [ $this, 'capture_nodes' ], 0 );
		add_action( 'wp_before_admin_bar_render', [ $this, 'remove_nodes' ], 999 );
	}

	private function get_options(): array {
		$defaults = [
			'hide_for_roles' => [],
			'disabled_nodes' => [],
		];
		$saved = get_option( 'space_core_admin_bar', [] );

		return array_merge( $defaults, is_array( $saved ) ? $saved : [] );
	}

	public function register_settings(): void {
		register_setting( 'space_core_admin_bar_group', 'space_core_admin_bar', [
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
			'hide_for_roles' => array_values( array_map( 'sanitize_key', (array) ( $input['hide_for_roles'] ?? [] ) ) ),
			'disabled_nodes' => array_values( array_map( 'sanitize_text_field', (array) ( $input['disabled_nodes'] ?? [] ) ) ),
		];
	}

	public function filter_show_admin_bar( bool $show ): bool {
		if ( ! $show ) {
			return false;
		}

		// Never hide from users who can manage options.
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		$hide_for = $this->opts['hide_for_roles'] ?? [];
		if ( empty( $hide_for ) ) {
			return $show;
		}

		$user = wp_get_current_user();
		if ( ! $user->exists() ) {
			return $show;
		}

		foreach ( $user->roles as $role ) {
			if ( in_array( $role, $hide_for, true ) ) {
				return false;
			}
		}

		return $show;
	}

	public function capture_nodes(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$bar = $GLOBALS['wp_admin_bar'] ?? null;
		if ( ! $bar instanceof \WP_Admin_Bar ) {
			return;
		}

		$raw     = $bar->get_nodes();
		$snapshot = [];

		foreach ( $raw as $node ) {
			$title = is_string( $node->title ) ? wp_strip_all_tags( $node->title ) : '';
			$title = trim( preg_replace( '/\s+/', ' ', $title ) );

			$snapshot[ $node->id ] = [
				'id'     => $node->id,
				'title'  => $title ?: $node->id,
				'parent' => $node->parent ?: false,
			];
		}

		update_option( 'space_core_admin_bar_nodes', $snapshot, false );
	}

	public function remove_nodes(): void {
		$disabled = $this->opts['disabled_nodes'] ?? [];
		if ( empty( $disabled ) ) {
			return;
		}

		$bar = $GLOBALS['wp_admin_bar'] ?? null;
		if ( ! $bar instanceof \WP_Admin_Bar ) {
			return;
		}

		foreach ( $disabled as $node_id ) {
			$bar->remove_node( $node_id );
		}
	}

	public function render_settings(): void {
		$opts  = $this->get_options();
		$nodes = get_option( 'space_core_admin_bar_nodes', [] );
		$roles = wp_roles()->get_names();

		echo $this->view( 'admin/settings', compact( 'opts', 'nodes', 'roles' ) );
	}
}
