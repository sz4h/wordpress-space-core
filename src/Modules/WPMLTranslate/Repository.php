<?php

namespace Space\Core\Modules\WPMLTranslate;

defined( 'ABSPATH' ) || exit;

use WP_Error;

class Repository {

	private ?array $availabilityCache = null;

	/** @var array<int, array<string, mixed>> */
	private array $jobCache = [];

	public function __construct( private readonly Watcher $watcher ) {
	}

	public function is_available(): bool {
		return (bool) $this->get_availability()['available'];
	}

	public function get_unavailable_error(): WP_Error {
		$availability = $this->get_availability();

		return new WP_Error(
			'wpml_translate_unavailable',
			__( 'WPMLTranslate is unavailable in the current environment.', 'space-core' ),
			[
				'status'      => 503,
				'module'      => 'WPMLTranslate',
				'reason_code' => $availability['reason_code'],
				'details'     => $availability['details'],
			]
		);
	}

	public function get_jobs( array $filters ): array {
		global $wpdb;

		$where  = [ "translations.element_type LIKE 'post_%'" ];
		$params = [];

		$statuses = $this->normalize_statuses( $filters['statuses'] ?? null );
		if ( $statuses ) {
			$placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%d' ) );
			$where[]      = "translation_status.status IN ({$placeholders})";
			array_push( $params, ...$statuses );
		}

		if ( ! empty( $filters['post_type'] ) ) {
			$where[]  = "REPLACE(translations.element_type, 'post_', '') = %s";
			$params[] = sanitize_key( (string) $filters['post_type'] );
		}

		if ( ! empty( $filters['source_lang'] ) ) {
			$where[]  = 'source_translation.language_code = %s';
			$params[] = sanitize_text_field( (string) $filters['source_lang'] );
		}

		if ( ! empty( $filters['target_lang'] ) ) {
			$where[]  = 'translations.language_code = %s';
			$params[] = sanitize_text_field( (string) $filters['target_lang'] );
		}

		$limit = isset( $filters['limit'] ) ? (int) $filters['limit'] : 50;
		$limit = max( 1, min( 200, $limit ) );

		$sql = "
			SELECT latest_job.job_id,
			       latest_job.rid,
			       latest_job.translator_id,
			       latest_job.translated,
			       latest_job.manager_id,
			       latest_job.title,
			       latest_job.deadline_date,
			       latest_job.completed_date,
			       latest_job.editor,
			       latest_job.editor_job_id,
			       latest_job.edit_timestamp,
			       latest_job.automatic,
			       translation_status.translation_id,
			       translation_status.status,
			       translation_status.needs_update,
			       translation_status.review_status,
			       translation_status.translation_service,
			       translation_status.batch_id,
			       translation_status.timestamp,
			       translation_status.tp_id,
			       translation_status.ts_status,
			       translations.trid,
			       translations.element_type,
			       translations.element_id AS translated_post_id,
			       translations.language_code AS target_language,
			       source_translation.element_id AS source_post_id,
			       source_translation.language_code AS source_language,
			       REPLACE(translations.element_type, 'post_', '') AS post_type,
			       batches.batch_name,
			       batches.tp_id AS batch_tp_id,
			       batches.last_update AS batch_last_update
			FROM {$wpdb->prefix}icl_translation_status translation_status
			INNER JOIN {$wpdb->prefix}icl_translations translations
				ON translations.translation_id = translation_status.translation_id
			LEFT JOIN {$wpdb->prefix}icl_translations source_translation
				ON source_translation.trid = translations.trid
				AND source_translation.source_language_code IS NULL
			LEFT JOIN (
				SELECT translate_job.*
				FROM {$wpdb->prefix}icl_translate_job translate_job
				INNER JOIN (
					SELECT MAX(job_id) AS job_id
					FROM {$wpdb->prefix}icl_translate_job
					GROUP BY rid
				) latest_jobs ON latest_jobs.job_id = translate_job.job_id
			) latest_job ON latest_job.rid = translation_status.rid
			LEFT JOIN {$wpdb->prefix}icl_translation_batches batches
				ON batches.id = translation_status.batch_id
			WHERE " . implode( ' AND ', $where ) . '
			ORDER BY COALESCE(latest_job.job_id, 0) DESC, translation_status.timestamp DESC
			LIMIT %d
		';

		$params[] = $limit;
		$prepared = $wpdb->prepare( $sql, $params );
		$rows     = $wpdb->get_results( $prepared, ARRAY_A );

		if ( ! is_array( $rows ) ) {
			return [];
		}

		$jobs = array_map( [ $this, 'normalize_job_row' ], $rows );
		foreach ( $jobs as $job ) {
			$this->jobCache[ (int) $job['job_id'] ] = $job;
		}

		return array_values( $jobs );
	}

