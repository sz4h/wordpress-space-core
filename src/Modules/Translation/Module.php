<?php

namespace Space\Core\Modules\Translation;

defined( 'ABSPATH' ) || exit;

use Space\Core\Abstracts\AbstractModule;

class Module extends AbstractModule {

	public function boot(): void {
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'wp_ajax_sc_translation_export_postman', [ $this, 'export_postman' ] );
		add_action( 'wp_ajax_sc_translation_save_config', [ $this, 'ajax_save_config' ] );
		add_action( 'wp_ajax_sc_translation_sample_keys', [ $this, 'ajax_sample_keys' ] );
	}

	public function get_label(): string {
		return __( 'Translation', 'space-core' );
	}

	public function get_description(): string {
		return __( 'REST API endpoints for bulk fetching and filling missing translations (Polylang / WPML).', 'space-core' );
	}

	public function register_rest_routes(): void {
		( new TermsController() )->register_routes();
		( new MenusController() )->register_routes();
		( new PostsController() )->register_routes();
	}

	public function register_settings(): void {
		register_setting(
			'space_core_translation_group',
			'space_core_translation',
			[ 'sanitize_callback' => '__return_empty_array' ]
		);
	}

	public function export_postman(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'space-core' ), 403 );
		}

		check_admin_referer( 'sc_translation_export_postman' );

		$languages = [];
		if ( 'none' !== PluginDetector::detect() ) {
			try {
				$languages = PluginDetector::adapter()->get_languages();
			} catch ( \Throwable ) {
				$languages = [];
			}
		}

		$json = ( new PostmanExporter( $languages ) )->json();

		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="space-core-translation.postman_collection.json"' );
		header( 'Content-Length: ' . strlen( $json ) );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $json;
		exit;
	}

	public function ajax_save_config(): void {
		check_ajax_referer( 'sc_translation_config' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Not allowed.', 'space-core' ) ], 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing — nonce already checked above
		$raw_type = isset( $_POST['config_type'] ) ? sanitize_key( $_POST['config_type'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$raw_slug = isset( $_POST['slug'] ) ? sanitize_key( $_POST['slug'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$raw_keys = isset( $_POST['keys'] ) && is_array( $_POST['keys'] ) ? $_POST['keys'] : [];

		if ( ! in_array( $raw_type, [ 'post_types', 'taxonomies' ], true ) || '' === $raw_slug ) {
			wp_send_json_error( [ 'message' => __( 'Invalid request.', 'space-core' ) ], 400 );
		}

		$keys = array_values( array_filter( array_map( 'sanitize_key', $raw_keys ) ) );
		$opts = get_option( 'space_core_translation', [] );

		if ( empty( $keys ) ) {
			unset( $opts[ $raw_type ][ $raw_slug ] );
		} else {
			$opts[ $raw_type ][ $raw_slug ] = [ 'keys' => $keys ];
		}

		update_option( 'space_core_translation', $opts );

		wp_send_json_success( [ 'message' => __( 'Saved.', 'space-core' ) ] );
	}

	public function ajax_sample_keys(): void {
		check_ajax_referer( 'sc_translation_config' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Not allowed.', 'space-core' ) ], 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$config_type = isset( $_POST['config_type'] ) ? sanitize_key( $_POST['config_type'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$slug        = isset( $_POST['slug'] ) ? sanitize_key( $_POST['slug'] ) : '';

		if ( 'post_types' === $config_type ) {
			$post = get_posts( [ 'post_type' => $slug, 'posts_per_page' => 1, 'post_status' => 'any' ] );
			if ( empty( $post ) ) {
				wp_send_json_success( [ 'keys' => [] ] );
			}
			$keys = array_keys( MetaFilter::filter( $post[0]->ID, $slug ) );
			wp_send_json_success( [ 'keys' => $keys ] );
		}

		if ( 'taxonomies' === $config_type ) {
			$terms = get_terms( [ 'taxonomy' => $slug, 'number' => 1, 'hide_empty' => false ] );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				wp_send_json_success( [ 'keys' => [] ] );
			}
			$keys = array_keys( get_term_meta( $terms[0]->term_id ) );
			wp_send_json_success( [ 'keys' => $keys ] );
		}

		wp_send_json_error( [ 'message' => __( 'Invalid config type.', 'space-core' ) ], 400 );
	}

	public function render_settings(): void {
		$detected  = PluginDetector::detect();
		$languages = [];

		if ( 'none' !== $detected ) {
			try {
				$languages = PluginDetector::adapter()->get_languages();
			} catch ( \Throwable ) {
				$languages = [];
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$active_tab = isset( $_GET['sc_trans_tab'] ) ? sanitize_key( $_GET['sc_trans_tab'] ) : 'config';

		$post_types = array_filter(
			get_post_types( [ 'public' => true ], 'objects' ),
			static fn( $pt ) => 'attachment' !== $pt->name
		);

		$taxonomies = get_taxonomies(
			[ 'public' => true ],
			'objects'
		);

		$config = get_option( 'space_core_translation', [] );
		$nonce  = wp_create_nonce( 'sc_translation_config' );

		echo $this->view(
			'admin/settings',
			[
				'detected'   => $detected,
				'languages'  => $languages,
				'active_tab' => $active_tab,
				'post_types' => $post_types,
				'taxonomies' => $taxonomies,
				'config'     => $config,
				'nonce'      => $nonce,
			]
		);
	}
}
