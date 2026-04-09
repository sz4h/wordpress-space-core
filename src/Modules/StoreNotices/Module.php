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

    public function get_label(): string {
        return __( 'Store Notices', 'space-core' );
    }

    public function get_description(): string {
        return __( 'Custom store notices with scheduling, multilingual text, and granular page targeting.', 'space-core' );
    }

    // ── Lifecycle ─────────────────────────────────────────────────

    public function on_activate(): void {
        $this->create_table();
    }

    private function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE;
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

    public static function drop_table(): void {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
    }

    // ── Boot ──────────────────────────────────────────────────────

    public function boot(): void {
        add_action( 'wp_footer',                          [ $this, 'render_notices' ] );
        add_action( 'wp_head',                            [ $this, 'enqueue_font' ] );
        add_action( 'wp_ajax_sc_save_store_notice',       [ $this, 'ajax_save' ] );
        add_action( 'wp_ajax_sc_delete_store_notice',     [ $this, 'ajax_delete' ] );
    }

    // ── Font ──────────────────────────────────────────────────────

    public function enqueue_font(): void {
        global $wpdb;
        $table = $this->table_name();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $has_icon = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE is_active = 1 AND icon IS NOT NULL AND icon != ''" );
        if ( ! $has_icon ) return;

        $font_url = SPACE_CORE_URL . 'assets/fonts/MaterialIcons-Regular.woff2';
        ?>
        <style>
        @font-face {
            font-family: 'Material Icons';
            font-style: normal;
            font-weight: 400;
            src: url('<?php echo esc_url( $font_url ); ?>') format('woff2');
        }
        .sc-notice-material-icon {
            font-family: 'Material Icons';
            font-weight: normal;
            font-style: normal;
            font-size: 20px;
            line-height: 1;
            letter-spacing: normal;
            text-transform: none;
            display: inline-block;
            white-space: nowrap;
            word-wrap: normal;
            -webkit-font-feature-settings: 'liga';
            font-feature-settings: 'liga';
            -webkit-font-smoothing: antialiased;
            vertical-align: middle;
        }
        </style>
        <?php
    }

    // ── Frontend rendering ────────────────────────────────────────

    public function render_notices(): void {
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

        $shown = false;
        $offset = 0;
        foreach ( $notices as $notice ) {
            if ( ! $this->matches_page( $notice ) ) continue;
            $this->output_notice( $notice, $offset );
            $offset += 60;
            $shown = true;
        }

        if ( $shown ) {
            $this->output_styles();
            $this->output_js();
        }
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
            if ( $raw === null || $raw === '' ) continue;

            $decoded = json_decode( $raw, true );
            if ( ! is_array( $decoded ) ) continue;

            $any_dimension_set = true;

            if ( $decoded === [ '*' ] ) {
                if ( ( $wildcard_checks[ $col ] )() ) return true;
            } else {
                $ids = array_map( 'intval', $decoded );
                if ( ( $check )( $ids ) ) return true;
            }
        }

        // If no dimension is set at all, show everywhere.
        return ! $any_dimension_set;
    }

    private function resolve_json_text( ?string $json_col ): string {
        if ( $json_col === null || $json_col === '' ) return '';
        $data = json_decode( $json_col, true );
        if ( ! is_array( $data ) ) return '';
        $lang = substr( get_locale(), 0, 2 );
        return $data[ $lang ] ?? $data['en'] ?? reset( $data ) ?? '';
    }

    private function output_notice( array $n, int $bottom_offset ): void {
        $id      = (int) $n['id'];
        $title   = $this->resolve_json_text( $n['title'] );
        $message = $this->resolve_json_text( $n['message'] );
        $icon    = sanitize_text_field( $n['icon'] ?? '' );
        $bg      = sanitize_hex_color( $n['background_color'] ?? '#2271b1' ) ?? '#2271b1';
        $text    = sanitize_hex_color( $n['text_color'] ?? '#ffffff' ) ?? '#ffffff';
        $dismiss = (bool) ( $n['is_dismissible'] ?? true );

        echo '<div id="sc-notice-' . $id . '" class="sc-store-notice" '
            . 'style="background:' . esc_attr( $bg ) . ';color:' . esc_attr( $text ) . ';bottom:' . $bottom_offset . 'px;" '
            . 'data-id="' . $id . '">';

        if ( $icon ) {
            echo '<span class="sc-notice-material-icon" aria-hidden="true">' . esc_html( $icon ) . '</span>';
        }

        echo '<span class="sc-notice-body">';
        if ( $title ) {
            echo '<strong class="sc-notice-title">' . esc_html( $title ) . '</strong> ';
        }
        echo '<span class="sc-notice-msg">' . wp_kses_post( $message ) . '</span>';
        echo '</span>';

        if ( $dismiss ) {
            echo '<button type="button" class="sc-notice-dismiss" aria-label="' . esc_attr__( 'Dismiss', 'space-core' ) . '" '
                . 'style="color:' . esc_attr( $text ) . ';">&#x2715;</button>';
        }

        echo '</div>';
    }

    private function output_styles(): void {
        static $done = false;
        if ( $done ) return;
        $done = true;
        ?>
        <style>
        .sc-store-notice {
            position: fixed;
            left: 0; right: 0;
            z-index: 99998;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 20px;
            font-size: 14px;
            font-weight: 500;
            box-shadow: 0 -2px 8px rgba(0,0,0,.15);
        }
        .sc-notice-body { flex: 1; text-align: center; }
        .sc-notice-dismiss {
            background: none; border: none; cursor: pointer;
            font-size: 16px; opacity: .8; padding: 0 4px;
            line-height: 1;
        }
        .sc-notice-dismiss:hover { opacity: 1; }
        @media (max-width: 782px) { .sc-store-notice { font-size: 13px; padding: 10px 12px; } }
        </style>
        <?php
    }

    private function output_js(): void {
        static $done = false;
        if ( $done ) return;
        $done = true;
        ?>
        <script>
        (function(){
            document.querySelectorAll('.sc-store-notice').forEach(function(el){
                var id = el.dataset.id;
                if (id && sessionStorage.getItem('sc_notice_' + id)) el.style.display = 'none';
            });
            document.querySelectorAll('.sc-notice-dismiss').forEach(function(btn){
                btn.addEventListener('click', function(){
                    var notice = btn.closest('.sc-store-notice');
                    var id     = notice ? notice.dataset.id : '';
                    if (notice) notice.style.display = 'none';
                    if (id) { try { sessionStorage.setItem('sc_notice_' + id, '1'); } catch(e){} }
                });
            });
        })();
        </script>
        <?php
    }

    // ── AJAX ──────────────────────────────────────────────────────

    public function ajax_save(): void {
        check_ajax_referer( 'space_core_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [], 403 );

        global $wpdb;
        $raw    = isset( $_POST['notice'] ) ? wp_unslash( $_POST['notice'] ) : '{}'; // phpcs:ignore
        $notice = json_decode( $raw, true );
        if ( ! is_array( $notice ) ) wp_send_json_error();

        $encode_ids = function( $all_flag, $ids_raw ): ?string {
            if ( $all_flag ) return json_encode( [ '*' ] );
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
            'icon'             => sanitize_text_field( $notice['icon']             ?? '' ),
            'start_at'         => sanitize_text_field( $notice['start_at']         ?? '' ) ?: null,
            'end_at'           => sanitize_text_field( $notice['end_at']           ?? '' ) ?: null,
            'text_color'       => sanitize_hex_color( $notice['text_color']       ?? '#ffffff' ) ?? '#ffffff',
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
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [], 403 );

        global $wpdb;
        $id    = absint( $_POST['id'] ?? 0 ); // phpcs:ignore
        $table = $this->table_name();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );
        wp_send_json_success();
    }

    // ── Admin UI ──────────────────────────────────────────────────

    public function render_settings(): void {
        global $wpdb;
        $table   = $this->table_name();
        $nonce   = wp_create_nonce( 'space_core_admin' );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $notices = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC", ARRAY_A ) ?: [];

        // Build option lists.
        $pages_list      = get_pages( [ 'sort_column' => 'post_title', 'sort_order' => 'ASC' ] );
        $posts_list      = get_posts( [ 'numberposts' => 200, 'orderby' => 'title', 'order' => 'ASC' ] );
        $products_list   = class_exists( 'WooCommerce' ) ? get_posts( [ 'post_type' => 'product', 'numberposts' => 200, 'orderby' => 'title', 'order' => 'ASC' ] ) : [];
        $categories_list = get_categories( [ 'orderby' => 'name', 'hide_empty' => false ] );
        $product_cat_list = class_exists( 'WooCommerce' ) ? get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name' ] ) : [];
        ?>
        <div style="max-width:900px;">
            <p class="description"><?php esc_html_e( 'Create store notices shown at the bottom of the frontend. Supports multilingual text, scheduling, and granular page targeting.', 'space-core' ); ?></p>

            <p style="margin-top:12px;">
                <button type="button" class="button button-primary" id="sc-sn-add">
                    + <?php esc_html_e( 'Add Notice', 'space-core' ); ?>
                </button>
            </p>

            <div id="sc-sn-list" style="margin-top:16px;">
                <?php foreach ( $notices as $n ) : ?>
                    <?php $this->render_notice_card( $n, $pages_list, $posts_list, $products_list, $categories_list, $product_cat_list ); ?>
                <?php endforeach; ?>
            </div>
        </div>

        <template id="sc-sn-template">
            <?php $this->render_notice_card( [], $pages_list, $posts_list, $products_list, $categories_list, $product_cat_list ); ?>
        </template>

        <script>
        jQuery(function($){
            var nonce = '<?php echo esc_js( $nonce ); ?>';

            // Add new card.
            $('#sc-sn-add').on('click', function(){
                var html = document.getElementById('sc-sn-template').innerHTML;
                $('#sc-sn-list').prepend(html);
            });

            // Delete.
            $(document).on('click', '.sc-sn-delete', function(){
                if (!confirm('<?php echo esc_js( __( 'Delete this notice?', 'space-core' ) ); ?>')) return;
                var $card = $(this).closest('.sc-sn-card');
                var id    = $card.data('id');
                if (!id) { $card.remove(); return; }
                $.post(spaceCore.ajaxUrl, { action:'sc_delete_store_notice', nonce:nonce, id:id }, function(res){
                    if (res.success) $card.slideUp(200, function(){ $(this).remove(); });
                });
            });

            // Save.
            $(document).on('click', '.sc-sn-save', function(){
                var $card = $(this).closest('.sc-sn-card');
                var $btn  = $(this);
                var id    = $card.data('id');
                var get   = function(cls){ return $card.find('.' + cls).val(); };
                var getIds = function(cls){
                    return $card.find('.' + cls + ' option:selected').map(function(){ return parseInt($(this).val()) || 0; }).get().filter(Boolean);
                };
                var data = {
                    id:               id || 0,
                    title_en:         get('sc-sn-title-en'),
                    title_ar:         get('sc-sn-title-ar'),
                    message_en:       get('sc-sn-msg-en'),
                    message_ar:       get('sc-sn-msg-ar'),
                    icon:             get('sc-sn-icon'),
                    start_at:         get('sc-sn-start'),
                    end_at:           get('sc-sn-end'),
                    text_color:       get('sc-sn-text-color'),
                    background_color: get('sc-sn-bg-color'),
                    is_dismissible:   $card.find('.sc-sn-dismissible').is(':checked') ? 1 : 0,
                    is_active:        $card.find('.sc-sn-active').is(':checked') ? 1 : 0,
                    pages_all:        $card.find('.sc-sn-pages-all').is(':checked') ? 1 : 0,
                    pages:            getIds('sc-sn-pages'),
                    posts_all:        $card.find('.sc-sn-posts-all').is(':checked') ? 1 : 0,
                    posts:            getIds('sc-sn-posts'),
                    products_all:     $card.find('.sc-sn-products-all').is(':checked') ? 1 : 0,
                    products:         getIds('sc-sn-products'),
                    categories_all:   $card.find('.sc-sn-categories-all').is(':checked') ? 1 : 0,
                    categories:       getIds('sc-sn-categories'),
                    product_cat_all:  $card.find('.sc-sn-product-cat-all').is(':checked') ? 1 : 0,
                    product_cat:      getIds('sc-sn-product-cat'),
                };
                $btn.prop('disabled', true);
                $.post(spaceCore.ajaxUrl, { action:'sc_save_store_notice', nonce:nonce, notice:JSON.stringify(data) },
                    function(res){
                        if (res.success) {
                            if (res.data.id) $card.data('id', res.data.id);
                            $card.find('.sc-sn-status').text('<?php echo esc_js( __( 'Saved!', 'space-core' ) ); ?>').css('color','#2e7d32');
                            setTimeout(function(){ $card.find('.sc-sn-status').text(''); }, 3000);
                        }
                    }
                ).always(function(){ $btn.prop('disabled', false); });
            });

            // Toggle "all" checkbox shows/hides multiselect.
            $(document).on('change', '.sc-sn-all-chk', function(){
                var target = $(this).data('target');
                $(this).closest('.sc-sn-dim').find('.' + target).toggle(!$(this).is(':checked'));
            });

            // Color preview.
            $(document).on('input', '.sc-sn-bg-color,.sc-sn-text-color', function(){
                var $card = $(this).closest('.sc-sn-card');
                $card.find('.sc-sn-preview').css({
                    background: $card.find('.sc-sn-bg-color').val(),
                    color:      $card.find('.sc-sn-text-color').val(),
                });
            });
        });
        </script>
        <?php
    }

    private function render_notice_card(
        array $n,
        array $pages_list,
        array $posts_list,
        array $products_list,
        array $categories_list,
        array $product_cat_list
    ): void {
        $id      = (int) ( $n['id'] ?? 0 );
        $title   = is_string( $n['title'] ?? null ) ? json_decode( $n['title'], true ) : [];
        $message = is_string( $n['message'] ?? null ) ? json_decode( $n['message'], true ) : [];
        $title   = is_array( $title )   ? $title   : [];
        $message = is_array( $message ) ? $message : [];

        $decode_ids = function( ?string $col ): array {
            if ( $col === null || $col === '' ) return [];
            $d = json_decode( $col, true );
            return is_array( $d ) ? $d : [];
        };

        $pages_ids        = $decode_ids( $n['pages']       ?? null );
        $posts_ids        = $decode_ids( $n['posts']       ?? null );
        $products_ids     = $decode_ids( $n['products']    ?? null );
        $categories_ids   = $decode_ids( $n['categories']  ?? null );
        $product_cat_ids  = $decode_ids( $n['product_cat'] ?? null );

        $pages_all       = $pages_ids === [ '*' ];
        $posts_all       = $posts_ids === [ '*' ];
        $products_all    = $products_ids === [ '*' ];
        $categories_all  = $categories_ids === [ '*' ];
        $product_cat_all = $product_cat_ids === [ '*' ];

        $bg   = esc_attr( $n['background_color'] ?? '#2271b1' );
        $text = esc_attr( $n['text_color'] ?? '#ffffff' );

        $ms = 'style="width:100%;height:120px;"';

        ?>
        <div class="sc-sn-card" data-id="<?php echo $id; ?>"
             style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:16px;">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <!-- Title -->
                <div>
                    <label style="font-weight:600;"><?php esc_html_e( 'Title (EN)', 'space-core' ); ?></label>
                    <input type="text" class="sc-sn-title-en widefat"
                           value="<?php echo esc_attr( $title['en'] ?? '' ); ?>">
                </div>
                <div>
                    <label style="font-weight:600;"><?php esc_html_e( 'Title (AR)', 'space-core' ); ?></label>
                    <input type="text" class="sc-sn-title-ar widefat" dir="rtl"
                           value="<?php echo esc_attr( $title['ar'] ?? '' ); ?>">
                </div>

                <!-- Message -->
                <div>
                    <label style="font-weight:600;"><?php esc_html_e( 'Message (EN)', 'space-core' ); ?></label>
                    <textarea class="sc-sn-msg-en widefat" rows="2"><?php echo esc_textarea( $message['en'] ?? '' ); ?></textarea>
                </div>
                <div>
                    <label style="font-weight:600;"><?php esc_html_e( 'Message (AR)', 'space-core' ); ?></label>
                    <textarea class="sc-sn-msg-ar widefat" rows="2" dir="rtl"><?php echo esc_textarea( $message['ar'] ?? '' ); ?></textarea>
                </div>

                <!-- Icon -->
                <div>
                    <label><?php esc_html_e( 'Icon (Material Icon name)', 'space-core' ); ?></label>
                    <input type="text" class="sc-sn-icon regular-text"
                           value="<?php echo esc_attr( $n['icon'] ?? '' ); ?>" placeholder="local_offer">
                </div>

                <!-- Colors -->
                <div style="display:flex;gap:16px;align-items:flex-end;">
                    <div>
                        <label><?php esc_html_e( 'Background', 'space-core' ); ?></label><br>
                        <input type="color" class="sc-sn-bg-color" value="<?php echo $bg; ?>">
                    </div>
                    <div>
                        <label><?php esc_html_e( 'Text', 'space-core' ); ?></label><br>
                        <input type="color" class="sc-sn-text-color" value="<?php echo $text; ?>">
                    </div>
                </div>

                <!-- Dates -->
                <div>
                    <label><?php esc_html_e( 'Start At', 'space-core' ); ?></label>
                    <input type="datetime-local" class="sc-sn-start"
                           value="<?php echo esc_attr( $n['start_at'] ? str_replace( ' ', 'T', substr( $n['start_at'], 0, 16 ) ) : '' ); ?>">
                </div>
                <div>
                    <label><?php esc_html_e( 'End At', 'space-core' ); ?></label>
                    <input type="datetime-local" class="sc-sn-end"
                           value="<?php echo esc_attr( $n['end_at'] ? str_replace( ' ', 'T', substr( $n['end_at'], 0, 16 ) ) : '' ); ?>">
                </div>

                <!-- Toggles -->
                <div style="grid-column:1/-1;display:flex;gap:20px;">
                    <label>
                        <input type="checkbox" class="sc-sn-active" <?php checked( ! $id || ! empty( $n['is_active'] ) ); ?>>
                        <?php esc_html_e( 'Active', 'space-core' ); ?>
                    </label>
                    <label>
                        <input type="checkbox" class="sc-sn-dismissible" <?php checked( $id ? ! empty( $n['is_dismissible'] ) : true ); ?>>
                        <?php esc_html_e( 'Dismissible', 'space-core' ); ?>
                    </label>
                </div>
            </div>

            <!-- Targeting -->
            <details style="margin-top:16px;">
                <summary style="cursor:pointer;font-weight:600;"><?php esc_html_e( 'Page Targeting (leave all empty = show everywhere)', 'space-core' ); ?></summary>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px;">

                    <?php
                    $dims = [
                        [ 'key' => 'pages',       'label' => __( 'Pages',           'space-core' ), 'all_chk' => 'sc-sn-pages-all',       'all_val' => $pages_all,       'ids' => $pages_all ? [] : $pages_ids,       'ms_cls' => 'sc-sn-pages',
                          'options' => array_map( fn( $p ) => [ 'id' => $p->ID, 'name' => $p->post_title ], $pages_list ) ],
                        [ 'key' => 'posts',       'label' => __( 'Posts',           'space-core' ), 'all_chk' => 'sc-sn-posts-all',       'all_val' => $posts_all,       'ids' => $posts_all ? [] : $posts_ids,       'ms_cls' => 'sc-sn-posts',
                          'options' => array_map( fn( $p ) => [ 'id' => $p->ID, 'name' => $p->post_title ], $posts_list ) ],
                        [ 'key' => 'products',    'label' => __( 'Products',        'space-core' ), 'all_chk' => 'sc-sn-products-all',    'all_val' => $products_all,    'ids' => $products_all ? [] : $products_ids,    'ms_cls' => 'sc-sn-products',
                          'options' => array_map( fn( $p ) => [ 'id' => $p->ID, 'name' => $p->post_title ], $products_list ) ],
                        [ 'key' => 'categories',  'label' => __( 'Blog Categories', 'space-core' ), 'all_chk' => 'sc-sn-categories-all',  'all_val' => $categories_all,  'ids' => $categories_all ? [] : $categories_ids,  'ms_cls' => 'sc-sn-categories',
                          'options' => array_map( fn( $t ) => [ 'id' => $t->term_id, 'name' => $t->name ], $categories_list ) ],
                        [ 'key' => 'product_cat', 'label' => __( 'Product Categories', 'space-core' ), 'all_chk' => 'sc-sn-product-cat-all', 'all_val' => $product_cat_all, 'ids' => $product_cat_all ? [] : $product_cat_ids, 'ms_cls' => 'sc-sn-product-cat',
                          'options' => is_array( $product_cat_list ) ? array_map( fn( $t ) => [ 'id' => $t->term_id, 'name' => $t->name ], $product_cat_list ) : [] ],
                    ];
                    foreach ( $dims as $dim ) : ?>
                        <div class="sc-sn-dim">
                            <label style="font-weight:600;"><?php echo esc_html( $dim['label'] ); ?></label>
                            <label style="display:block;margin:4px 0;">
                                <input type="checkbox" class="sc-sn-all-chk <?php echo esc_attr( $dim['all_chk'] ); ?>"
                                       data-target="<?php echo esc_attr( $dim['ms_cls'] ); ?>"
                                       <?php checked( $dim['all_val'] ); ?>>
                                <?php esc_html_e( 'All', 'space-core' ); ?>
                            </label>
                            <select multiple class="<?php echo esc_attr( $dim['ms_cls'] ); ?>" <?php echo $ms; ?>
                                    <?php echo $dim['all_val'] ? 'style="display:none;"' : ''; ?>>
                                <?php foreach ( $dim['options'] as $opt ) : ?>
                                    <option value="<?php echo esc_attr( $opt['id'] ); ?>"
                                            <?php selected( in_array( (int) $opt['id'], array_map( 'intval', $dim['ids'] ), true ) ); ?>>
                                        <?php echo esc_html( $opt['name'] ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endforeach; ?>

                </div>
            </details>

            <!-- Preview -->
            <div class="sc-sn-preview" style="margin-top:16px;padding:10px 16px;border-radius:4px;
                 display:flex;align-items:center;gap:8px;
                 background:<?php echo $bg; ?>;color:<?php echo $text; ?>;">
                <strong><?php echo esc_html( $title['en'] ?? '' ); ?></strong>
                <?php echo esc_html( $message['en'] ?? '' ); ?>
            </div>

            <!-- Actions -->
            <div style="margin-top:12px;display:flex;align-items:center;gap:8px;">
                <button type="button" class="button button-primary sc-sn-save"><?php esc_html_e( 'Save', 'space-core' ); ?></button>
                <button type="button" class="button sc-sn-delete" style="color:#b32d2e;"><?php esc_html_e( 'Delete', 'space-core' ); ?></button>
                <span class="sc-sn-status" style="font-weight:600;"></span>
            </div>
        </div>
        <?php
    }
}
