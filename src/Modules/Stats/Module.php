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
        wp_enqueue_script(
            'sc-stats',
            SPACE_CORE_URL . 'assets/js/admin.js',
            [ 'jquery', 'jquery-ui-sortable', 'sc-chartjs' ],
            SPACE_CORE_VERSION,
            true
        );
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
        ?>
        <div class="wrap sc-stats-wrap">
            <h1><?php esc_html_e( 'Store Stats', 'space-core' ); ?></h1>
            <p class="description"><?php esc_html_e( 'Drag widgets to reorder. Order is saved in your browser.', 'space-core' ); ?></p>

            <div id="sc-stats-widgets" style="display:flex;flex-direction:column;gap:20px;margin-top:16px;">

                <!-- Widget: Order Summary -->
                <?php if ( $wc_ready ) : ?>
                <div class="sc-stat-widget" data-widget="orders-summary">
                    <div class="sc-widget-header">
                        <span class="sc-widget-handle dashicons dashicons-move"></span>
                        <h2><?php esc_html_e( 'Orders & Revenue', 'space-core' ); ?></h2>
                    </div>
                    <div class="sc-stats-grid">
                        <?php $this->stat_card( number_format_i18n( $total_orders ),   __( 'Total Orders', 'space-core' ) ); ?>
                        <?php $this->stat_card( number_format_i18n( $today_orders ),   __( 'Orders Today', 'space-core' ) ); ?>
                        <?php $this->stat_card( number_format_i18n( $processing ),     __( 'Processing', 'space-core' ) ); ?>
                        <?php $this->stat_card( $currency . number_format( $total_income, 2 ), __( 'Total Revenue', 'space-core' ), 'income' ); ?>
                        <?php $this->stat_card( $currency . number_format( $today_income, 2 ), __( 'Revenue Today', 'space-core' ), 'income' ); ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Widget: Visitors -->
                <div class="sc-stat-widget" data-widget="visitors">
                    <div class="sc-widget-header">
                        <span class="sc-widget-handle dashicons dashicons-move"></span>
                        <h2><?php esc_html_e( 'Visitors', 'space-core' ); ?></h2>
                    </div>
                    <div class="sc-stats-grid">
                        <?php $this->stat_card( number_format_i18n( $total_visitors ),  __( 'Total Visitors', 'space-core' ) ); ?>
                        <?php $this->stat_card( number_format_i18n( $today_visitors ),  __( 'Visitors Today', 'space-core' ) ); ?>
                        <?php $this->stat_card( number_format_i18n( $total_pageviews ), __( 'Total Page Views', 'space-core' ) ); ?>
                        <?php $this->stat_card( number_format_i18n( $today_pageviews ), __( 'Page Views Today', 'space-core' ) ); ?>
                    </div>
                </div>

                <!-- Widget: Revenue Line Chart -->
                <?php if ( $wc_ready ) : ?>
                <div class="sc-stat-widget" data-widget="revenue-chart">
                    <div class="sc-widget-header">
                        <span class="sc-widget-handle dashicons dashicons-move"></span>
                        <h2><?php esc_html_e( 'Revenue Chart', 'space-core' ); ?></h2>
                        <div class="sc-chart-controls" style="margin-left:auto;display:flex;gap:8px;align-items:center;">
                            <select id="sc-revenue-month">
                                <?php for ( $m = 1; $m <= 12; $m++ ) : ?>
                                    <option value="<?php echo $m; ?>" <?php selected( $m, $cur_month ); ?>>
                                        <?php echo esc_html( date_i18n( 'F', mktime( 0, 0, 0, $m, 1 ) ) ); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <select id="sc-revenue-year">
                                <?php for ( $y = $cur_year; $y >= $cur_year - 3; $y-- ) : ?>
                                    <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div style="position:relative;height:260px;">
                        <canvas id="sc-revenue-chart"></canvas>
                    </div>
                </div>

                <!-- Widget: Orders by Status Pie -->
                <div class="sc-stat-widget" data-widget="status-chart">
                    <div class="sc-widget-header">
                        <span class="sc-widget-handle dashicons dashicons-move"></span>
                        <h2><?php esc_html_e( 'Orders by Status', 'space-core' ); ?></h2>
                    </div>
                    <div style="position:relative;height:260px;display:flex;justify-content:center;">
                        <canvas id="sc-status-chart"></canvas>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Widget: Orders by Country -->
                <?php if ( $wc_ready && ! empty( $orders_by_country ) ) : ?>
                <div class="sc-stat-widget" data-widget="orders-country">
                    <div class="sc-widget-header">
                        <span class="sc-widget-handle dashicons dashicons-move"></span>
                        <h2><?php esc_html_e( 'Orders by Country', 'space-core' ); ?></h2>
                    </div>
                    <table class="sc-country-table">
                        <thead><tr>
                            <th><?php esc_html_e( 'Country', 'space-core' ); ?></th>
                            <th><?php esc_html_e( 'Orders', 'space-core' ); ?></th>
                        </tr></thead>
                        <tbody>
                            <?php foreach ( $orders_by_country as $row ) :
                                $pct  = (int) round( ( $row['count'] / $max_orders ) * 100 );
                                $flag = $this->country_flag( $row['country_code'] );
                                $name = $this->country_name( $row['country_code'] );
                                ?>
                                <tr>
                                    <td><?php echo $flag; ?> <?php echo esc_html( $name ); ?></td>
                                    <td>
                                        <div class="sc-progress"><div class="sc-progress-bar" style="width:<?php echo $pct; ?>%"></div></div>
                                        <span style="font-size:.8rem;color:#666;"><?php echo esc_html( $row['count'] ); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <!-- Widget: Visitors by Country + Bar Chart -->
                <?php if ( ! empty( $visitor_countries ) ) : ?>
                <div class="sc-stat-widget" data-widget="visitors-country">
                    <div class="sc-widget-header">
                        <span class="sc-widget-handle dashicons dashicons-move"></span>
                        <h2><?php esc_html_e( 'Visitors by Country', 'space-core' ); ?></h2>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start;">
                        <table class="sc-country-table">
                            <thead><tr>
                                <th><?php esc_html_e( 'Country', 'space-core' ); ?></th>
                                <th><?php esc_html_e( 'Visitors', 'space-core' ); ?></th>
                            </tr></thead>
                            <tbody>
                                <?php foreach ( $visitor_countries as $row ) :
                                    $pct  = (int) round( ( $row['count'] / $max_visitors ) * 100 );
                                    $flag = $this->country_flag( $row['country_code'] );
                                    ?>
                                    <tr>
                                        <td><?php echo $flag; ?> <?php echo esc_html( $row['country_name'] ?: $row['country_code'] ); ?></td>
                                        <td>
                                            <div class="sc-progress"><div class="sc-progress-bar" style="width:<?php echo $pct; ?>%;background:#7f54b3;"></div></div>
                                            <span style="font-size:.8rem;color:#666;"><?php echo esc_html( $row['count'] ); ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div style="position:relative;height:220px;">
                            <canvas id="sc-country-chart"></canvas>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div><!-- #sc-stats-widgets -->
        </div>

        <script>
        (function($){
            // ── Sortable widgets ──────────────────────────────────
            var $widgets = $('#sc-stats-widgets');
            var STORAGE_KEY = 'sc_stats_order';

            function restoreOrder() {
                try {
                    var order = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
                    if (!order.length) return;
                    order.forEach(function(id){
                        var $el = $widgets.find('[data-widget="'+id+'"]');
                        if ($el.length) $widgets.append($el);
                    });
                } catch(e){}
            }

            function saveOrder() {
                var order = [];
                $widgets.children('[data-widget]').each(function(){ order.push($(this).data('widget')); });
                try { localStorage.setItem(STORAGE_KEY, JSON.stringify(order)); } catch(e){}
            }

            restoreOrder();
            $widgets.sortable({ handle: '.sc-widget-handle', stop: saveOrder });

            // ── Revenue chart ─────────────────────────────────────
            var revenueCtx = document.getElementById('sc-revenue-chart');
            if (revenueCtx) {
                var revenueChart = new Chart(revenueCtx, {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode( $initial_revenue['labels'] ); ?>,
                        datasets: [{
                            label: '<?php echo esc_js( __( 'Revenue', 'space-core' ) ); ?>',
                            data:  <?php echo json_encode( $initial_revenue['data'] ); ?>,
                            borderColor: '#2271b1',
                            backgroundColor: 'rgba(34,113,177,.08)',
                            tension: 0.3,
                            fill: true,
                        }]
                    },
                    options: { responsive:true, maintainAspectRatio:false,
                        plugins: { legend: { display:false } },
                        scales: { y: { beginAtZero:true } }
                    }
                });

                function loadRevenueChart() {
                    $.post(spaceCore.ajaxUrl, {
                        action: 'sc_stats_revenue_chart',
                        nonce:  spaceCore.nonce,
                        month:  $('#sc-revenue-month').val(),
                        year:   $('#sc-revenue-year').val(),
                    }, function(res){
                        if (!res.success) return;
                        revenueChart.data.labels   = res.data.labels;
                        revenueChart.data.datasets[0].data = res.data.data;
                        revenueChart.update();
                    });
                }

                $('#sc-revenue-month,#sc-revenue-year').on('change', loadRevenueChart);
            }

            // ── Status pie chart ──────────────────────────────────
            var statusCtx = document.getElementById('sc-status-chart');
            if (statusCtx) {
                new Chart(statusCtx, {
                    type: 'pie',
                    data: {
                        labels: <?php echo json_encode( array_keys( $orders_by_status ) ); ?>,
                        datasets: [{
                            data: <?php echo json_encode( array_values( $orders_by_status ) ); ?>,
                            backgroundColor: ['#2271b1','#2e7d32','#c62828','#e65100','#6a1b9a','#00695c'],
                        }]
                    },
                    options: { responsive:true, maintainAspectRatio:false,
                        plugins: { legend: { position:'right' } }
                    }
                });
            }

            // ── Country bar chart ─────────────────────────────────
            var countryCtx = document.getElementById('sc-country-chart');
            if (countryCtx) {
                new Chart(countryCtx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode( array_map( fn($r) => $r['country_name'] ?: $r['country_code'], $visitor_countries ) ); ?>,
                        datasets: [{
                            label: '<?php echo esc_js( __( 'Visitors', 'space-core' ) ); ?>',
                            data:  <?php echo json_encode( array_column( $visitor_countries, 'count' ) ); ?>,
                            backgroundColor: '#7f54b3',
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display:false } },
                        scales: { x: { beginAtZero:true } }
                    }
                });
            }

        }(jQuery));
        </script>
        <?php
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
        echo '<div class="sc-stat-card ' . esc_attr( $extra_class ) . '">';
        echo '<div class="sc-stat-number">' . esc_html( (string) $value ) . '</div>';
        echo '<div class="sc-stat-label">' . esc_html( $label ) . '</div>';
        echo '</div>';
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
