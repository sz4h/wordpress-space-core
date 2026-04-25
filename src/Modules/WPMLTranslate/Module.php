<?php

namespace Space\Core\Modules\WPMLTranslate;

defined( 'ABSPATH' ) || exit;

use Space\Core\Abstracts\AbstractModule;
use Space\Core\Modules\Translation\PluginDetector;

class Module extends AbstractModule {

	private Watcher $watcher;

	public function __construct( string $slug ) {
		parent::__construct( $slug );
		$this->watcher = new Watcher();
	}

	public function boot(): void {
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
		add_action( 'wpml_tm_send_post_jobs', [ $this->watcher, 'capture_sent_post_jobs' ], 10, 3 );
		add_action( 'wp_ajax_sc_wpml_translate_export_postman', [ $this, 'export_postman' ] );
	}

	public function get_label(): string {
		return __( 'WPMLTranslate', 'space-core' );
	}

	public function get_description(): string {
		return __( 'Read-only WPML job discovery and XLIFF payload extraction for post translation jobs.', 'space-core' );
	}

	public function register_rest_routes(): void {
		$repository = new Repository( $this->watcher );
		$extractor  = new PayloadExtractor();

		( new JobsController( $repository, $extractor ) )->register_routes();
	}

	public function export_postman(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'space-core' ), 403 );
		}

		check_admin_referer( 'sc_wpml_translate_export_postman' );

		$languages = [];
		if ( defined( 'ICL_SITEPRESS_VERSION' ) && 'wpml' === PluginDetector::detect() ) {
			try {
				$languages = PluginDetector::adapter()->get_languages();
			} catch ( \Throwable ) {
				$languages = [];
			}
		}

		$json = ( new PostmanExporter( $languages ) )->json();

		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=space-core-wpml-translate-postman.json' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	public function render_settings(): void {
		$languages = [];

		if ( defined( 'ICL_SITEPRESS_VERSION' ) && 'wpml' === PluginDetector::detect() ) {
			try {
				$languages = PluginDetector::adapter()->get_languages();
			} catch ( \Throwable ) {
				$languages = [];
			}
		}

		echo $this->view(
			'admin/settings',
			[
				'wpml_active' => defined( 'ICL_SITEPRESS_VERSION' ),
				'languages'   => $languages,
			]
		);
	}
}
