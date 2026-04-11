<?php

namespace Space\Core\Modules\GuestOrders;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Utilities\OrderUtil;
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
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }
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
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $nonce = wp_create_nonce( 'sc_guest_orders_nonce' );
        $phone = sanitize_text_field( wp_unslash( $_GET['phone'] ?? '' ) );
        $from  = sanitize_text_field( wp_unslash( $_GET['from'] ?? '' ) );
        $to    = sanitize_text_field( wp_unslash( $_GET['to'] ?? '' ) );
        $paged = max( 1, absint( $_GET['paged'] ?? 1 ) );

        [ 'rows' => $rows, 'total' => $total ] = $this->query_grouped( $phone, $from, $to, $paged );
        $rows = array_map( function ( array $row ): array {
            $row['orders_url'] = $this->get_orders_list_url( $row['phone'] );
            return $row;
        }, $rows );
        $total_pages = ceil( $total / self::PER_PAGE );
        $currency    = get_woocommerce_currency_symbol();
        $page_url    = admin_url( 'admin.php?page=sc-guest-orders' );
        echo $this->view( 'admin/page', [
            'nonce'       => $nonce,
            'phone'       => $phone,
            'from'        => $from,
            'to'          => $to,
            'paged'       => $paged,
            'rows'        => $rows,
            'total'       => $total,
            'total_pages' => $total_pages,
            'currency'    => $currency,
            'page_url'    => $page_url,
            'per_page'    => self::PER_PAGE,
        ] );
    }

    /** Returns paginated rows grouped by phone + total count. */
    private function query_grouped( string $phone, string $from, string $to, int $paged, bool $paginate = true ): array {
        $rows = $this->get_grouped_order_rows( $phone, $from, $to );

        usort( $rows, static function ( array $a, array $b ): int {
            $count_comparison = $b['order_count'] <=> $a['order_count'];

            return 0 !== $count_comparison ? $count_comparison : strnatcasecmp( $a['phone'], $b['phone'] );
        } );

        $total = count( $rows );

        if ( $paginate ) {
            $offset = ( max( 1, $paged ) - 1 ) * self::PER_PAGE;
            $rows   = array_slice( $rows, $offset, self::PER_PAGE );
        }

        return [ 'rows' => array_values( $rows ), 'total' => $total ];
    }

    /** Returns all guest-order groups for the current filters using WooCommerce's active order data store. */
    private function get_grouped_order_rows( string $phone, string $from, string $to ): array {
        $groups = [];
        $page   = 1;
        $limit  = 200;

        do {
            $orders = wc_get_orders( array_merge(
                    $this->get_order_query_args( $from, $to ),
                    [
                            'limit' => $limit,
                            'page'  => $page,
                    ]
            ) );

            foreach ( $orders as $order ) {
                if ( ! $order instanceof \WC_Order ) {
                    continue;
                }

                $order_phone = (string) $order->get_billing_phone();
                if ( '' !== $phone && false === stripos( $order_phone, $phone ) ) {
                    continue;
                }

                if ( ! isset( $groups[ $order_phone ] ) ) {
                    $groups[ $order_phone ] = [
                            'name'        => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
                            'phone'       => $order_phone,
                            'email'       => $order->get_billing_email(),
                            'order_count' => 0,
                            'total_spent' => 0.0,
                    ];
                } else {
                    $name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
                    if ( '' === $groups[ $order_phone ]['name'] && '' !== $name ) {
                        $groups[ $order_phone ]['name'] = $name;
                    }

                    $email = $order->get_billing_email();
                    if ( '' === $groups[ $order_phone ]['email'] && '' !== $email ) {
                        $groups[ $order_phone ]['email'] = $email;
                    }
                }

                $groups[ $order_phone ]['order_count'] ++;
                $groups[ $order_phone ]['total_spent'] += (float) $order->get_total();
            }

            $page ++;
        } while ( count( $orders ) === $limit );

        return array_values( $groups );
    }

    /** Returns HPOS-safe order query args shared by the table and export. */
    private function get_order_query_args( string $from, string $to ): array {
        $args = [
                'type'        => 'shop_order',
                'status'      => array_keys( wc_get_order_statuses() ),
                'customer_id' => 0,
                'orderby'     => 'date',
                'order'       => 'DESC',
                'return'      => 'objects',
        ];

        $date_created = $this->get_date_created_query( $from, $to );
        if ( '' !== $date_created ) {
            $args['date_created'] = $date_created;
        }

        return $args;
    }

    /** Builds a WooCommerce order date query while preserving the old inclusive date filters. */
    private function get_date_created_query( string $from, string $to ): string {
        if ( '' !== $from && '' !== $to ) {
            return $from . '...' . $to;
        }

        if ( '' !== $from ) {
            return '>=' . $from;
        }

        if ( '' !== $to ) {
            return '<=' . $to;
        }

        return '';
    }

    /** Returns the order-list URL for the active WooCommerce order storage mode. */
    private function get_orders_list_url( string $phone ): string {
        if ( class_exists( OrderUtil::class ) && OrderUtil::custom_orders_table_usage_is_enabled() ) {
            return add_query_arg(
                    [
                            'page' => 'wc-orders',
                            's'    => $phone,
                    ],
                    admin_url( 'admin.php' )
            );
        }

        return add_query_arg(
                [
                        'post_type' => 'shop_order',
                        's'         => $phone,
                ],
                admin_url( 'edit.php' )
        );
    }

    public function handle_export(): void {
        check_ajax_referer( 'sc_guest_orders_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( 'Unauthorized', 403 );
        }

        $phone = sanitize_text_field( wp_unslash( $_GET['phone'] ?? '' ) );
        $from  = sanitize_text_field( wp_unslash( $_GET['from'] ?? '' ) );
        $to    = sanitize_text_field( wp_unslash( $_GET['to'] ?? '' ) );

        [ 'rows' => $all_rows ] = $this->query_grouped( $phone, $from, $to, 1, false );

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="guest-orders-' . gmdate( 'Y-m-d' ) . '.csv"' );
        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, [ 'Name', 'Phone', 'Email', 'Number of Orders', 'Total Spent' ] );
        foreach ( $all_rows as $r ) {
            fputcsv( $out, [
                    $r['name'],
                    $r['phone'],
                    $r['email'],
                    $r['order_count'],
                    number_format( $r['total_spent'], 3 )
            ] );
        }
        fclose( $out );
        exit;
    }
}