	public function find_job( int $jobId ): ?array {
		if ( isset( $this->jobCache[ $jobId ] ) ) {
			return $this->jobCache[ $jobId ];
		}

		global $wpdb;

		$sql = "
			SELECT latest_job.job_id,
			       latest_job.rid,
			       latest_job.translator_id,
			       latest_job.translated,
			       latest_job.manager_id,
			       latest_job.title,
			       latest_job.deadline_date,
			       latest_job.completed_date,
			       latest_job.editor,
			       latest_job.editor_job_id,
			       latest_job.edit_timestamp,
			       latest_job.automatic,
			       translation_status.translation_id,
			       translation_status.status,
			       translation_status.needs_update,
			       translation_status.review_status,
			       translation_status.translation_service,
			       translation_status.batch_id,
			       translation_status.timestamp,
			       translation_status.tp_id,
			       translation_status.ts_status,
			       translations.trid,
			       translations.element_type,
			       translations.element_id AS translated_post_id,
			       translations.language_code AS target_language,
			       source_translation.element_id AS source_post_id,
			       source_translation.language_code AS source_language,
			       REPLACE(translations.element_type, 'post_', '') AS post_type,
			       batches.batch_name,
			       batches.tp_id AS batch_tp_id,
			       batches.last_update AS batch_last_update
			FROM {$wpdb->prefix}icl_translation_status translation_status
			INNER JOIN {$wpdb->prefix}icl_translations translations
				ON translations.translation_id = translation_status.translation_id
			LEFT JOIN {$wpdb->prefix}icl_translations source_translation
				ON source_translation.trid = translations.trid
				AND source_translation.source_language_code IS NULL
			LEFT JOIN (
				SELECT translate_job.*
				FROM {$wpdb->prefix}icl_translate_job translate_job
				INNER JOIN (
					SELECT MAX(job_id) AS job_id
					FROM {$wpdb->prefix}icl_translate_job
					GROUP BY rid
				) latest_jobs ON latest_jobs.job_id = translate_job.job_id
			) latest_job ON latest_job.rid = translation_status.rid
			LEFT JOIN {$wpdb->prefix}icl_translation_batches batches
				ON batches.id = translation_status.batch_id
			WHERE latest_job.job_id = %d
				AND translations.element_type LIKE 'post_%'
			LIMIT 1
		";

		$row = $wpdb->get_row( $wpdb->prepare( $sql, $jobId ), ARRAY_A );
		if ( ! is_array( $row ) ) {
			return null;
		}

		$job                     = $this->normalize_job_row( $row );
		$this->jobCache[ $jobId ] = $job;

		return $job;
	}

	public function parse_status_filter( ?string $statusParam ): ?array {
		if ( null === $statusParam || '' === $statusParam ) {
			return array_values(
				array_filter(
					[
						defined( 'ICL_TM_WAITING_FOR_TRANSLATOR' ) ? (int) constant( 'ICL_TM_WAITING_FOR_TRANSLATOR' ) : null,
						defined( 'ICL_TM_IN_PROGRESS' ) ? (int) constant( 'ICL_TM_IN_PROGRESS' ) : null,
						defined( 'ICL_TM_TRANSLATION_READY_TO_DOWNLOAD' ) ? (int) constant( 'ICL_TM_TRANSLATION_READY_TO_DOWNLOAD' ) : null,
					],
					static fn( mixed $status ): bool => null !== $status
				)
			);
		}

		$values   = array_filter( array_map( 'trim', explode( ',', $statusParam ) ) );
		$statuses = [];

		foreach ( $values as $value ) {
			$mapped = $this->map_status_to_id( $value );
			if ( null !== $mapped ) {
				$statuses[] = $mapped;
			}
		}

		return $statuses ? array_values( array_unique( $statuses ) ) : null;
	}

