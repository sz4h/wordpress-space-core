<?php

namespace Space\Core\Modules\Translation;

defined( 'ABSPATH' ) || exit;

use Space\Core\Abstracts\AbstractModule;

class Module extends AbstractModule {

	public function boot(): void {
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'wp_ajax_sc_translation_export_postman', [ $this, 'export_postman' ] );
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

		echo $this->view(
			'admin/settings',
			[
				'detected'  => $detected,
				'languages' => $languages,
			]
		);
	}
}
