<?php

namespace Space\Core\Modules\Translation;

defined( 'ABSPATH' ) || exit;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * GET /space-core/v1/translation/schema
 *
 * Returns all public post types and taxonomies registered on the site,
 * with their currently configured translatable meta keys (from the
 * space_core_translation option) and an auto-detected sample from the DB.
 */
class SchemaController {

	private const NS = 'space-core/v1';

	public function register_routes(): void {
		register_rest_route(
			self::NS,
			'/translation/schema',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_schema' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	public function check_permission(): bool|WP_Error {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access translation schema.', 'space-core' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return true;
	}

	public function get_schema( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response(
			[
				'post_types' => $this->build_post_types(),
				'taxonomies' => $this->build_taxonomies(),
			],
			200
		);
	}

	// -------------------------------------------------------------------------

	private function build_post_types(): array {
		$post_types = get_post_types( [ 'public' => true ], 'objects' );
		$result     = [];

		foreach ( $post_types as $pt ) {
			if ( 'attachment' === $pt->name ) {
				continue;
			}

			$configured = MetaFilter::configured_post_keys( $pt->name );

			$result[] = [
				'name'            => $pt->name,
				'label'           => $pt->label,
				'taxonomies'      => get_object_taxonomies( $pt->name ),
				'configured_keys' => $configured ?? [],
				'sample_keys'     => $this->sample_post_keys( $pt->name, $configured ),
			];
		}

		return $result;
	}

	private function build_taxonomies(): array {
		$taxonomies = get_taxonomies( [ 'public' => true ], 'objects' );
		$result     = [];

		foreach ( $taxonomies as $tax ) {
			$configured = MetaFilter::configured_taxonomy_keys( $tax->name );

			$result[] = [
				'name'            => $tax->name,
				'label'           => $tax->label,
				'post_types'      => $tax->object_type,
				'configured_keys' => $configured ?? [],
				'sample_keys'     => $this->sample_term_keys( $tax->name ),
			];
		}

		return $result;
	}

	/**
	 * Auto-detect translatable keys from the latest post of this type.
	 * If configured keys are set, we skip auto-detection (they are already known).
	 */
	private function sample_post_keys( string $post_type, ?array $configured ): array {
		if ( null !== $configured ) {
			return [];
		}

		$posts = get_posts( [ 'post_type' => $post_type, 'posts_per_page' => 1, 'post_status' => 'any' ] );
		if ( empty( $posts ) ) {
			return [];
		}

		return array_keys( MetaFilter::filter( $posts[0]->ID, $post_type ) );
	}

	/**
	 * Returns all term meta keys found on a sample term (no filtering — let caller decide).
	 */
	private function sample_term_keys( string $taxonomy ): array {
		$terms = get_terms( [ 'taxonomy' => $taxonomy, 'number' => 1, 'hide_empty' => false ] );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return [];
		}

		return array_keys( get_term_meta( $terms[0]->term_id ) );
	}
}
