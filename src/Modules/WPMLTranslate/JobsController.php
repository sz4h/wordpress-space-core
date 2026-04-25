<?php

namespace Space\Core\Modules\WPMLTranslate;

defined( 'ABSPATH' ) || exit;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class JobsController {

	private const NS = 'space-core/v1';

	public function __construct(
		private readonly Repository $repository,
		private readonly PayloadExtractor $payloadExtractor
	) {
	}

	public function register_routes(): void {
		register_rest_route(
			self::NS,
			'/wpml-translate/jobs',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_jobs' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args'                => [
						'status'      => [
							'sanitize_callback' => 'sanitize_text_field',
						],
						'post_type'   => [
							'sanitize_callback' => 'sanitize_key',
						],
						'source_lang' => [
							'sanitize_callback' => 'sanitize_text_field',
						],
						'target_lang' => [
							'sanitize_callback' => 'sanitize_text_field',
						],
						'limit'       => [
							'sanitize_callback' => 'absint',
						],
					],
				],
			]
		);

		register_rest_route(
			self::NS,
			'/wpml-translate/jobs/(?P<job_id>\d+)/payload',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_payload' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args'                => [
						'job_id' => [
							'required'          => true,
							'sanitize_callback' => 'absint',
						],
					],
				],
			]
		);
	}

	public function check_permission(): bool|WP_Error {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to manage WPMLTranslate jobs.', 'space-core' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return true;
	}

	public function get_jobs( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! $this->repository->is_available() ) {
			return $this->repository->get_unavailable_error();
		}

		$jobs = $this->repository->get_jobs(
			[
				'statuses'    => $this->repository->parse_status_filter( $request->get_param( 'status' ) ),
				'post_type'   => $request->get_param( 'post_type' ),
				'source_lang' => $request->get_param( 'source_lang' ),
				'target_lang' => $request->get_param( 'target_lang' ),
				'limit'       => $request->get_param( 'limit' ),
			]
		);

		return new WP_REST_Response(
			[
				'module'    => 'WPMLTranslate',
				'available' => true,
				'count'     => count( $jobs ),
				'jobs'      => $jobs,
			],
			200
		);
	}

	public function get_payload( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! $this->repository->is_available() ) {
			return $this->repository->get_unavailable_error();
		}

		$result = $this->payloadExtractor->extract( (int) $request['job_id'], $this->repository );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 200 );
	}
}