	public function get_payload_readiness( array $job ): array {
		if ( 'post' !== ( $job['job_type'] ?? '' ) ) {
			return $this->build_reason( 'unsupported_job_type', __( 'Only post translation jobs are supported in this first pass.', 'space-core' ) );
		}

		if ( empty( $job['source_post_id'] ) ) {
			return $this->build_reason( 'missing_source_post', __( 'The job does not have a resolvable source post.', 'space-core' ) );
		}

		if ( ! $this->is_remote_job( $job ) ) {
			return $this->build_reason( 'local_job', __( 'The job appears to be local-only and is excluded from remote payload extraction.', 'space-core' ) );
		}

		$allowed = array_values(
			array_filter(
				[
					defined( 'ICL_TM_WAITING_FOR_TRANSLATOR' ) ? (int) constant( 'ICL_TM_WAITING_FOR_TRANSLATOR' ) : null,
					defined( 'ICL_TM_IN_PROGRESS' ) ? (int) constant( 'ICL_TM_IN_PROGRESS' ) : null,
					defined( 'ICL_TM_TRANSLATION_READY_TO_DOWNLOAD' ) ? (int) constant( 'ICL_TM_TRANSLATION_READY_TO_DOWNLOAD' ) : null,
					defined( 'ICL_TM_COMPLETE' ) ? (int) constant( 'ICL_TM_COMPLETE' ) : null,
				],
				static fn( mixed $status ): bool => null !== $status
			)
		);

		if ( $allowed && ! in_array( (int) ( $job['status_id'] ?? 0 ), $allowed, true ) ) {
			return $this->build_reason( 'unsupported_status', __( 'The job is not in a state that can be exported safely.', 'space-core' ) );
		}

		return [
			'available'      => true,
			'reason_code'    => null,
			'reason_message' => null,
		];
	}

	public function is_remote_job( array $job ): bool {
		$serviceMarker = (string) ( $job['translation_service_marker'] ?? '' );

		return ! in_array( strtolower( $serviceMarker ), [ '', '0', 'local' ], true )
			|| ! empty( $job['tp_id'] )
			|| ! empty( $job['batch_tp_id'] )
			|| 'ate' === strtolower( (string) ( $job['editor'] ?? '' ) )
			|| ! empty( $job['automatic'] );
	}

	private function get_availability(): array {
		global $wpdb;

		if ( null !== $this->availabilityCache ) {
			return $this->availabilityCache;
		}

		if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
			$this->availabilityCache = [
				'available'   => false,
				'reason_code' => 'wpml_inactive',
				'details'     => [],
			];

			return $this->availabilityCache;
		}

		$tables = [
			$wpdb->prefix . 'icl_translation_status',
			$wpdb->prefix . 'icl_translate_job',
			$wpdb->prefix . 'icl_translation_batches',
			$wpdb->prefix . 'icl_translations',
		];

		foreach ( $tables as $table ) {
			$foundTable = (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( 0 !== strcasecmp( $foundTable, $table ) ) {
				$this->availabilityCache = [
					'available'   => false,
					'reason_code' => 'missing_wpml_tables',
					'details'     => [ 'missing_table' => $table ],
				];

				return $this->availabilityCache;
			}
		}

		$this->availabilityCache = [
			'available'   => true,
			'reason_code' => null,
			'details'     => [],
		];

		return $this->availabilityCache;
	}

	private function normalize_statuses( ?array $statuses ): ?array {
		if ( null === $statuses ) {
			return null;
		}

		$normalized = [];
		foreach ( $statuses as $status ) {
			$mapped = $this->map_status_to_id( $status );
			if ( null !== $mapped ) {
				$normalized[] = $mapped;
			}
		}

		return $normalized ? array_values( array_unique( $normalized ) ) : null;
	}

	private function map_status_to_id( mixed $status ): ?int {
		if ( is_numeric( $status ) ) {
			return (int) $status;
		}

		$key = sanitize_key( (string) $status );

		return match ( $key ) {
			'not_translated'                  => defined( 'ICL_TM_NOT_TRANSLATED' ) ? (int) constant( 'ICL_TM_NOT_TRANSLATED' ) : null,
			'waiting', 'waiting_for_translator' => defined( 'ICL_TM_WAITING_FOR_TRANSLATOR' ) ? (int) constant( 'ICL_TM_WAITING_FOR_TRANSLATOR' ) : null,
			'in_progress'                     => defined( 'ICL_TM_IN_PROGRESS' ) ? (int) constant( 'ICL_TM_IN_PROGRESS' ) : null,
			'translation_ready_to_download', 'ready' => defined( 'ICL_TM_TRANSLATION_READY_TO_DOWNLOAD' ) ? (int) constant( 'ICL_TM_TRANSLATION_READY_TO_DOWNLOAD' ) : null,
			'complete'                        => defined( 'ICL_TM_COMPLETE' ) ? (int) constant( 'ICL_TM_COMPLETE' ) : null,
			default                           => null,
		};
	}

