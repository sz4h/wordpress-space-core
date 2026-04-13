<?php

namespace Space\Core\Modules\PrintOrders;

defined( 'ABSPATH' ) || exit;

use Space\Core\Abstracts\AbstractModule;
use Space\Core\Modules\MultiCurrency\CurrencyDB;
use Space\Core\Modules\MultiCurrency\CurrencyPosition;
use WC_Order;

/**
 * Print Orders — per-order and bulk print for A4 and Epson thermal (80 mm).
 *
 * Adds:
 *  - "Print A4" / "Print Thermal" actions to the order list row actions.
 *  - A "Print Selected" bulk action.
 *  - A print button on the single order edit screen.
 */
class Module extends AbstractModule {

    private const PRINT_ACTION = 'sc_print_orders';

    public function get_label(): string {
        return __( 'Print Orders', 'space-core' );
    }

    public function get_description(): string {
        return __( 'Print orders as A4 or 80mm thermal receipt, single or in bulk.', 'space-core' );
    }

    public function boot(): void {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        // Row actions on order list.
        add_filter( 'woocommerce_admin_order_actions', [ $this, 'add_order_actions' ], 10, 2 );
        add_action( 'admin_head', [ $this, 'order_action_styles' ] );

        // Single order page button.
        add_action( 'woocommerce_order_actions', [ $this, 'add_single_order_action' ] );
        add_action( 'woocommerce_order_action_sc_print_a4', [ $this, 'handle_print_a4' ] );
        add_action( 'woocommerce_order_action_sc_print_thermal', [ $this, 'handle_print_thermal' ] );

        // Bulk actions on order list (legacy + HPOS).
        add_filter( 'bulk_actions-edit-shop_order', [ $this, 'add_bulk_actions' ] );
        add_filter( 'handle_bulk_actions-edit-shop_order', [ $this, 'handle_bulk_actions' ], 10, 3 );
        add_filter( 'bulk_actions-woocommerce_page_wc-orders', [ $this, 'add_bulk_actions' ] );
        add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', [ $this, 'handle_bulk_actions' ], 10, 3 );

        add_action( 'admin_post_' . self::PRINT_ACTION, [ $this, 'render_print_page' ] );
        add_action( 'admin_init', [ $this, 'redirect_legacy_print_page' ] );

        add_action( 'wp_ajax_sc_print_orders', [ $this, 'ajax_print' ] );
        add_action( 'wp_ajax_sc_save_print_settings', [ $this, 'ajax_save_settings' ] );
    }

    public function redirect_legacy_print_page(): void {
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( $page !== 'sc-print-order' ) {
            return;
        }

        wp_safe_redirect( $this->print_url( $this->request_order_ids(), $this->request_format() ) );
        exit;
    }

    private function print_url( array $ids, string $format = 'a4' ): string {
        $ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );

