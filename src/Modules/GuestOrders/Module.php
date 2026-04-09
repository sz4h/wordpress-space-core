<?php

namespace Space\Core\Modules\GuestOrders;

defined( 'ABSPATH' ) || exit;

use Space\Core\Abstracts\AbstractModule;

/**
 * Guest Orders — paginated table of guest orders grouped by phone.
 * Search by billing_phone, export CSV with phone + email.
 * "View" links to WC orders filtered by phone.
 */
class Module extends AbstractModule {

    private const PER_PAGE = 20;

    public function get_label(): string {
        return __( 'Guest Orders', 'space-core' );
    }

    public function get_description(): string {
        return __( 'View and export WooCommerce guest orders grouped by billing phone number.', 'space-core' );
    }

    public function boot(): void {
        if ( ! class_exists( 'WooCommerce' ) ) return;
        add_action( 'admin_menu', [ $this, 'register_page' ] );
        add_action( 'wp_ajax_sc_guest_orders_export', [ $this, 'handle_export' ] );
    }

    public function register_page(): void {
        add_submenu_page(
            'woocommerce',
            __( 'Guest Orders', 'space-core' ),
            __( 'Guest Orders', 'space-core' ),
            'manage_woocommerce',
            'sc-guest-orders',
            [ $this, 'render_page' ]
        );
    }

    public function render_page(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) return;

        $nonce  = wp_create_nonce( 'sc_guest_orders_nonce' );
        $phone  = sanitize_text_field( wp_unslash( $_GET['phone'] ?? '' ) );
        $from   = sanitize_text_field( wp_unslash( $_GET['from']  ?? '' ) );
        $to     = sanitize_text_field( wp_unslash( $_GET['to']    ?? '' ) );
        $paged  = max( 1, absint( $_GET['paged'] ?? 1 ) );

        [ 'rows' => $rows, 'total' => $total ] = $this->query_grouped( $phone, $from, $to, $paged );
        $total_pages = ceil( $total / self::PER_PAGE );
        $currency    = get_woocommerce_currency_symbol();
        $page_url    = admin_url( 'admin.php?page=sc-guest-orders' );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Guest Orders', 'space-core' ); ?></h1>

