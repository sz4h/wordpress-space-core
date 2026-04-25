<?php

namespace Space\Core\Modules\WPMLTranslate;

defined( 'ABSPATH' ) || exit;

use Space\Core\Abstracts\AbstractModule;

class Module extends AbstractModule {

	private Watcher $watcher;

	public function __construct( string $slug ) {
		parent::__construct( $slug );
		$this->watcher = new Watcher();
	}

	public function boot(): void {
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
		add_action( 'wpml_tm_send_post_jobs', [ $this->watcher, 'capture_sent_post_jobs' ], 10, 3 );
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
}