        return add_query_arg(
                [
                        'action' => self::PRINT_ACTION,
                        'ids'    => implode( ',', $ids ),
                        'format' => $this->normalize_format( $format ),
                ],
                admin_url( 'admin-post.php' )
        );
    }

    private function normalize_format( string $format ): string {
        return 'thermal' === sanitize_key( $format ) ? 'thermal' : 'a4';
    }

    private function request_order_ids(): array {
        $raw = [];

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
        if ( isset( $_REQUEST['ids'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
            $raw = wp_unslash( $_REQUEST['ids'] );
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
        } elseif ( isset( $_REQUEST['id'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
            $raw = wp_unslash( $_REQUEST['id'] );
        }

        if ( is_array( $raw ) ) {
            $ids = $raw;
        } else {
            $ids = explode( ',', (string) $raw );
        }

        return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
    }

    private function request_format(): string {
        $format = isset( $_REQUEST['format'] ) ? wp_unslash( $_REQUEST['format'] ) : 'a4'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
        if ( is_array( $format ) ) {
            $format = reset( $format );
        }

        return $this->normalize_format( sanitize_key( (string) $format ) );
    }

    public function add_order_actions( array $actions, WC_Order $order ): array {
        $actions['sc_print_a4']      = [
                'url'    => esc_url( $this->print_url( [ $order->get_id() ], 'a4' ) ),
                'name'   => __( 'Print A4', 'space-core' ),
                'action' => 'sc_print_a4',
                'target' => '_blank',
        ];
        $actions['sc_print_thermal'] = [
                'url'    => esc_url( $this->print_url( [ $order->get_id() ], 'thermal' ) ),
                'name'   => __( 'Print 80mm', 'space-core' ),
                'action' => 'sc_print_thermal',
                'target' => '_blank',
        ];

        return $actions;
    }

    public function order_action_styles(): void {
        $screen = get_current_screen();
        if ( ! $screen || ! in_array( $screen->id, [ 'edit-shop_order', 'woocommerce_page_wc-orders' ], true ) ) {
            return;
        }
        echo '<style>
        .wc-action-button-sc_print_a4::after,
        .wc-action-button-sc_print_thermal::after {
            content: "\f193" !important;
            font-family: dashicons !important;
        }
        </style>';
    }

    public function add_single_order_action( array $actions ): array {
        $actions['sc_print_a4']      = __( 'Print — A4', 'space-core' );
        $actions['sc_print_thermal'] = __( 'Print — Thermal', 'space-core' );

        return $actions;
    }

    public function handle_print_a4( WC_Order $order ): void {
        wp_safe_redirect( $this->print_url( [ $order->get_id() ], 'a4' ) );
        exit;
    }

    public function handle_print_thermal( WC_Order $order ): void {
        wp_safe_redirect( $this->print_url( [ $order->get_id() ], 'thermal' ) );
        exit;
    }

    public function add_bulk_actions( array $actions ): array {
        $actions['sc_bulk_print_a4']      = __( 'Print A4 (selected)', 'space-core' );
        $actions['sc_bulk_print_thermal'] = __( 'Print Thermal (selected)', 'space-core' );

        return $actions;
    }

    public function handle_bulk_actions( string $redirect, string $action, array $ids ): string {
        $format = null;
        if ( 'sc_bulk_print_a4' === $action ) {
            $format = 'a4';
        }
        if ( 'sc_bulk_print_thermal' === $action ) {
            $format = 'thermal';
        }
        if ( ! $format ) {
            return $redirect;
        }

        return $this->print_url( $ids, $format );
    }

    public function ajax_print(): void {
        check_ajax_referer( 'space_core_admin', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [], 403 );
        }
        wp_send_json_success( [ 'url' => $this->print_url( $this->request_order_ids(), $this->request_format() ) ] );
    }

    public function render_print_page(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( 'Unauthorized' );
        }

        $format = $this->request_format();
        $ids    = $this->request_order_ids();

        if ( empty( $ids ) ) {
            wp_die( esc_html__( 'No orders selected.', 'space-core' ) );
        }

        $is_thermal = 'thermal' === $format;
        $orders     = array_filter( array_map( 'wc_get_order', $ids ) );
        $orders     = array_values( $orders );

        if ( empty( $orders ) ) {
            wp_die( esc_html__( 'No valid orders selected.', 'space-core' ) );
        }

        wp_enqueue_style(
                'space-core-admin-print',
                SPACE_CORE_URL . 'assets/css/admin-print.css',
                [],
                SPACE_CORE_VERSION
        );

        nocache_headers();
        header( 'Content-Type: text/html; charset=UTF-8' );
        echo $this->view( 'admin/print-page', [
            'format'     => $format,
            'orders'     => $orders,
            'is_thermal' => $is_thermal,
        ] );
        exit;
    }

    // ── Thermal Template (80 mm) ──────────────────────────────────

    private function render_thermal( WC_Order $order, bool $is_last = false ): void {
        $items     = $order->get_items();
        $opts      = $this->opts();
        $shop_name = $opts['shop_name'] ?: get_bloginfo( 'name' );
        echo $this->view( 'admin/print-thermal', [
            'order'     => $order,
            'items'     => $items,
            'options'   => $opts,
            'shop_name' => $shop_name,
            'is_last'   => $is_last,
        ] );
    }

    // ── Options ───────────────────────────────────────────────────

    private function opts(): array {
        $defaults = [
                'shop_name'      => get_bloginfo( 'name' ),
                'logo_id'        => 0,
                'store_address'  => '',
                'footer_message' => '',
                'default_format' => 'a4',
                'print_currency' => 'order',   // 'order' = as placed | 'default' = store default
        ];
        $saved    = get_option( 'space_core_print_orders', [] );

        return array_merge( $defaults, is_array( $saved ) ? $saved : [] );
    }

    /**
     * Returns a small informational currency label for the print header,
     * or an empty string when no multi-currency info is available.
     */
    private function currency_note_for_order( WC_Order $order ): string {
        $opts           = $this->opts();
        $order_currency = (string) $order->get_meta( '_sc_order_currency' );

        if ( ! $order_currency ) {
            return '';
        }

        $symbol = (string) $order->get_meta( '_sc_order_currency_symbol' ) ?: $order_currency;

        if ( 'default' === ( $opts['print_currency'] ?? 'order' ) ) {
            return sprintf(
            /* translators: 1: original currency code, 2: original currency symbol */
                    __( 'Prices in default currency (original: %1$s %2$s)', 'space-core' ),
                    esc_html( $order_currency ),
                    esc_html( $symbol )
            );
        }

        return sprintf(
        /* translators: 1: currency code, 2: currency symbol */
                __( 'Currency: %1$s (%2$s)', 'space-core' ),
                esc_html( $order_currency ),
                esc_html( $symbol )
        );
    }

    /**
     * Format a price amount for the print template.
     *
     * 'order' mode  — uses the currency the customer paid in (_sc_order_currency).
     *                 Looks up symbol/decimals/position from CurrencyDB; falls back to
     *                 WC's get_woocommerce_currency_symbol() for unregistered currencies.
     * 'default' mode — converts back to the store default currency by dividing by the
     *                  stored effective rate (_sc_order_rate), then calls wc_price().
     *
     * Falls back to wc_price() when no multi-currency meta exists on the order.
     */
    private function format_price_for_order( WC_Order $order, float $amount ): string {
        $opts           = $this->opts();
        $order_currency = (string) $order->get_meta( '_sc_order_currency' );

        // No multi-currency meta — order is in the default currency.
        if ( ! $order_currency ) {
            return wc_price( $amount );
        }

        if ( 'default' === ( $opts['print_currency'] ?? 'order' ) ) {
            $rate      = (float) ( $order->get_meta( '_sc_order_rate' ) ?: 1 );
            $converted = $rate > 0 ? round( $amount / $rate, wc_get_price_decimals() ) : $amount;

            return wc_price( $converted );
        }

        // ── Order currency mode ─────────────────────────────────────

        // Resolve symbol: prefer the one saved on the order (locale-aware),
        // fall back to CurrencyDB, then to WC's built-in symbol.
        $symbol   = (string) $order->get_meta( '_sc_order_currency_symbol' );
        $decimals = wc_get_price_decimals();

        // If MultiCurrency is available, get exact decimal digits and position.
        if ( class_exists( CurrencyDB::class ) ) {
            $row = CurrencyDB::get_by_code( $order_currency );
            if ( $row ) {
                $decimals = (int) $row['decimal_digits'];
                if ( ! $symbol ) {
                    $symbol = CurrencyDB::resolve_symbol( $row['symbol'] );
                }
                $pos              = CurrencyPosition::tryFrom( (int) $row['currency_position'] ) ?? CurrencyPosition::AfterSpace;
                $formatted_number = number_format(
                        $amount,
                        $decimals,
                        wc_get_price_decimal_separator(),
                        wc_get_price_thousand_separator()
                );
                $symbol_html      = '<span class="woocommerce-Price-currencySymbol">' . esc_html( $symbol ) . '</span>';
                $price_html       = '<span class="woocommerce-Price-amount amount">' . $pos->format( $formatted_number, $symbol_html ) . '</span>';

                return $price_html;
            }
        }

        // CurrencyDB not available — use wc_price() with the order currency code.
        // Override the symbol via a single-use filter.
        if ( $symbol ) {
            $resolved_symbol = $symbol; // capture for closure.
            $filter          = static function ( $sym, $code ) use ( $order_currency, $resolved_symbol ) {
                return $code === $order_currency ? $resolved_symbol : $sym;
            };
            add_filter( 'woocommerce_currency_symbol', $filter, 10, 2 );
            $price = wc_price( $amount, [ 'currency' => $order_currency, 'decimals' => $decimals ] );
            remove_filter( 'woocommerce_currency_symbol', $filter, 10 );

            return $price;
        }

        return wc_price( $amount, [ 'currency' => $order_currency ] );
    }

    private function render_a4( WC_Order $order, bool $is_last = false ): void {
        $items         = $order->get_items();
        $opts          = $this->opts();
        $shop_name     = $opts['shop_name'] ?: get_bloginfo( 'name' );
        $logo_url      = $opts['logo_id'] ? wp_get_attachment_image_url( (int) $opts['logo_id'], 'medium' ) : '';
        $currency_note = $this->currency_note_for_order( $order );
        echo $this->view( 'admin/print-a4', [
            'order'         => $order,
            'items'         => $items,
            'options'       => $opts,
            'shop_name'     => $shop_name,
            'logo_url'      => $logo_url,
            'currency_note' => $currency_note,
            'is_last'       => $is_last,
        ] );
    }

    // ── Settings ──────────────────────────────────────────────────

    public function ajax_save_settings(): void {
        check_ajax_referer( 'space_core_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [], 403 );
        }

        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $data = [
                'shop_name'      => sanitize_text_field( wp_unslash( $_POST['shop_name'] ?? '' ) ),
                'logo_id'        => absint( $_POST['logo_id'] ?? 0 ),
                'store_address'  => sanitize_textarea_field( wp_unslash( $_POST['store_address'] ?? '' ) ),
                'footer_message' => sanitize_text_field( wp_unslash( $_POST['footer_message'] ?? '' ) ),
                'default_format' => in_array( $_POST['default_format'] ?? '', [
                        'a4',
                        'thermal'
                ], true ) ? $_POST['default_format'] : 'a4',
                'print_currency' => in_array( $_POST['print_currency'] ?? '', [
                        'order',
                        'default'
                ], true ) ? $_POST['print_currency'] : 'order',
        ];
        // phpcs:enable
        update_option( 'space_core_print_orders', $data );
        wp_send_json_success( [ 'message' => __( 'Saved!', 'space-core' ) ] );
    }

    public function render_settings(): void {
        $opts     = $this->opts();
        $nonce    = wp_create_nonce( 'space_core_admin' );
        $logo_url = $opts['logo_id'] ? wp_get_attachment_image_url( (int) $opts['logo_id'], 'thumbnail' ) : '';
        echo $this->view( 'admin/settings', [
            'options'  => $opts,
            'nonce'    => $nonce,
            'logo_url' => $logo_url,
        ] );
    }
}