            <!-- Search form -->
            <form method="get" action="<?php echo esc_url( $page_url ); ?>"
                  style="background:#fff;padding:16px 20px;border:1px solid #ddd;border-radius:6px;margin-bottom:20px;display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
                <input type="hidden" name="page" value="sc-guest-orders">
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;"><?php esc_html_e( 'Phone', 'space-core' ); ?></label>
                    <input type="text" name="phone" value="<?php echo esc_attr( $phone ); ?>" class="regular-text" placeholder="+96512345678">
                </div>
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;"><?php esc_html_e( 'From', 'space-core' ); ?></label>
                    <input type="date" name="from" value="<?php echo esc_attr( $from ); ?>">
                </div>
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:4px;"><?php esc_html_e( 'To', 'space-core' ); ?></label>
                    <input type="date" name="to" value="<?php echo esc_attr( $to ); ?>">
                </div>
                <div>
                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'space-core' ); ?></button>
                    <a href="<?php echo esc_url( $page_url ); ?>" class="button" style="margin-left:4px;"><?php esc_html_e( 'Reset', 'space-core' ); ?></a>
                    <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'sc-guest-orders', 'action' => 'sc_guest_orders_export', 'nonce' => $nonce, 'phone' => $phone, 'from' => $from, 'to' => $to ], admin_url( 'admin-ajax.php' ) ) ); ?>"
                       class="button" style="margin-left:4px;"><?php esc_html_e( 'Export CSV', 'space-core' ); ?></a>
                </div>
            </form>

            <!-- Table -->
            <?php if ( empty( $rows ) ) : ?>
                <p><?php esc_html_e( 'No guest orders found.', 'space-core' ); ?></p>
            <?php else : ?>
                <p style="color:#666;font-size:.875rem;">
                    <?php printf( esc_html__( 'Showing %d–%d of %d customers', 'space-core' ),
                        ( $paged - 1 ) * self::PER_PAGE + 1,
                        min( $paged * self::PER_PAGE, $total ),
                        $total
                    ); ?>
                </p>
                <table class="widefat striped" style="margin-bottom:16px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Name', 'space-core' ); ?></th>
                            <th><?php esc_html_e( 'Phone', 'space-core' ); ?></th>
                            <th><?php esc_html_e( 'Email', 'space-core' ); ?></th>
                            <th style="text-align:center;"><?php esc_html_e( '# Orders', 'space-core' ); ?></th>
                            <th style="text-align:right;"><?php esc_html_e( 'Total Spent', 'space-core' ); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $rows as $row ) :
                            $orders_url = admin_url( 'edit.php?post_type=shop_order&s=' . urlencode( $row['phone'] ) );
                            ?>
                            <tr>
                                <td><?php echo esc_html( $row['name'] ); ?></td>
                                <td><?php echo esc_html( $row['phone'] ); ?></td>
                                <td><?php echo esc_html( $row['email'] ); ?></td>
                                <td style="text-align:center;"><strong><?php echo esc_html( $row['order_count'] ); ?></strong></td>
                                <td style="text-align:right;"><?php echo esc_html( $currency . number_format( $row['total_spent'], 2 ) ); ?></td>
                                <td>
                                    <a href="<?php echo esc_url( $orders_url ); ?>" class="button button-small" target="_blank">
                                        <?php esc_html_e( 'View Orders', 'space-core' ); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ( $total_pages > 1 ) : ?>
                <div style="display:flex;gap:4px;align-items:center;">
                    <?php for ( $p = 1; $p <= $total_pages; $p++ ) :
                        $url = add_query_arg( [ 'page' => 'sc-guest-orders', 'paged' => $p, 'phone' => $phone, 'from' => $from, 'to' => $to ], $page_url );
                        ?>
                        <a href="<?php echo esc_url( $url ); ?>"
                           class="button<?php echo $p === $paged ? ' button-primary' : ''; ?>"
                           style="min-width:32px;text-align:center;">
                            <?php echo $p; ?>
                        </a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /** Returns paginated rows grouped by phone + total count. */
    private function query_grouped( string $phone, string $from, string $to, int $paged ): array {
        global $wpdb;

        $where = [ "p.post_type = 'shop_order'", "p.post_status != 'trash'", "p.post_status != 'wc-checkout-draft'" ];
        $args  = [];

        // Guest only: customer_id = 0 stored in post meta _customer_user.
        $where[] = "NOT EXISTS (
            SELECT 1 FROM {$wpdb->postmeta} cu
            WHERE cu.post_id = p.ID AND cu.meta_key = '_customer_user' AND cu.meta_value > 0
        )";

        if ( $phone ) {
            $where[] = "EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} ph
                WHERE ph.post_id = p.ID AND ph.meta_key = '_billing_phone'
                AND ph.meta_value LIKE %s
            )";
            $args[]  = '%' . $wpdb->esc_like( $phone ) . '%';
        }
        if ( $from ) {
            $where[] = 'p.post_date >= %s';
            $args[]  = $from . ' 00:00:00';
        }
        if ( $to ) {
            $where[] = 'p.post_date <= %s';
            $args[]  = $to . ' 23:59:59';
        }

        $where_sql = implode( ' AND ', $where );
        $offset    = ( $paged - 1 ) * self::PER_PAGE;

        // Count distinct phones.
        $count_sql = "
            SELECT COUNT(DISTINCT COALESCE(pm_phone.meta_value,''))
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_phone ON pm_phone.post_id = p.ID AND pm_phone.meta_key = '_billing_phone'
            WHERE {$where_sql}
        ";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $total = (int) ( empty( $args ) ? $wpdb->get_var( $count_sql ) : $wpdb->get_var( $wpdb->prepare( $count_sql, ...$args ) ) );

        // Grouped rows.
        $rows_sql = "
            SELECT
                COALESCE(MAX(pm_name_f.meta_value),'') as first_name,
                COALESCE(MAX(pm_name_l.meta_value),'') as last_name,
                COALESCE(pm_phone.meta_value,'')        as phone,
                COALESCE(MAX(pm_email.meta_value),'')   as email,
                COUNT(p.ID)                             as order_count,
                SUM(COALESCE(pm_total.meta_value,0))    as total_spent
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_phone  ON pm_phone.post_id  = p.ID AND pm_phone.meta_key  = '_billing_phone'
            LEFT JOIN {$wpdb->postmeta} pm_email  ON pm_email.post_id  = p.ID AND pm_email.meta_key  = '_billing_email'
            LEFT JOIN {$wpdb->postmeta} pm_name_f ON pm_name_f.post_id = p.ID AND pm_name_f.meta_key = '_billing_first_name'
            LEFT JOIN {$wpdb->postmeta} pm_name_l ON pm_name_l.post_id = p.ID AND pm_name_l.meta_key = '_billing_last_name'
            LEFT JOIN {$wpdb->postmeta} pm_total  ON pm_total.post_id  = p.ID AND pm_total.meta_key  = '_order_total'
            WHERE {$where_sql}
            GROUP BY COALESCE(pm_phone.meta_value,'')
            ORDER BY order_count DESC
            LIMIT %d OFFSET %d
        ";
        $all_args = array_merge( $args, $args, [ self::PER_PAGE, $offset ] );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $db_rows = $wpdb->get_results( $wpdb->prepare( $rows_sql, ...$all_args ), ARRAY_A ) ?: [];

        $rows = array_map( fn( $r ) => [
            'name'        => trim( $r['first_name'] . ' ' . $r['last_name'] ),
            'phone'       => $r['phone'],
            'email'       => $r['email'],
            'order_count' => (int) $r['order_count'],
            'total_spent' => (float) $r['total_spent'],
        ], $db_rows );

        return [ 'rows' => $rows, 'total' => $total ];
    }

    public function handle_export(): void {
        check_ajax_referer( 'sc_guest_orders_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'Unauthorized', 403 );

        $phone = sanitize_text_field( wp_unslash( $_GET['phone'] ?? '' ) );
        $from  = sanitize_text_field( wp_unslash( $_GET['from']  ?? '' ) );
        $to    = sanitize_text_field( wp_unslash( $_GET['to']    ?? '' ) );

        [ 'rows' => $rows ] = $this->query_grouped( $phone, $from, $to, 1 );
        // For export get all — re-run without pagination.
        [ 'total' => $total ] = $this->query_grouped( $phone, $from, $to, 1 );
        $all_pages = ceil( $total / self::PER_PAGE );
        $all_rows  = $rows;
        for ( $p = 2; $p <= $all_pages; $p++ ) {
            [ 'rows' => $page_rows ] = $this->query_grouped( $phone, $from, $to, $p );
            $all_rows = array_merge( $all_rows, $page_rows );
        }

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="guest-orders-' . gmdate( 'Y-m-d' ) . '.csv"' );
        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, [ 'Name', 'Phone', 'Email', 'Number of Orders', 'Total Spent' ] );
        foreach ( $all_rows as $r ) {
            fputcsv( $out, [ $r['name'], $r['phone'], $r['email'], $r['order_count'], number_format( $r['total_spent'], 3 ) ] );
        }
        fclose( $out );
        exit;
    }
}
