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
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo( 'charset' ); ?>">
            <meta name="viewport" content="width=device-width">
            <title><?php esc_html_e( 'Print Orders', 'space-core' ); ?></title>
            <?php wp_print_styles( [ 'space-core-admin-print' ] ); ?>
        </head>
        <body class="sc-print-body sc-print-format-<?php echo esc_attr( $format ); ?>" onload="window.print()">
        <?php $last = count( $orders ) - 1; ?>
        <?php foreach ( $orders as $i => $order ) : ?>
            <?php if ( $is_thermal ) : ?>
                <?php $this->render_thermal( $order, $i === $last ); ?>
            <?php else : ?>
                <?php $this->render_a4( $order, $i === $last ); ?>
            <?php endif; ?>
        <?php endforeach; ?>
        </body>
        </html>
        <?php
        exit;
    }

    // ── Thermal Template (80 mm) ──────────────────────────────────

    private function render_thermal( WC_Order $order, bool $is_last = false ): void {
        $items     = $order->get_items();
        $opts      = $this->opts();
        $shop_name = $opts['shop_name'] ?: get_bloginfo( 'name' );
        ?>
        <div class="print-page page-thermal sc-thermal-page<?php echo $is_last ? ' print-page-last' : ''; ?>">
            <div class="sc-t-center sc-t-bold sc-t-shop-name"><?php echo esc_html( $shop_name ); ?></div>
            <div class="sc-t-center"><?php printf( esc_html__( 'Order #%s', 'space-core' ), esc_html( $order->get_order_number() ) ); ?></div>
            <div class="sc-t-center"><?php echo esc_html( $order->get_date_created() ? $order->get_date_created()->format( 'd/m/Y H:i' ) : '' ); ?></div>
            <?php $currency_note = $this->currency_note_for_order( $order ); ?>
            <?php if ( $currency_note ) : ?>
                <div class="sc-t-center sc-t-currency-note"><?php echo esc_html( $currency_note ); ?></div>
            <?php endif; ?>
            <div class="sc-t-separator"></div>

            <div class="sc-t-bold"><?php echo esc_html( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ); ?></div>
            <?php if ( $order->get_billing_phone() ) {
                echo '<div>' . esc_html( $order->get_billing_phone() ) . '</div>';
            } ?>
            <?php if ( $order->get_billing_address_1() ) {
                echo '<div>' . esc_html( $order->get_billing_address_1() ) . '</div>';
            } ?>

            <div class="sc-t-separator"></div>

            <?php foreach ( $items as $item ) : ?>
                <div class="sc-t-row">
                    <span><?php echo esc_html( $item->get_name() ); ?> x<?php echo esc_html( $item->get_quantity() ); ?></span>
                    <span><?php echo wp_kses_post( $this->format_price_for_order( $order, (float) $item->get_total() ) ); ?></span>
                </div>
            <?php endforeach; ?>

            <div class="sc-t-separator"></div>

            <?php foreach ( $order->get_items( 'shipping' ) as $s ) : ?>
                <div class="sc-t-row">
                    <span><?php echo esc_html( $s->get_name() ); ?></span>
                    <span><?php echo wp_kses_post( $this->format_price_for_order( $order, (float) $s->get_total() ) ); ?></span>
                </div>
            <?php endforeach; ?>
            <?php foreach ( $order->get_items( 'fee' ) as $fee ) : ?>
                <div class="sc-t-row">
                    <span><?php echo esc_html( $fee->get_name() ); ?></span>
                    <span><?php echo wp_kses_post( $this->format_price_for_order( $order, (float) $fee->get_total() ) ); ?></span>
                </div>
            <?php endforeach; ?>

            <div class="sc-t-separator"></div>
            <div class="sc-t-row sc-t-large">
                <span><?php esc_html_e( 'TOTAL', 'space-core' ); ?></span>
                <span><?php echo wp_kses_post( $this->format_price_for_order( $order, (float) $order->get_total() ) ); ?></span>
            </div>
            <div class="sc-t-separator"></div>
            <?php if ( $opts['footer_message'] ) : ?>
                <div class="sc-t-center sc-t-footer-message"><?php echo esc_html( $opts['footer_message'] ); ?></div>
            <?php endif; ?>
            <div class="sc-t-center sc-t-thank-you"><?php esc_html_e( 'Thank you!', 'space-core' ); ?></div>
        </div>
        <?php
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
            $converted = c2b_amount( $amount, $rate );

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
        ?>
        <div class="print-page page-a4 sc-order-page<?php echo $is_last ? ' print-page-last' : ''; ?>">
            <div class="sc-header">
                <div>
                    <?php if ( $logo_url ) : ?>
                        <img src="<?php echo esc_url( $logo_url ); ?>" alt="" class="sc-logo">
                    <?php endif; ?>
                    <div class="sc-site-name"><?php echo esc_html( $shop_name ); ?></div>
                    <?php if ( $opts['store_address'] ) : ?>
                        <div class="sc-store-address"><?php echo esc_html( $opts['store_address'] ); ?></div>
                    <?php endif; ?>
                    <div><?php echo esc_html( get_option( 'admin_email' ) ); ?></div>
                </div>
                <div class="sc-order-meta">
                    <div class="sc-order-number"><?php printf( esc_html__( 'Order #%s', 'space-core' ), esc_html( $order->get_order_number() ) ); ?></div>
                    <div><?php echo esc_html( $order->get_date_created() ? $order->get_date_created()->format( 'd/m/Y H:i' ) : '' ); ?></div>
                    <div><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></div>
                    <?php if ( $currency_note ) : ?>
                        <div class="sc-order-currency-note"><?php echo esc_html( $currency_note ); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="sc-address-grid avoid-break">
                <div>
                    <div class="sc-section-title"><?php esc_html_e( 'Billing Address', 'space-core' ); ?></div>
                    <div><?php echo wp_kses_post( $order->get_formatted_billing_address() ); ?></div>
                    <?php if ( $order->get_billing_phone() ) : ?>
                        <div><?php echo esc_html( $order->get_billing_phone() ); ?></div>
                    <?php endif; ?>
                    <?php if ( $order->get_billing_email() ) : ?>
                        <div><?php echo esc_html( $order->get_billing_email() ); ?></div>
                    <?php endif; ?>
                </div>
                <?php if ( $order->get_formatted_shipping_address() ) : ?>
                    <div>
                        <div class="sc-section-title"><?php esc_html_e( 'Shipping Address', 'space-core' ); ?></div>
                        <div><?php echo wp_kses_post( $order->get_formatted_shipping_address() ); ?></div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="sc-section-title"><?php esc_html_e( 'Order Items', 'space-core' ); ?></div>
            <table>
                <thead>
                <tr>
                    <th><?php esc_html_e( 'Product', 'space-core' ); ?></th>
                    <th><?php esc_html_e( 'SKU', 'space-core' ); ?></th>
                    <th><?php esc_html_e( 'Qty', 'space-core' ); ?></th>
                    <th><?php esc_html_e( 'Price', 'space-core' ); ?></th>
                    <th><?php esc_html_e( 'Total', 'space-core' ); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ( $items as $item ) :
                    $product = $item->get_product();
                    ?>
                    <tr>
                        <td><?php echo esc_html( $item->get_name() ); ?></td>
                        <td><?php echo esc_html( $product ? $product->get_sku() : '' ); ?></td>
                        <td><?php echo esc_html( $item->get_quantity() ); ?></td>
                        <td><?php echo wp_kses_post( $this->format_price_for_order( $order, (float) $order->get_item_subtotal( $item, false, true ) ) ); ?></td>
                        <td><?php echo wp_kses_post( $this->format_price_for_order( $order, (float) $item->get_total() ) ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <table class="sc-totals">
                <tr>
                    <td><?php esc_html_e( 'Subtotal', 'space-core' ); ?></td>
                    <td><?php echo wp_kses_post( $this->format_price_for_order( $order, (float) $order->get_subtotal() ) ); ?></td>
                </tr>
                <?php if ( $order->get_total_discount() ) : ?>
                    <tr>
                        <td><?php esc_html_e( 'Discount', 'space-core' ); ?></td>
                        <td>
                            -<?php echo wp_kses_post( $this->format_price_for_order( $order, (float) $order->get_total_discount() ) ); ?></td>
                    </tr>
                <?php endif; ?>
                <?php foreach ( $order->get_items( 'shipping' ) as $shipping ) : ?>
                    <tr>
                        <td><?php echo esc_html( $shipping->get_name() ); ?></td>
                        <td><?php echo wp_kses_post( $this->format_price_for_order( $order, (float) $shipping->get_total() ) ); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php foreach ( $order->get_items( 'fee' ) as $fee ) : ?>
                    <tr>
                        <td><?php echo esc_html( $fee->get_name() ); ?></td>
                        <td><?php echo wp_kses_post( $this->format_price_for_order( $order, (float) $fee->get_total() ) ); ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="sc-grand-total">
                    <td><strong><?php esc_html_e( 'Total', 'space-core' ); ?></strong></td>
                    <td>
                        <strong><?php echo wp_kses_post( $this->format_price_for_order( $order, (float) $order->get_total() ) ); ?></strong>
                    </td>
                </tr>
            </table>

            <?php if ( $order->get_customer_note() ) : ?>
                <div class="sc-section-title"><?php esc_html_e( 'Customer Note', 'space-core' ); ?></div>
                <p><?php echo esc_html( $order->get_customer_note() ); ?></p>
            <?php endif; ?>

            <?php if ( $opts['footer_message'] ) : ?>
                <div class="sc-footer"><?php echo esc_html( $opts['footer_message'] ); ?></div>
            <?php endif; ?>
        </div>
        <?php
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
        ?>
        <div style="max-width:600px;">
            <p class="description"><?php esc_html_e( 'Customize the print template used for A4 and thermal print pages.', 'space-core' ); ?></p>
            <table class="form-table" style="margin-top:16px;">
                <tr>
                    <th><?php esc_html_e( 'Shop Name', 'space-core' ); ?></th>
                    <td><input type="text" id="sc-po-shop-name" class="regular-text"
                               value="<?php echo esc_attr( $opts['shop_name'] ); ?>"></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Logo', 'space-core' ); ?></th>
                    <td>
                        <input type="hidden" id="sc-po-logo-id" value="<?php echo esc_attr( $opts['logo_id'] ); ?>">
                        <?php if ( $logo_url ) : ?>
                            <img id="sc-po-logo-preview" src="<?php echo esc_url( $logo_url ); ?>"
                                 style="max-height:60px;display:block;margin-bottom:8px;">
                        <?php else : ?>
                            <img id="sc-po-logo-preview" src="" style="max-height:60px;display:none;margin-bottom:8px;">
                        <?php endif; ?>
                        <button type="button" class="button"
                                id="sc-po-logo-pick"><?php esc_html_e( 'Select Logo', 'space-core' ); ?></button>
                        <button type="button" class="button" id="sc-po-logo-remove"
                                style="margin-left:4px;<?php echo $opts['logo_id'] ? '' : 'display:none;'; ?>"><?php esc_html_e( 'Remove', 'space-core' ); ?></button>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Store Address', 'space-core' ); ?></th>
                    <td><textarea id="sc-po-address" class="large-text"
                                  rows="3"><?php echo esc_textarea( $opts['store_address'] ); ?></textarea></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Footer Message', 'space-core' ); ?></th>
                    <td><input type="text" id="sc-po-footer" class="regular-text"
                               value="<?php echo esc_attr( $opts['footer_message'] ); ?>"></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Default Format', 'space-core' ); ?></th>
                    <td>
                        <select id="sc-po-format">
                            <option value="a4" <?php selected( $opts['default_format'], 'a4' ); ?>><?php esc_html_e( 'A4', 'space-core' ); ?></option>
                            <option value="thermal" <?php selected( $opts['default_format'], 'thermal' ); ?>><?php esc_html_e( 'Thermal 80mm', 'space-core' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Print Currency', 'space-core' ); ?></th>
                    <td>
                        <select id="sc-po-print-currency">
                            <option value="order" <?php selected( $opts['print_currency'] ?? 'order', 'order' ); ?>>
                                <?php esc_html_e( 'Order currency (as placed by customer)', 'space-core' ); ?>
                            </option>
                            <option value="default" <?php selected( $opts['print_currency'] ?? 'order', 'default' ); ?>>
                                <?php esc_html_e( 'Default currency (convert back using saved rate)', 'space-core' ); ?>
                            </option>
                        </select>
                        <p class="description"><?php esc_html_e( 'Applies only when Multi-Currency module is active and the order was placed in a non-default currency.', 'space-core' ); ?></p>
                    </td>
                </tr>
            </table>

            <p style="margin-top:16px;">
                <button type="button" class="button button-primary"
                        id="sc-po-save"><?php esc_html_e( 'Save Settings', 'space-core' ); ?></button>
                <span id="sc-po-status" style="margin-left:10px;font-weight:600;"></span>
            </p>

            <hr style="margin:24px 0;">
            <p><?php printf( esc_html__( 'Print buttons are added to the %s (row actions and bulk actions).', 'space-core' ), '<a href="' . esc_url( admin_url( 'edit.php?post_type=shop_order' ) ) . '">' . esc_html__( 'Orders list', 'space-core' ) . '</a>' ); ?></p>
        </div>

        <script>
            jQuery(function ($) {
                // Media picker.
                $('#sc-po-logo-pick').on('click', function () {
                    var frame = wp.media({
                        title: '<?php echo esc_js( __( 'Select Logo', 'space-core' ) ); ?>',
                        button: {text: '<?php echo esc_js( __( 'Use this image', 'space-core' ) ); ?>'},
                        multiple: false
                    });
                    frame.on('select', function () {
                        var att = frame.state().get('selection').first().toJSON();
                        $('#sc-po-logo-id').val(att.id);
                        $('#sc-po-logo-preview').attr('src', att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url).show();
                        $('#sc-po-logo-remove').show();
                    });
                    frame.open();
                });
                $('#sc-po-logo-remove').on('click', function () {
                    $('#sc-po-logo-id').val(0);
                    $('#sc-po-logo-preview').attr('src', '').hide();
                    $(this).hide();
                });

                // Save.
                $('#sc-po-save').on('click', function () {
                    var $btn = $(this);
                    $btn.prop('disabled', true);
                    $.post(spaceCore.ajaxUrl, {
                        action: 'sc_save_print_settings',
                        nonce: spaceCore.nonce,
                        shop_name: $('#sc-po-shop-name').val(),
                        logo_id: $('#sc-po-logo-id').val(),
                        store_address: $('#sc-po-address').val(),
                        footer_message: $('#sc-po-footer').val(),
                        default_format: $('#sc-po-format').val(),
                        print_currency: $('#sc-po-print-currency').val(),
                    }, function (res) {
                        $('#sc-po-status').text(res.success ? '<?php echo esc_js( __( 'Saved!', 'space-core' ) ); ?>' : '<?php echo esc_js( __( 'Error.', 'space-core' ) ); ?>')
                            .css('color', res.success ? '#2e7d32' : '#c62828');
                        setTimeout(function () {
                            $('#sc-po-status').text('');
                        }, 3000);
                    }).always(function () {
                        $btn.prop('disabled', false);
                    });
                });
            });
        </script>
        <?php
    }
}
