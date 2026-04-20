<?php

namespace Space\Core\Modules\StoreNotices;

defined( 'ABSPATH' ) || exit;

use Space\Core\Abstracts\AbstractModule;

/**
 * Store Notices — DB-backed frontend notices with multilingual text,
 * scheduling, per-page/product/category targeting, colors, and local Material Icons.
 *
 * Table: {prefix}sc_store_notices
 */
class Module extends AbstractModule {

	private const TABLE = 'sc_store_notices';

	private ?array $renderable_notices = null;

	public static function drop_table(): void {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}

	public function get_label(): string {
		return __( 'Store Notices', 'space-core' );
	}

	// ── Lifecycle ─────────────────────────────────────────────────

	public function get_description(): string {
		return __( 'Custom store notices with scheduling, multilingual text, and granular page targeting.', 'space-core' );
	}

	public function on_activate(): void {
		$this->create_table();
	}

	private function create_table(): void {
		global $wpdb;
		$table   = $this->table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title            TEXT         NULL,
            message          TEXT         NULL,
            icon             VARCHAR(100) NULL,
            start_at         DATETIME     NULL,
            end_at           DATETIME     NULL,
            text_color       VARCHAR(20)  NOT NULL DEFAULT '#ffffff',
            background_color VARCHAR(20)  NOT NULL DEFAULT '#2271b1',
            is_dismissible   TINYINT(1)   NOT NULL DEFAULT 1,
            is_active        TINYINT(1)   NOT NULL DEFAULT 1,
            pages            LONGTEXT     NULL,
            posts            LONGTEXT     NULL,
            products         LONGTEXT     NULL,
            categories       LONGTEXT     NULL,
            product_cat      LONGTEXT     NULL
        ) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	private function table_name(): string {
		global $wpdb;

		return $wpdb->prefix . self::TABLE;
	}

	// ── Boot ──────────────────────────────────────────────────────

	public function boot(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_footer', [ $this, 'render_notices' ] );
		add_action( 'wp_ajax_sc_save_store_notice', [ $this, 'ajax_save' ] );
		add_action( 'wp_ajax_sc_delete_store_notice', [ $this, 'ajax_delete' ] );
		add_action( 'wp_ajax_sc_search_items', [ $this, 'ajax_search_items' ] );
		add_action( 'admin_menu', [ $this, 'register_menu' ] );

	}

	// ── Assets ────────────────────────────────────────────────────

	public function enqueue_assets(): void {
		$notices = $this->get_renderable_notices();
		if ( empty( $notices ) ) {
			return;
		}

		wp_enqueue_style(
			'space-core-front',
			SPACE_CORE_URL . 'assets/css/front.css',
			[],
			SPACE_CORE_VERSION
		);

		foreach ( $notices as $notice ) {
			if ( ! empty( $notice['icon'] ) ) {
				wp_enqueue_style(
					'sc-store-notices-material-symbols',
					'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200',
					[],
					null
				);
				break;
			}
		}
	}

	// ── Frontend rendering ────────────────────────────────────────

