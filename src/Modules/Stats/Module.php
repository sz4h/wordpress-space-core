<?php

namespace Space\Core\Modules\Stats;

defined( 'ABSPATH' ) || exit;

use Space\Core\Abstracts\AbstractModule;

/**
 * Stats Dashboard — order + visitor stats with sortable widgets and Chart.js charts.
 *
 * Accessible to manage_options AND manage_woocommerce (shop_manager).
 */
class Module extends AbstractModule {

    public function get_label(): string {
        return __( 'Stats', 'space-core' );
    }

    public function get_description(): string {
        return __( 'Dashboard stats: orders, income, visitors by country and page views with charts.', 'space-core' );
    }

    public function on_activate(): void {
        VisitorDB::create_table();
        if ( ! wp_next_scheduled( 'sc_resolve_visitor_countries' ) ) {
            wp_schedule_event( time(), 'hourly', 'sc_resolve_visitor_countries' );
        }
    }

    public function boot(): void {
        add_action( 'wp',                   [ $this, 'track_visit' ] );
        add_action( 'sc_resolve_visitor_countries', [ VisitorDB::class, 'resolve_pending_countries' ] );
        add_action( 'admin_menu',           [ $this, 'register_stats_page' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_sc_stats_revenue_chart', [ $this, 'ajax_revenue_chart' ] );
    }

    public function track_visit(): void {
        if ( is_admin() || is_user_logged_in() ) return;
        if ( defined( 'DOING_CRON' ) && DOING_CRON ) return;

        $ip  = $this->get_client_ip();
        $url = esc_url_raw( ( isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ? 'https' : 'http' )
            . '://' . ( $_SERVER['HTTP_HOST'] ?? '' ) . ( $_SERVER['REQUEST_URI'] ?? '' ) );

        VisitorDB::record( $ip, $url );
    }

    private function get_client_ip(): string {
        foreach ( [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ] as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = trim( explode( ',', $_SERVER[ $key ] )[0] ); // phpcs:ignore
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) return $ip;
            }
        }
        return '0.0.0.0';
    }

    public function register_stats_page(): void {
        add_submenu_page(
            'index.php',
            __( 'Space Core Stats', 'space-core' ),
            __( 'SC Stats', 'space-core' ),
            'manage_woocommerce',
            'sc-stats',
            [ $this, 'render_stats_page' ]
        );
    }