	private function normalize_job_row( array $row ): array {
		$job = [
			'job_id'                     => isset( $row['job_id'] ) ? (int) $row['job_id'] : 0,
			'rid'                        => isset( $row['rid'] ) ? (int) $row['rid'] : 0,
			'translation_id'             => isset( $row['translation_id'] ) ? (int) $row['translation_id'] : 0,
			'source_post_id'             => isset( $row['source_post_id'] ) ? (int) $row['source_post_id'] : 0,
			'translated_post_id'         => isset( $row['translated_post_id'] ) ? (int) $row['translated_post_id'] : 0,
			'job_type'                   => isset( $row['element_type'] ) && str_starts_with( (string) $row['element_type'], 'post_' ) ? 'post' : 'unsupported',
			'post_type'                  => (string) ( $row['post_type'] ?? '' ),
			'source_language'            => (string) ( $row['source_language'] ?? '' ),
			'target_language'            => (string) ( $row['target_language'] ?? '' ),
			'batch_id'                   => isset( $row['batch_id'] ) ? (int) $row['batch_id'] : 0,
			'batch_name'                 => (string) ( $row['batch_name'] ?? '' ),
			'batch_tp_id'                => isset( $row['batch_tp_id'] ) ? (int) $row['batch_tp_id'] : 0,
			'batch_last_update'          => (string) ( $row['batch_last_update'] ?? '' ),
			'translation_service_marker' => (string) ( $row['translation_service'] ?? '' ),
			'status_id'                  => isset( $row['status'] ) ? (int) $row['status'] : 0,
			'status'                     => $this->normalize_status_label( isset( $row['status'] ) ? (int) $row['status'] : null ),
			'needs_update'               => ! empty( $row['needs_update'] ),
			'review_status'              => isset( $row['review_status'] ) ? (int) $row['review_status'] : 0,
			'timestamp'                  => (string) ( $row['timestamp'] ?? '' ),
			'updated_at'                 => (string) ( $row['edit_timestamp'] ?? $row['timestamp'] ?? '' ),
			'created_at'                 => (string) ( $row['timestamp'] ?? '' ),
			'tp_id'                      => isset( $row['tp_id'] ) ? (int) $row['tp_id'] : 0,
			'ts_status'                  => isset( $row['ts_status'] ) ? (int) $row['ts_status'] : 0,
			'translator_id'              => isset( $row['translator_id'] ) ? (int) $row['translator_id'] : 0,
			'title'                      => (string) ( $row['title'] ?? '' ),
			'deadline_date'              => (string) ( $row['deadline_date'] ?? '' ),
			'completed_date'             => (string) ( $row['completed_date'] ?? '' ),
			'editor'                     => (string) ( $row['editor'] ?? '' ),
			'editor_job_id'              => isset( $row['editor_job_id'] ) ? (int) $row['editor_job_id'] : 0,
			'automatic'                  => ! empty( $row['automatic'] ),
		];

		$job['sent_in_request'] = $this->watcher->was_sent_in_request(
			$job['source_post_id'],
			$job['source_language'],
			$job['target_language']
		);

		return $job;
	}

	private function normalize_status_label( ?int $statusId ): string {
		$map = [];

		if ( defined( 'ICL_TM_NOT_TRANSLATED' ) ) {
			$map[ (int) constant( 'ICL_TM_NOT_TRANSLATED' ) ] = 'not_translated';
		}
		if ( defined( 'ICL_TM_WAITING_FOR_TRANSLATOR' ) ) {
			$map[ (int) constant( 'ICL_TM_WAITING_FOR_TRANSLATOR' ) ] = 'waiting_for_translator';
		}
		if ( defined( 'ICL_TM_IN_PROGRESS' ) ) {
			$map[ (int) constant( 'ICL_TM_IN_PROGRESS' ) ] = 'in_progress';
		}
		if ( defined( 'ICL_TM_TRANSLATION_READY_TO_DOWNLOAD' ) ) {
			$map[ (int) constant( 'ICL_TM_TRANSLATION_READY_TO_DOWNLOAD' ) ] = 'translation_ready_to_download';
		}
		if ( defined( 'ICL_TM_COMPLETE' ) ) {
			$map[ (int) constant( 'ICL_TM_COMPLETE' ) ] = 'complete';
		}

		return $map[ $statusId ] ?? 'unknown';
	}

	private function build_reason( string $reasonCode, string $message ): array {
		return [
			'available'      => false,
			'reason_code'    => $reasonCode,
			'reason_message' => $message,
		];
	}
}
