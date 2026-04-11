<?php

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap sc-stats-wrap">
    <h1><?php esc_html_e( 'Store Stats', 'space-core' ); ?></h1>
    <p class="description"><?php esc_html_e( 'Drag widgets to reorder. Order is saved in your browser.', 'space-core' ); ?></p>

    <div id="sc-stats-widgets" style="display:flex;flex-direction:column;gap:20px;margin-top:16px;">
        <?php if ( $wc_ready ) : ?>
        <div class="sc-stat-widget" data-widget="orders-summary">
            <div class="sc-widget-header">
                <span class="sc-widget-handle dashicons dashicons-move"></span>
                <h2><?php esc_html_e( 'Orders & Revenue', 'space-core' ); ?></h2>
            </div>
            <div class="sc-stats-grid">
                <?php $this->stat_card( number_format_i18n( $total_orders ), __( 'Total Orders', 'space-core' ) ); ?>
                <?php $this->stat_card( number_format_i18n( $today_orders ), __( 'Orders Today', 'space-core' ) ); ?>
                <?php $this->stat_card( number_format_i18n( $processing ), __( 'Processing', 'space-core' ) ); ?>
                <?php $this->stat_card( $currency . number_format( $total_income, 2 ), __( 'Total Revenue', 'space-core' ), 'income' ); ?>
                <?php $this->stat_card( $currency . number_format( $today_income, 2 ), __( 'Revenue Today', 'space-core' ), 'income' ); ?>
                <?php $avg_order = $total_orders > 0 ? $total_income / $total_orders : 0; ?>
                <?php $this->stat_card( $currency . number_format( $avg_order, 2 ), __( 'Avg. Order Value', 'space-core' ) ); ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="sc-stat-widget" data-widget="visitors">
            <div class="sc-widget-header">
                <span class="sc-widget-handle dashicons dashicons-move"></span>
                <h2><?php esc_html_e( 'Visitors', 'space-core' ); ?></h2>
            </div>
            <div class="sc-stats-grid">
                <?php $this->stat_card( number_format_i18n( $total_visitors ), __( 'Total Visitors', 'space-core' ) ); ?>
                <?php $this->stat_card( number_format_i18n( $today_visitors ), __( 'Visitors Today', 'space-core' ) ); ?>
                <?php $this->stat_card( number_format_i18n( $total_pageviews ), __( 'Total Page Views', 'space-core' ) ); ?>
                <?php $this->stat_card( number_format_i18n( $today_pageviews ), __( 'Page Views Today', 'space-core' ) ); ?>
            </div>
        </div>

        <?php if ( $wc_ready ) : ?>
        <div class="sc-stat-widget" data-widget="revenue-chart">
            <div class="sc-widget-header">
                <span class="sc-widget-handle dashicons dashicons-move"></span>
                <h2><?php esc_html_e( 'Revenue Chart', 'space-core' ); ?></h2>
                <div class="sc-chart-controls" style="margin-left:auto;display:flex;gap:8px;align-items:center;">
                    <select id="sc-revenue-month">
                        <?php for ( $m = 1; $m <= 12; $m++ ) : ?>
                            <option value="<?php echo esc_attr( (string) $m ); ?>" <?php selected( $m, $cur_month ); ?>><?php echo esc_html( date_i18n( 'F', mktime( 0, 0, 0, $m, 1 ) ) ); ?></option>
                        <?php endfor; ?>
                    </select>
                    <select id="sc-revenue-year">
                        <?php for ( $y = $cur_year; $y >= $cur_year - 3; $y-- ) : ?>
                            <option value="<?php echo esc_attr( (string) $y ); ?>"><?php echo esc_html( (string) $y ); ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            <div style="position:relative;height:260px;"><canvas id="sc-revenue-chart"></canvas></div>
        </div>

        <div class="sc-stat-widget" data-widget="status-chart">
            <div class="sc-widget-header">
                <span class="sc-widget-handle dashicons dashicons-move"></span>
                <h2><?php esc_html_e( 'Orders by Status', 'space-core' ); ?></h2>
            </div>
            <div style="position:relative;height:260px;display:flex;justify-content:center;"><canvas id="sc-status-chart"></canvas></div>
        </div>
        <?php endif; ?>

        <?php if ( $wc_ready && ! empty( $orders_by_country ) ) : ?>
        <div class="sc-stat-widget" data-widget="orders-country">
            <div class="sc-widget-header">
                <span class="sc-widget-handle dashicons dashicons-move"></span>
                <h2><?php esc_html_e( 'Orders by Country', 'space-core' ); ?></h2>
            </div>
            <table class="sc-country-table">
                <thead><tr><th><?php esc_html_e( 'Country', 'space-core' ); ?></th><th><?php esc_html_e( 'Orders', 'space-core' ); ?></th></tr></thead>
                <tbody>
                    <?php foreach ( $orders_by_country as $row ) : $pct = (int) round( ( $row['count'] / $max_orders ) * 100 ); ?>
                        <tr>
                            <td><?php echo $this->country_flag( $row['country_code'] ); ?> <?php echo esc_html( $this->country_name( $row['country_code'] ) ); ?></td>
                            <td><div class="sc-progress"><div class="sc-progress-bar" style="width:<?php echo esc_attr( (string) $pct ); ?>%"></div></div><span style="font-size:.8rem;color:#666;"><?php echo esc_html( (string) $row['count'] ); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if ( ! empty( $visitor_countries ) ) : ?>
        <div class="sc-stat-widget" data-widget="visitors-country">
            <div class="sc-widget-header">
                <span class="sc-widget-handle dashicons dashicons-move"></span>
                <h2><?php esc_html_e( 'Visitors by Country', 'space-core' ); ?></h2>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start;">
                <table class="sc-country-table">
                    <thead><tr><th><?php esc_html_e( 'Country', 'space-core' ); ?></th><th><?php esc_html_e( 'Visitors', 'space-core' ); ?></th></tr></thead>
                    <tbody>
                        <?php foreach ( $visitor_countries as $row ) : $pct = (int) round( ( $row['count'] / $max_visitors ) * 100 ); ?>
                            <tr>
                                <td><?php echo $this->country_flag( $row['country_code'] ); ?> <?php echo esc_html( $row['country_name'] ?: $row['country_code'] ); ?></td>
                                <td><div class="sc-progress"><div class="sc-progress-bar" style="width:<?php echo esc_attr( (string) $pct ); ?>%;background:#7f54b3;"></div></div><span style="font-size:.8rem;color:#666;"><?php echo esc_html( (string) $row['count'] ); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="position:relative;height:220px;"><canvas id="sc-country-chart"></canvas></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ( $wc_ready && ! empty( $top_products ) ) : ?>
        <div class="sc-stat-widget" data-widget="top-products">
            <div class="sc-widget-header">
                <span class="sc-widget-handle dashicons dashicons-move"></span>
                <h2><?php esc_html_e( 'Top 10 Best-Selling Products', 'space-core' ); ?></h2>
            </div>
            <div class="sc-widget-body">
                <table class="widefat striped" style="margin:0;">
                    <thead><tr><th><?php esc_html_e( 'Product', 'space-core' ); ?></th><th><?php esc_html_e( 'SKU', 'space-core' ); ?></th><th style="text-align:right;"><?php esc_html_e( 'Qty Sold', 'space-core' ); ?></th></tr></thead>
                    <tbody>
                        <?php foreach ( $top_products as $tp ) : $product = wc_get_product( (int) $tp['product_id'] ); if ( ! $product ) { continue; } ?>
                            <tr>
                                <td><a href="<?php echo esc_url( get_edit_post_link( $product->get_id() ) ); ?>"><?php echo esc_html( $product->get_name() ); ?></a></td>
                                <td><?php echo esc_html( $product->get_sku() ); ?></td>
                                <td style="text-align:right;font-weight:600;"><?php echo esc_html( number_format_i18n( (int) $tp['total_qty'] ) ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if ( $wc_ready && ! empty( $recent_orders ) ) : ?>
        <div class="sc-stat-widget" data-widget="recent-orders">
            <div class="sc-widget-header">
                <span class="sc-widget-handle dashicons dashicons-move"></span>
                <h2><?php esc_html_e( 'Recent Orders', 'space-core' ); ?></h2>
            </div>
            <div class="sc-widget-body">
                <table class="widefat striped" style="margin:0;">
                    <thead><tr><th><?php esc_html_e( 'Order', 'space-core' ); ?></th><th><?php esc_html_e( 'Date', 'space-core' ); ?></th><th><?php esc_html_e( 'Customer', 'space-core' ); ?></th><th><?php esc_html_e( 'Status', 'space-core' ); ?></th><th style="text-align:right;"><?php esc_html_e( 'Total', 'space-core' ); ?></th></tr></thead>
                    <tbody>
                        <?php foreach ( $recent_orders as $ro ) :
                            $status_label = ucfirst( str_replace( 'wc-', '', $ro['post_status'] ) );
                            $status_colors = [ 'processing' => '#2271b1', 'completed' => '#2e7d32', 'on-hold' => '#e65100', 'cancelled' => '#c62828', 'refunded' => '#6a1b9a', 'pending' => '#9e9e9e' ];
                            $sc = $status_colors[ str_replace( 'wc-', '', $ro['post_status'] ) ] ?? '#666';
                            $edit_url = admin_url( 'post.php?post=' . $ro['ID'] . '&action=edit' );
                            ?>
                            <tr>
                                <td><a href="<?php echo esc_url( $edit_url ); ?>">#<?php echo esc_html( (string) $ro['ID'] ); ?></a></td>
                                <td><?php echo esc_html( wp_date( 'd/m/Y', strtotime( $ro['post_date'] ) ) ); ?></td>
                                <td><?php echo esc_html( trim( ( $ro['fname'] ?? '' ) . ' ' . ( $ro['lname'] ?? '' ) ) ); ?></td>
                                <td><span style="display:inline-block;padding:2px 8px;border-radius:3px;background:<?php echo esc_attr( $sc ); ?>;color:#fff;font-size:11px;"><?php echo esc_html( $status_label ); ?></span></td>
                                <td style="text-align:right;"><?php echo wp_kses_post( wc_price( (float) ( $ro['total'] ?? 0 ) ) ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if ( $wc_ready && ! empty( $low_stock ) ) : ?>
        <div class="sc-stat-widget" data-widget="low-stock">
            <div class="sc-widget-header">
                <span class="sc-widget-handle dashicons dashicons-move"></span>
                <h2><?php esc_html_e( 'Low Stock Products', 'space-core' ); ?></h2>
            </div>
            <div class="sc-widget-body">
                <table class="widefat striped" style="margin:0;">
                    <thead><tr><th><?php esc_html_e( 'Product', 'space-core' ); ?></th><th style="text-align:right;"><?php esc_html_e( 'Stock', 'space-core' ); ?></th></tr></thead>
                    <tbody>
                        <?php foreach ( $low_stock as $ls ) : ?>
                            <tr>
                                <td><a href="<?php echo esc_url( get_edit_post_link( (int) $ls['ID'] ) ); ?>"><?php echo esc_html( $ls['post_title'] ); ?></a></td>
                                <td style="text-align:right;font-weight:600;color:<?php echo (int) $ls['stock'] <= 2 ? '#c62828' : '#e65100'; ?>;"><?php echo esc_html( (string) $ls['stock'] ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
jQuery(function($){
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

    var revenueCtx = document.getElementById('sc-revenue-chart');
    if (revenueCtx) {
        var revenueChart = new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: <?php echo wp_json_encode( $initial_revenue['labels'] ); ?>,
                datasets: [{
                    label: '<?php echo esc_js( __( 'Revenue', 'space-core' ) ); ?>',
                    data:  <?php echo wp_json_encode( $initial_revenue['data'] ); ?>,
                    borderColor: '#2271b1',
                    backgroundColor: 'rgba(34,113,177,.08)',
                    tension: 0.3,
                    fill: true,
                }]
            },
            options: { responsive:true, maintainAspectRatio:false, plugins: { legend: { display:false } }, scales: { y: { beginAtZero:true } } }
        });

        function loadRevenueChart() {
            $.post(spaceCore.ajaxUrl, {
                action: 'sc_stats_revenue_chart',
                nonce:  spaceCore.nonce,
                month:  $('#sc-revenue-month').val(),
                year:   $('#sc-revenue-year').val(),
            }, function(res){
                if (!res.success) return;
                revenueChart.data.labels = res.data.labels;
                revenueChart.data.datasets[0].data = res.data.data;
                revenueChart.update();
            });
        }

        $('#sc-revenue-month,#sc-revenue-year').on('change', loadRevenueChart);
    }

    var statusCtx = document.getElementById('sc-status-chart');
    if (statusCtx) {
        new Chart(statusCtx, {
            type: 'pie',
            data: {
                labels: <?php echo wp_json_encode( array_keys( $orders_by_status ) ); ?>,
                datasets: [{ data: <?php echo wp_json_encode( array_values( $orders_by_status ) ); ?>, backgroundColor: ['#2271b1','#2e7d32','#c62828','#e65100','#6a1b9a','#00695c'] }]
            },
            options: { responsive:true, maintainAspectRatio:false, plugins: { legend: { position:'right' } } }
        });
    }

    var countryCtx = document.getElementById('sc-country-chart');
    if (countryCtx) {
        new Chart(countryCtx, {
            type: 'bar',
            data: {
                labels: <?php echo wp_json_encode( array_map( fn( $r ) => $r['country_name'] ?: $r['country_code'], $visitor_countries ) ); ?>,
                datasets: [{ label: '<?php echo esc_js( __( 'Visitors', 'space-core' ) ); ?>', data: <?php echo wp_json_encode( array_column( $visitor_countries, 'count' ) ); ?>, backgroundColor: '#7f54b3' }]
            },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display:false } }, scales: { x: { beginAtZero:true } } }
        });
    }
});
</script>