    public function enqueue_assets( string $hook ): void {
        if ( ! in_array( $hook, [ 'dashboard_page_sc-stats', 'index.php' ], true ) ) return;

        wp_enqueue_script(
            'sc-chartjs',
            SPACE_CORE_URL . 'assets/js/chart.min.js',
            [],
            '4.4.4',
            true
        );
        wp_enqueue_script( 'jquery-ui-sortable' );
        wp_enqueue_script(
            'sc-stats',
            SPACE_CORE_URL . 'assets/js/admin.js',
            [ 'jquery', 'jquery-ui-sortable', 'sc-chartjs' ],
            SPACE_CORE_VERSION,
            true
        );
        wp_localize_script( 'sc-stats', 'spaceCore', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'space_core_admin' ),
        ] );
        wp_add_inline_style( 'wp-admin', $this->stats_css() );
    }

    // ── AJAX: Revenue chart data for selected month/year ─────────

    public function ajax_revenue_chart(): void {
        check_ajax_referer( 'space_core_admin', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error( [], 403 );
        if ( ! class_exists( 'WooCommerce' ) ) wp_send_json_error();

        $month = absint( $_POST['month'] ?? date( 'n' ) );
        $year  = absint( $_POST['year']  ?? date( 'Y' ) );
        $month = max( 1, min( 12, $month ) );
        $year  = max( (int) date( 'Y' ) - 3, min( (int) date( 'Y' ), $year ) );

        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore
            "SELECT DATE(p.post_date) as day, SUM(pm.meta_value) as revenue
             FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_order_total'
             WHERE p.post_type = 'shop_order'
               AND p.post_status IN ('wc-completed','wc-processing')
               AND MONTH(p.post_date) = %d AND YEAR(p.post_date) = %d
             GROUP BY DATE(p.post_date)
             ORDER BY day ASC",
            $month, $year
        ), ARRAY_A );

        // Build complete day array for the month.
        $days_in_month = cal_days_in_month( CAL_GREGORIAN, $month, $year );
        $by_day = [];
        foreach ( $rows as $row ) {
            $by_day[ $row['day'] ] = (float) $row['revenue'];
        }
        $labels = [];
        $data   = [];
        for ( $d = 1; $d <= $days_in_month; $d++ ) {
            $date     = sprintf( '%04d-%02d-%02d', $year, $month, $d );
            $labels[] = $d;
            $data[]   = $by_day[ $date ] ?? 0;
        }

        wp_send_json_success( [ 'labels' => $labels, 'data' => $data ] );
    }

    // ── Stats page ────────────────────────────────────────────────

    public function render_stats_page(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'space-core' ) );
        }

        $wc_ready = class_exists( 'WooCommerce' );
        global $wpdb;
        $today = current_time( 'Y-m-d' );

        // ── Order summary ─────────────────────────────────────────
        $total_orders = $today_orders = $processing = $total_income = $today_income = 0;
        $orders_by_country = $orders_by_status = [];

        if ( $wc_ready ) {
            $total_orders   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='shop_order' AND post_status != 'trash'" ); // phpcs:ignore
            $today_orders   = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='shop_order' AND post_status != 'trash' AND DATE(post_date) = %s", $today
            ) );
            $processing = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='shop_order' AND post_status='wc-processing'" ); // phpcs:ignore

            $total_income = (float) $wpdb->get_var( // phpcs:ignore
                "SELECT SUM(pm.meta_value) FROM {$wpdb->postmeta} pm
                 JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE p.post_type='shop_order' AND p.post_status IN ('wc-completed','wc-processing')
                 AND pm.meta_key='_order_total'"
            );
            $today_income = (float) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore
                "SELECT SUM(pm.meta_value) FROM {$wpdb->postmeta} pm
                 JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE p.post_type='shop_order' AND p.post_status IN ('wc-completed','wc-processing')
                 AND pm.meta_key='_order_total' AND DATE(p.post_date) = %s", $today
            ) );

            $orders_by_country = $wpdb->get_results( // phpcs:ignore
                "SELECT pm.meta_value as country_code, COUNT(*) as count
                 FROM {$wpdb->postmeta} pm
                 JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE p.post_type='shop_order' AND p.post_status != 'trash'
                 AND pm.meta_key='_billing_country' AND pm.meta_value != ''
                 GROUP BY pm.meta_value ORDER BY count DESC LIMIT 10",
                ARRAY_A
            ) ?: [];

            // Orders by status for pie chart.
            $status_rows = $wpdb->get_results( // phpcs:ignore
                "SELECT post_status, COUNT(*) as count FROM {$wpdb->posts}
                 WHERE post_type='shop_order' AND post_status != 'trash'
                 GROUP BY post_status",
                ARRAY_A
            ) ?: [];
            foreach ( $status_rows as $sr ) {
                $label = ucfirst( str_replace( 'wc-', '', $sr['post_status'] ) );
                $orders_by_status[ $label ] = (int) $sr['count'];
            }
        }

        // ── Top selling products ──────────────────────────────────
        $top_products = [];
        if ( $wc_ready ) {
            $top_products = $wpdb->get_results( // phpcs:ignore
                "SELECT oi_pid.meta_value as product_id, SUM(oi_qty.meta_value) as total_qty
                 FROM {$wpdb->prefix}woocommerce_order_items oi
                 JOIN {$wpdb->prefix}woocommerce_order_itemmeta oi_pid ON oi.order_item_id = oi_pid.order_item_id AND oi_pid.meta_key = '_product_id'
                 JOIN {$wpdb->prefix}woocommerce_order_itemmeta oi_qty ON oi.order_item_id = oi_qty.order_item_id AND oi_qty.meta_key = '_qty'
                 JOIN {$wpdb->posts} p ON p.ID = oi.order_id AND p.post_status IN ('wc-completed','wc-processing')
                 WHERE oi.order_item_type = 'line_item'
                 GROUP BY product_id ORDER BY total_qty DESC LIMIT 10",
                ARRAY_A
            ) ?: [];
        }

        // ── Recent orders ────────────────────────────────────────
        $recent_orders = [];
        if ( $wc_ready ) {
            $recent_orders = $wpdb->get_results( // phpcs:ignore
                "SELECT p.ID, p.post_date, p.post_status,
                        MAX(CASE WHEN pm.meta_key='_order_total'         THEN pm.meta_value END) as total,
                        MAX(CASE WHEN pm.meta_key='_billing_first_name'  THEN pm.meta_value END) as fname,
                        MAX(CASE WHEN pm.meta_key='_billing_last_name'   THEN pm.meta_value END) as lname
                 FROM {$wpdb->posts} p
                 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 WHERE p.post_type='shop_order' AND p.post_status != 'trash'
                 GROUP BY p.ID ORDER BY p.post_date DESC LIMIT 10",
                ARRAY_A
            ) ?: [];
        }

        // ── Low stock products ───────────────────────────────────
        $low_stock = [];
        if ( $wc_ready ) {
            $low_stock = $wpdb->get_results( // phpcs:ignore
                "SELECT p.ID, p.post_title, CAST(pm.meta_value AS UNSIGNED) as stock
                 FROM {$wpdb->posts} p
                 JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_stock'
                 WHERE p.post_type = 'product' AND p.post_status = 'publish'
                   AND pm.meta_value REGEXP '^[0-9]+$' AND CAST(pm.meta_value AS UNSIGNED) > 0 AND CAST(pm.meta_value AS UNSIGNED) <= 5
                 ORDER BY CAST(pm.meta_value AS UNSIGNED) ASC LIMIT 10",
                ARRAY_A
            ) ?: [];
        }

        // ── Visitor stats ─────────────────────────────────────────
        $total_visitors    = VisitorDB::total_visitors();
        $today_visitors    = VisitorDB::today_visitors();
        $total_pageviews   = VisitorDB::total_pageviews();
        $today_pageviews   = VisitorDB::today_pageviews();
        $visitor_countries = VisitorDB::top_visitor_countries( 10 );

        $currency     = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';
        $max_orders   = max( 1, max( array_column( $orders_by_country, 'count' ) ?: [1] ) );
        $max_visitors = max( 1, max( array_column( $visitor_countries, 'count' ) ?: [1] ) );

        // Month/year for revenue chart.
        $cur_month = (int) date( 'n' );
        $cur_year  = (int) date( 'Y' );
        $nonce     = wp_create_nonce( 'space_core_admin' );

        // Initial revenue chart data.
        $this->ajax_revenue_chart_data( $cur_month, $cur_year );
        $initial_revenue = $this->get_revenue_data( $cur_month, $cur_year );
        echo $this->view( 'admin/page', [
            'wc_ready'         => $wc_ready,
            'total_orders'     => $total_orders,
            'today_orders'     => $today_orders,
            'processing'       => $processing,
            'total_income'     => $total_income,
            'today_income'     => $today_income,
            'orders_by_country'=> $orders_by_country,
            'orders_by_status' => $orders_by_status,
            'top_products'     => $top_products,
            'recent_orders'    => $recent_orders,
            'low_stock'        => $low_stock,
            'total_visitors'   => $total_visitors,
            'today_visitors'   => $today_visitors,
            'total_pageviews'  => $total_pageviews,
            'today_pageviews'  => $today_pageviews,
            'visitor_countries'=> $visitor_countries,
            'currency'         => $currency,
            'max_orders'       => $max_orders,
            'max_visitors'     => $max_visitors,
            'cur_month'        => $cur_month,
            'cur_year'         => $cur_year,
            'nonce'            => $nonce,
            'initial_revenue'  => $initial_revenue,
        ] );
    }

    private function get_revenue_data( int $month, int $year ): array {
        if ( ! class_exists( 'WooCommerce' ) ) return [ 'labels' => [], 'data' => [] ];

        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore
            "SELECT DATE(p.post_date) as day, SUM(pm.meta_value) as revenue
             FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_order_total'
             WHERE p.post_type = 'shop_order'
               AND p.post_status IN ('wc-completed','wc-processing')
               AND MONTH(p.post_date) = %d AND YEAR(p.post_date) = %d
             GROUP BY DATE(p.post_date) ORDER BY day ASC",
            $month, $year
        ), ARRAY_A );

        $days_in_month = cal_days_in_month( CAL_GREGORIAN, $month, $year );
        $by_day = [];
        foreach ( $rows as $row ) {
            $by_day[ $row['day'] ] = (float) $row['revenue'];
        }
        $labels = [];
        $data   = [];
        for ( $d = 1; $d <= $days_in_month; $d++ ) {
            $date     = sprintf( '%04d-%02d-%02d', $year, $month, $d );
            $labels[] = $d;
            $data[]   = $by_day[ $date ] ?? 0;
        }
        return [ 'labels' => $labels, 'data' => $data ];
    }

    private function ajax_revenue_chart_data( int $month, int $year ): void {
        // Called internally during page render for initial data (not an AJAX response).
    }

    private function stat_card( mixed $value, string $label, string $extra_class = '' ): void {
        echo $this->view( 'admin/partials/stat-card', [
            'value'       => $value,
            'label'       => $label,
            'extra_class' => $extra_class,
        ] );
    }

    private function country_flag( string $code ): string {
        $code = strtoupper( $code );
        if ( strlen( $code ) !== 2 ) return '';
        $flag = '';
        foreach ( str_split( $code ) as $char ) {
            $flag .= mb_chr( ord( $char ) - ord( 'A' ) + 0x1F1E6, 'UTF-8' );
        }
        return $flag;
    }

    private function country_name( string $code ): string {
        if ( class_exists( 'WC_Countries' ) ) {
            $countries = new \WC_Countries();
            return $countries->countries[ strtoupper( $code ) ] ?? $code;
        }
        return $code;
    }

    private function stats_css(): string {
        return '
        .sc-stats-wrap h1 { margin-bottom:4px; }
        .sc-stat-widget {
            background:#fff;
            border:1px solid #ddd;
            border-radius:8px;
            padding:20px;
            box-shadow:0 1px 4px rgba(0,0,0,.06);
        }
        .sc-widget-header {
            display:flex;
            align-items:center;
            gap:10px;
            margin-bottom:16px;
            padding-bottom:12px;
            border-bottom:2px solid #f0f0f0;
        }
        .sc-widget-header h2 { margin:0; font-size:1rem; color:#1d2327; }
        .sc-widget-handle { cursor:grab; color:#bbb; font-size:1.2rem; }
        .sc-widget-handle:active { cursor:grabbing; }
        .sc-stats-grid {
            display:grid;
            grid-template-columns:repeat(auto-fill,minmax(160px,1fr));
            gap:12px;
        }
        .sc-stat-card {
            background:#f9f9f9;
            border:1px solid #ebebeb;
            border-radius:6px;
            padding:16px 12px;
            text-align:center;
        }
        .sc-stat-card .sc-stat-number { font-size:1.6rem; font-weight:700; color:#2271b1; line-height:1.1; }
        .sc-stat-card .sc-stat-label  { font-size:.75rem; color:#888; margin-top:4px; }
        .sc-stat-card.income .sc-stat-number { color:#2e7d32; }
        .sc-country-table { width:100%; border-collapse:collapse; }
        .sc-country-table th,.sc-country-table td { padding:6px 10px; border-bottom:1px solid #f0f0f0; font-size:.875rem; }
        .sc-country-table th { background:#f9f9f9; font-weight:600; }
        .sc-progress { background:#eee; border-radius:4px; height:6px; overflow:hidden; margin-bottom:2px; }
        .sc-progress-bar { height:6px; border-radius:4px; background:#2271b1; }
        .sc-chart-controls select { font-size:.875rem; }
        ';
    }
}