	private function get_renderable_notices(): array {
		if ( $this->renderable_notices !== null ) {
			return $this->renderable_notices;
		}

		global $wpdb;
		$table = $this->table_name();
		$now   = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$notices = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table}
             WHERE is_active = 1
               AND (start_at IS NULL OR start_at <= %s)
               AND (end_at   IS NULL OR end_at   >= %s)",
			$now,
			$now
		), ARRAY_A ) ?: [];

		$renderable = [];
		foreach ( $notices as $notice ) {
			if ( ! $this->matches_page( $notice ) ) {
				continue;
			}
			$renderable[] = $notice;
		}

		$this->renderable_notices = $renderable;

		return $this->renderable_notices;
	}

	private function matches_page( array $notice ): bool {
		$dimensions = [
			'pages'       => fn( $ids ) => is_page( $ids ),
			'posts'       => fn( $ids ) => is_singular() && in_array( get_the_ID(), $ids, true ),
			'products'    => fn( $ids ) => is_singular( 'product' ) && in_array( get_the_ID(), $ids, true ),
			'categories'  => fn( $ids ) => is_category( $ids ),
			'product_cat' => fn( $ids ) => is_tax( 'product_cat' ) && in_array( get_queried_object_id(), $ids, true ),
		];

		$wildcard_checks = [
			'pages'       => fn() => is_page(),
			'posts'       => fn() => is_singular(),
			'products'    => fn() => is_singular( 'product' ),
			'categories'  => fn() => is_category(),
			'product_cat' => fn() => is_tax( 'product_cat' ),
		];

		$any_dimension_set = false;

		foreach ( $dimensions as $col => $check ) {
			$raw = $notice[ $col ] ?? null;
			if ( $raw === null || $raw === '' ) {
				continue;
			}

			$decoded = json_decode( $raw, true );
			if ( ! is_array( $decoded ) ) {
				continue;
			}

			$any_dimension_set = true;

			if ( $decoded === [ '*' ] ) {
				if ( ( $wildcard_checks[ $col ] )() ) {
					return true;
				}
			} else {
				$ids = array_map( 'intval', $decoded );
				if ( ( $check )( $ids ) ) {
					return true;
				}
			}
		}

		// If no dimension is set at all, show everywhere.
		return ! $any_dimension_set;
	}

	public function render_notices(): void {
		$notices = $this->get_renderable_notices();
		if ( empty( $notices ) ) {
			return;
		}

		$offset = 0;
		foreach ( $notices as $notice ) {
			$this->output_notice( $notice, $offset );
			$offset += 60;
		}

		$this->output_js();
	}

	private function output_notice( array $n, int $bottom_offset ): void {
		$id      = (int) $n['id'];
		$title   = $this->resolve_json_text( $n['title'] );
		$message = $this->resolve_json_text( $n['message'] );
		$icon    = sanitize_text_field( $n['icon'] ?? '' );
		$bg      = sanitize_hex_color( $n['background_color'] ?? '#2271b1' ) ?? '#2271b1';
		$text    = sanitize_hex_color( $n['text_color'] ?? '#ffffff' ) ?? '#ffffff';
		$dismiss = (bool) ( $n['is_dismissible'] ?? true );

		echo $this->view( 'front/notice/item', compact( 'id', 'title', 'message', 'icon', 'bg', 'text', 'dismiss', 'bottom_offset' ) );
	}

	private function resolve_json_text( ?string $json_col ): string {
		if ( $json_col === null || $json_col === '' ) {
			return '';
		}
		$data = json_decode( $json_col, true );
		if ( ! is_array( $data ) ) {
			return '';
		}
		$lang = substr( get_locale(), 0, 2 );

		return $data[ $lang ] ?? $data['en'] ?? reset( $data ) ?? '';
	}

	private function output_js(): void {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;
		echo $this->view( 'front/notice/js', [] );
	}

	// ── AJAX ──────────────────────────────────────────────────────

	public function ajax_save(): void {
		check_ajax_referer( 'space_core_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [], 403 );
		}

		global $wpdb;
		$raw    = isset( $_POST['notice'] ) ? wp_unslash( $_POST['notice'] ) : '{}'; // phpcs:ignore
		$notice = json_decode( $raw, true );
		if ( ! is_array( $notice ) ) {
			wp_send_json_error();
		}

		$encode_ids = function ( $all_flag, $ids_raw ): ?string {
			if ( $all_flag ) {
				return json_encode( [ '*' ] );
			}
			$ids = array_filter( array_map( 'absint', (array) $ids_raw ) );

			return empty( $ids ) ? null : json_encode( array_values( $ids ) );
		};

		$data = [
			'title'            => json_encode( [
				'en' => sanitize_text_field( $notice['title_en'] ?? '' ),
				'ar' => sanitize_text_field( $notice['title_ar'] ?? '' ),
			] ),
			'message'          => json_encode( [
				'en' => wp_kses_post( $notice['message_en'] ?? '' ),
				'ar' => wp_kses_post( $notice['message_ar'] ?? '' ),
			] ),
			'icon'             => sanitize_text_field( $notice['icon'] ?? '' ),
			'start_at'         => sanitize_text_field( $notice['start_at'] ?? '' ) ?: null,
			'end_at'           => sanitize_text_field( $notice['end_at'] ?? '' ) ?: null,
			'text_color'       => sanitize_hex_color( $notice['text_color'] ?? '#ffffff' ) ?? '#ffffff',
			'background_color' => sanitize_hex_color( $notice['background_color'] ?? '#2271b1' ) ?? '#2271b1',
			'is_dismissible'   => ! empty( $notice['is_dismissible'] ) ? 1 : 0,
			'is_active'        => ! empty( $notice['is_active'] ) ? 1 : 0,
			'pages'            => $encode_ids( ! empty( $notice['pages_all'] ), $notice['pages'] ?? [] ),
			'posts'            => $encode_ids( ! empty( $notice['posts_all'] ), $notice['posts'] ?? [] ),
			'products'         => $encode_ids( ! empty( $notice['products_all'] ), $notice['products'] ?? [] ),
			'categories'       => $encode_ids( ! empty( $notice['categories_all'] ), $notice['categories'] ?? [] ),
			'product_cat'      => $encode_ids( ! empty( $notice['product_cat_all'] ), $notice['product_cat'] ?? [] ),
		];

		$id    = absint( $notice['id'] ?? 0 );
		$table = $this->table_name();

		if ( $id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update( $table, $data, [ 'id' => $id ] );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert( $table, $data );
			$id = $wpdb->insert_id;
		}

		wp_send_json_success( [ 'id' => $id ] );
	}

	public function ajax_delete(): void {
		check_ajax_referer( 'space_core_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [], 403 );
		}

		global $wpdb;
		$id    = absint( $_POST['id'] ?? 0 ); // phpcs:ignore
		$table = $this->table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );
		wp_send_json_success();
	}

	public function ajax_search_items(): void {
		check_ajax_referer( 'space_core_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [], 403 );
		}

		$type = sanitize_key( $_GET['type'] ?? '' ); // phpcs:ignore
		$q    = sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) ); // phpcs:ignore

		$results = [];

		if ( in_array( $type, [ 'page', 'post', 'product' ], true ) ) {
			$post_type = $type === 'page' ? 'page' : ( $type === 'product' ? 'product' : 'post' );
			$posts     = get_posts( [
				'post_type'   => $post_type,
				's'           => $q,
				'numberposts' => 20,
				'orderby'     => 'title',
				'order'       => 'ASC',
				'post_status' => 'publish',
			] );
			foreach ( $posts as $p ) {
				$results[] = [ 'id' => $p->ID, 'text' => $p->post_title ];
			}
		} elseif ( in_array( $type, [ 'category', 'product_cat' ], true ) ) {
			$taxonomy = $type === 'category' ? 'category' : 'product_cat';
			$terms    = get_terms( [
				'taxonomy'   => $taxonomy,
				'search'     => $q,
				'number'     => 20,
				'hide_empty' => false,
				'orderby'    => 'name',
			] );
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $t ) {
					$results[] = [ 'id' => $t->term_id, 'text' => $t->name ];
				}
			}
		}

		wp_send_json( [ 'results' => $results ] );
	}

	// ── Admin UI ──────────────────────────────────────────────────

	public function render_settings(): void {
		global $wpdb;
		$table        = $this->table_name();
		$nonce        = wp_create_nonce( 'space_core_admin' );
		$screen       = get_current_screen();
		$postbox_page = $screen ? $screen->id : 'space-core-store-notices';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$notices = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC", ARRAY_A ) ?: [];

		wp_enqueue_script( 'postbox' );
		wp_enqueue_script( 'sc-select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', [ 'jquery' ], '4.1.0', true );
		wp_enqueue_style( 'sc-select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', [], '4.1.0' );
		echo $this->view( 'admin/settings', [
			'notices'      => $notices,
			'nonce'        => $nonce,
			'postbox_page' => $postbox_page,
		] );
	}

	public function register_menu(): void {
		add_menu_page(
			__( 'Store Notices', 'space-core' ),
			__( 'Store Notices', 'space-core' ),
			'manage_options',
			'sc-store-notices',
			[ $this, 'render_settings' ],
			'dashicons-megaphone',
			25
		);
	}

	private function render_notice_card( array $n ): void {
		$id      = (int) ( $n['id'] ?? 0 );
		$title   = is_string( $n['title'] ?? null ) ? json_decode( $n['title'], true ) : [];
		$message = is_string( $n['message'] ?? null ) ? json_decode( $n['message'], true ) : [];
		$title   = is_array( $title ) ? $title : [];
		$message = is_array( $message ) ? $message : [];

		$decode_ids = function ( ?string $col ): array {
			if ( $col === null || $col === '' ) {
				return [];
			}
			$d = json_decode( $col, true );

			return is_array( $d ) ? $d : [];
		};

		$pages_ids       = $decode_ids( $n['pages'] ?? null );
		$posts_ids       = $decode_ids( $n['posts'] ?? null );
		$products_ids    = $decode_ids( $n['products'] ?? null );
		$categories_ids  = $decode_ids( $n['categories'] ?? null );
		$product_cat_ids = $decode_ids( $n['product_cat'] ?? null );

		$pages_all       = $pages_ids === [ '*' ];
		$posts_all       = $posts_ids === [ '*' ];
		$products_all    = $products_ids === [ '*' ];
		$categories_all  = $categories_ids === [ '*' ];
		$product_cat_all = $product_cat_ids === [ '*' ];

		$bg          = esc_attr( $n['background_color'] ?? '#2271b1' );
		$text        = esc_attr( $n['text_color'] ?? '#ffffff' );
		$card_title  = $this->notice_card_title( $title );
		$card_dom_id = $id ? 'sc-sn-card-' . $id : 'sc-sn-card-new';
		$screen      = get_current_screen();
		$state_class = function_exists( 'postbox_classes' ) && $screen ? postbox_classes( $card_dom_id, $screen->id ) : '';
		$is_closed   = in_array( 'closed', array_filter( explode( ' ', $state_class ) ), true );

		$dims = [
			[
				'label'   => __( 'Pages', 'space-core' ),
				'all_chk' => 'sc-sn-pages-all',
				'all_val' => $pages_all,
				'ids'     => $pages_all ? [] : $pages_ids,
				'ms_cls'  => 'sc-sn-pages',
				'type'    => 'page'
			],
			[
				'label'   => __( 'Posts', 'space-core' ),
				'all_chk' => 'sc-sn-posts-all',
				'all_val' => $posts_all,
				'ids'     => $posts_all ? [] : $posts_ids,
				'ms_cls'  => 'sc-sn-posts',
				'type'    => 'post'
			],
			[
				'label'   => __( 'Products', 'space-core' ),
				'all_chk' => 'sc-sn-products-all',
				'all_val' => $products_all,
				'ids'     => $products_all ? [] : $products_ids,
				'ms_cls'  => 'sc-sn-products',
				'type'    => 'product'
			],
			[
				'label'   => __( 'Blog Categories', 'space-core' ),
				'all_chk' => 'sc-sn-categories-all',
				'all_val' => $categories_all,
				'ids'     => $categories_all ? [] : $categories_ids,
				'ms_cls'  => 'sc-sn-categories',
				'type'    => 'category'
			],
			[
				'label'   => __( 'Product Categories', 'space-core' ),
				'all_chk' => 'sc-sn-product-cat-all',
				'all_val' => $product_cat_all,
				'ids'     => $product_cat_all ? [] : $product_cat_ids,
				'ms_cls'  => 'sc-sn-product-cat',
				'type'    => 'product_cat'
			],
		];

		echo $this->view( 'admin/notice-card', [
			'notice'      => $n,
			'id'          => $id,
			'title'       => $title,
			'message'     => $message,
			'bg'          => $bg,
			'text'        => $text,
			'card_title'  => $card_title,
			'card_dom_id' => $card_dom_id,
			'state_class' => $state_class,
			'is_closed'   => $is_closed,
			'dims'        => $dims,
		] );
	}

	private function notice_card_title( array $title ): string {
		$locale = $this->notice_card_title_locale();
		$value  = trim( (string) ( $title[ $locale ] ?? '' ) );

		if ( $value === '' && $locale !== 'en' ) {
			$value = trim( (string) ( $title['en'] ?? '' ) );
		}

		if ( $value === '' ) {
			$value = trim( (string) ( $title['ar'] ?? '' ) );
		}

		return $value !== '' ? $value : __( 'New Notice', 'space-core' );
	}

	private function notice_card_title_locale(): string {
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();

		return substr( $locale, 0, 2 ) === 'ar' ? 'ar' : 'en';
	}

	/** Resolve titles for pre-selected IDs in Select2 fields. */
	private function resolve_selected_titles( string $type, array $ids ): array {
		if ( empty( $ids ) ) {
			return [];
		}
		$results = [];
		if ( in_array( $type, [ 'page', 'post', 'product' ], true ) ) {
			foreach ( $ids as $id ) {
				$title = get_the_title( (int) $id );
				if ( $title ) {
					$results[] = [ 'id' => (int) $id, 'text' => $title ];
				}
			}
		} elseif ( in_array( $type, [ 'category', 'product_cat' ], true ) ) {
			$taxonomy = $type === 'category' ? 'category' : 'product_cat';
			foreach ( $ids as $id ) {
				$term = get_term( (int) $id, $taxonomy );
				if ( $term && ! is_wp_error( $term ) ) {
					$results[] = [ 'id' => $term->term_id, 'text' => $term->name ];
				}
			}
		}

		return $results;
	}

}
