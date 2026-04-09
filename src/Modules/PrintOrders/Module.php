<?php

namespace Space\Core\Modules\PrintOrders;

defined( 'ABSPATH' ) || exit;

use Space\Core\Abstracts\AbstractModule;

/**
 * Print Orders — per-order and bulk print for A4 and Epson thermal (80 mm).
 *
 * Adds:
 *  - "Print A4" / "Print Thermal" actions to the order list row actions.
 *  - A "Print Selected" bulk action.
 *  - A print button on the single order edit screen.
 */
class Module extends AbstractModule {

    public function get_label(): string {
        return __( 'Print Orders', 'space-core' );
    }

    public function get_description(): string {
        return __( 'Print orders as A4 or 80mm thermal receipt, single or in bulk.', 'space-core' );
    }

    private function opts(): array {
        $defaults = [
            'shop_name'       => get_bloginfo( 'name' ),
            'logo_id'         => 0,
            'store_address'   => '',
            'footer_message'  => '',
            'default_format'  => 'a4',
        ];
        $saved = get_option( 'space_core_print_orders', [] );
        return array_merge( $defaults, is_array( $saved ) ? $saved : [] );
    }

    public function boot(): void {
        if ( ! class_exists( 'WooCommerce' ) ) return;

        // Row actions on order list.
        add_filter( 'woocommerce_admin_order_actions',   [ $this, 'add_order_actions' ], 10, 2 );
        add_action( 'admin_head',                         [ $this, 'order_action_styles' ] );

        // Single order page button.
        add_action( 'woocommerce_order_actions',          [ $this, 'add_single_order_action' ] );
        add_action( 'woocommerce_order_action_sc_print_a4',      [ $this, 'handle_print_a4' ] );
        add_action( 'woocommerce_order_action_sc_print_thermal',  [ $this, 'handle_print_thermal' ] );

        // Bulk actions on order list (legacy + HPOS).
        add_filter( 'bulk_actions-edit-shop_order',                  [ $this, 'add_bulk_actions' ] );
        add_filter( 'handle_bulk_actions-edit-shop_order',           [ $this, 'handle_bulk_actions' ], 10, 3 );
        add_filter( 'bulk_actions-woocommerce_page_wc-orders',      [ $this, 'add_bulk_actions' ] );
        add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', [ $this, 'handle_bulk_actions' ], 10, 3 );

        // Print page (rendered via admin.php?page=sc-print-order&format=a4&ids=1,2,3).
        add_action( 'admin_menu', [ $this, 'register_print_page' ] );

        add_action( 'wp_ajax_sc_print_orders',         [ $this, 'ajax_print' ] );
        add_action( 'wp_ajax_sc_save_print_settings',  [ $this, 'ajax_save_settings' ] );
    }

    public function register_print_page(): void {
        add_submenu_page( null, __( 'Print Orders', 'space-core' ), '', 'manage_woocommerce', 'sc-print-order', [ $this, 'render_print_page' ] );
    }

    public function add_order_actions( array $actions, \WC_Order $order ): array {
        $base = admin_url( 'admin.php?page=sc-print-order&ids=' . $order->get_id() );
        $actions['sc_print_a4']      = [
            'url'    => esc_url( $base . '&format=a4' ),
            'name'   => __( 'Print A4', 'space-core' ),
            'action' => 'sc_print_a4',
            'target' => '_blank',
        ];
        $actions['sc_print_thermal'] = [
            'url'    => esc_url( $base . '&format=thermal' ),
            'name'   => __( 'Print 80mm', 'space-core' ),
            'action' => 'sc_print_thermal',
            'target' => '_blank',
        ];
        return $actions;
    }

    public function order_action_styles(): void {
        $screen = get_current_screen();
        if ( ! $screen || ! in_array( $screen->id, [ 'edit-shop_order', 'woocommerce_page_wc-orders' ], true ) ) return;
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

    public function handle_print_a4( \WC_Order $order ): void {
        // Single order action from edit page — redirect then auto-open in new tab via JS.
        $url = admin_url( 'admin.php?page=sc-print-order&ids=' . $order->get_id() . '&format=a4' );
        echo '<script>window.open(' . json_encode( esc_url_raw( $url ) ) . ', "_blank");</script>';
    }

    public function handle_print_thermal( \WC_Order $order ): void {
        $url = admin_url( 'admin.php?page=sc-print-order&ids=' . $order->get_id() . '&format=thermal' );
        echo '<script>window.open(' . json_encode( esc_url_raw( $url ) ) . ', "_blank");</script>';
    }

    public function add_bulk_actions( array $actions ): array {
        $actions['sc_bulk_print_a4']      = __( 'Print A4 (selected)', 'space-core' );
        $actions['sc_bulk_print_thermal'] = __( 'Print Thermal (selected)', 'space-core' );
        return $actions;
    }

    public function handle_bulk_actions( string $redirect, string $action, array $ids ): string {
        $format = null;
        if ( 'sc_bulk_print_a4' === $action )      $format = 'a4';
        if ( 'sc_bulk_print_thermal' === $action ) $format = 'thermal';
        if ( ! $format ) return $redirect;

        $url = admin_url( 'admin.php?page=sc-print-order&ids=' . implode( ',', array_map( 'absint', $ids ) ) . '&format=' . $format );
        wp_safe_redirect( $url );
        exit;
    }

    public function ajax_print(): void {
        check_ajax_referer( 'space_core_admin', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error( [], 403 );
        $format = sanitize_key( $_POST['format'] ?? 'a4' );
        $ids    = array_map( 'absint', explode( ',', wp_unslash( $_POST['ids'] ?? '' ) ) );
        wp_send_json_success( [ 'url' => admin_url( 'admin.php?page=sc-print-order&ids=' . implode( ',', $ids ) . '&format=' . $format ) ] );
    }

    public function render_print_page(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'Unauthorized' );

        // Remove admin notices and discard any buffered WP output.
        remove_all_actions( 'admin_notices' );
        remove_all_actions( 'all_admin_notices' );
        ob_start();

        $format = sanitize_key( $_GET['format'] ?? 'a4' ); // phpcs:ignore
        $ids    = array_filter( array_map( 'absint', explode( ',', wp_unslash( $_GET['ids'] ?? '' ) ) ) ); // phpcs:ignore

        if ( empty( $ids ) ) {
            wp_die( esc_html__( 'No orders selected.', 'space-core' ) );
        }

        $is_thermal = 'thermal' === $format;
        $orders     = array_filter( array_map( 'wc_get_order', $ids ) );
        $orders     = array_values( $orders );

        // Discard any buffered WP admin output.
        ob_end_clean();

        // Output clean standalone HTML — no WP sidebar.
        header( 'Content-Type: text/html; charset=UTF-8' );
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width">
            <title><?php esc_html_e( 'Print Orders', 'space-core' ); ?></title>
            <style>
            #wpadminbar, #adminmenuback, #adminmenuwrap { display: none !important; }
            body { margin: 0 !important; padding: 0 !important; }
            <?php echo $is_thermal ? $this->thermal_css() : $this->a4_css(); ?>
            </style>
        </head>
        <body onload="window.print()">
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

    // ── A4 Template ───────────────────────────────────────────────

    private function a4_css(): string {
        return '
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: Arial, sans-serif; }
        body { background: #fff; color: #000; font-size: 12pt; }
        .sc-order-page { width: 210mm; min-height: 297mm; padding: 20mm; page-break-after: always; margin: 0 auto; }
        .sc-order-page.last { page-break-after: avoid; }
        .sc-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24pt; }
        .sc-site-name { font-size: 22pt; font-weight: 700; }
        .sc-order-number { font-size: 14pt; font-weight: 600; color: #444; }
        .sc-section-title { font-size: 11pt; font-weight: 700; border-bottom: 2px solid #000; padding-bottom: 4pt; margin: 16pt 0 8pt; }
        table { width: 100%; border-collapse: collapse; margin-top: 8pt; }
        th, td { padding: 6pt 8pt; border: 1px solid #ddd; font-size: 10pt; }
        th { background: #f5f5f5; font-weight: 700; text-align: left; }
        .sc-totals { margin-top: 12pt; margin-left: auto; width: 50%; }
        .sc-totals td { border: none; padding: 3pt 8pt; }
        .sc-totals .sc-grand-total { font-weight: 700; font-size: 12pt; border-top: 2pt solid #000; }
        .sc-logo { max-height: 60pt; max-width: 150pt; margin-bottom: 8pt; display: block; }
        .sc-footer { margin-top: 32pt; text-align: center; font-size: 9pt; color: #666; border-top: 1px solid #eee; padding-top: 8pt; }
        @media print {
            @page { size: A4; margin: 0; }
            body { margin: 0; }
        }
        ';
    }

    private function render_a4( \WC_Order $order, bool $is_last = false ): void {
        $items     = $order->get_items();
        $opts      = $this->opts();
        $shop_name = $opts['shop_name'] ?: get_bloginfo( 'name' );
        $logo_url  = $opts['logo_id'] ? wp_get_attachment_image_url( (int) $opts['logo_id'], 'medium' ) : '';
        ?>
        <div class="sc-order-page<?php echo $is_last ? ' last' : ''; ?>">
            <div class="sc-header">
                <div>
                    <?php if ( $logo_url ) : ?>
                        <img src="<?php echo esc_url( $logo_url ); ?>" alt="" class="sc-logo">
                    <?php endif; ?>
                    <div class="sc-site-name"><?php echo esc_html( $shop_name ); ?></div>
                    <?php if ( $opts['store_address'] ) : ?>
                        <div style="white-space:pre-line;font-size:10pt;"><?php echo esc_html( $opts['store_address'] ); ?></div>
                    <?php endif; ?>
                    <div><?php echo esc_html( get_option( 'admin_email' ) ); ?></div>
                </div>
                <div style="text-align:right;">
                    <div class="sc-order-number"><?php printf( esc_html__( 'Order #%s', 'space-core' ), esc_html( $order->get_order_number() ) ); ?></div>
                    <div><?php echo esc_html( $order->get_date_created() ? $order->get_date_created()->format( 'd/m/Y H:i' ) : '' ); ?></div>
                    <div><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></div>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16pt;">
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
                            <td><?php echo wp_kses_post( wc_price( $order->get_item_subtotal( $item, false, true ) ) ); ?></td>
                            <td><?php echo wp_kses_post( wc_price( $item->get_total() ) ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <table class="sc-totals">
                <tr><td><?php esc_html_e( 'Subtotal', 'space-core' ); ?></td><td><?php echo wp_kses_post( wc_price( $order->get_subtotal() ) ); ?></td></tr>
                <?php if ( $order->get_total_discount() ) : ?>
                    <tr><td><?php esc_html_e( 'Discount', 'space-core' ); ?></td><td>-<?php echo wp_kses_post( wc_price( $order->get_total_discount() ) ); ?></td></tr>
                <?php endif; ?>
                <?php foreach ( $order->get_items( 'shipping' ) as $shipping ) : ?>
                    <tr><td><?php echo esc_html( $shipping->get_name() ); ?></td><td><?php echo wp_kses_post( wc_price( $shipping->get_total() ) ); ?></td></tr>
                <?php endforeach; ?>
                <?php foreach ( $order->get_items( 'fee' ) as $fee ) : ?>
                    <tr><td><?php echo esc_html( $fee->get_name() ); ?></td><td><?php echo wp_kses_post( wc_price( $fee->get_total() ) ); ?></td></tr>
                <?php endforeach; ?>
                <tr class="sc-grand-total"><td><strong><?php esc_html_e( 'Total', 'space-core' ); ?></strong></td><td><strong><?php echo wp_kses_post( wc_price( $order->get_total() ) ); ?></strong></td></tr>
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

    // ── Thermal Template (80 mm) ──────────────────────────────────

    private function thermal_css(): string {
        return '
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: "Courier New", monospace; }
        body { background: #fff; color: #000; font-size: 9pt; }
        .sc-thermal-page { width: 80mm; padding: 4mm; page-break-after: always; margin: 0 auto; }
        .sc-thermal-page.last { page-break-after: avoid; }
        .sc-t-center { text-align: center; }
        .sc-t-bold { font-weight: 700; }
        .sc-t-separator { border-top: 1px dashed #000; margin: 4pt 0; }
        .sc-t-row { display: flex; justify-content: space-between; gap: 4pt; }
        .sc-t-row span:first-child { flex: 1; overflow: hidden; }
        .sc-t-row span:last-child { flex-shrink: 0; }
        .sc-t-large { font-size: 11pt; font-weight: 700; }
        @media print {
            @page { size: 80mm auto; margin: 0; }
            body { margin: 0; }
        }
        ';
    }

    private function render_thermal( \WC_Order $order, bool $is_last = false ): void {
        $items     = $order->get_items();
        $opts      = $this->opts();
        $shop_name = $opts['shop_name'] ?: get_bloginfo( 'name' );
        ?>
        <div class="sc-thermal-page<?php echo $is_last ? ' last' : ''; ?>">
            <div class="sc-t-center sc-t-bold" style="font-size:12pt;margin-bottom:4pt;"><?php echo esc_html( $shop_name ); ?></div>
            <div class="sc-t-center"><?php printf( esc_html__( 'Order #%s', 'space-core' ), esc_html( $order->get_order_number() ) ); ?></div>
            <div class="sc-t-center"><?php echo esc_html( $order->get_date_created() ? $order->get_date_created()->format( 'd/m/Y H:i' ) : '' ); ?></div>
            <div class="sc-t-separator"></div>

            <div class="sc-t-bold"><?php echo esc_html( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ); ?></div>
            <?php if ( $order->get_billing_phone() ) echo '<div>' . esc_html( $order->get_billing_phone() ) . '</div>'; ?>
            <?php if ( $order->get_billing_address_1() ) echo '<div>' . esc_html( $order->get_billing_address_1() ) . '</div>'; ?>

            <div class="sc-t-separator"></div>

            <?php foreach ( $items as $item ) : ?>
                <div class="sc-t-row">
                    <span><?php echo esc_html( $item->get_name() ); ?> x<?php echo esc_html( $item->get_quantity() ); ?></span>
                    <span><?php echo wp_kses_post( wc_price( $item->get_total() ) ); ?></span>
                </div>
            <?php endforeach; ?>

            <div class="sc-t-separator"></div>

            <?php foreach ( $order->get_items( 'shipping' ) as $s ) : ?>
                <div class="sc-t-row"><span><?php echo esc_html( $s->get_name() ); ?></span><span><?php echo wp_kses_post( wc_price( $s->get_total() ) ); ?></span></div>
            <?php endforeach; ?>
            <?php foreach ( $order->get_items( 'fee' ) as $fee ) : ?>
                <div class="sc-t-row"><span><?php echo esc_html( $fee->get_name() ); ?></span><span><?php echo wp_kses_post( wc_price( $fee->get_total() ) ); ?></span></div>
            <?php endforeach; ?>

            <div class="sc-t-separator"></div>
            <div class="sc-t-row sc-t-large">
                <span><?php esc_html_e( 'TOTAL', 'space-core' ); ?></span>
                <span><?php echo wp_kses_post( wc_price( $order->get_total() ) ); ?></span>
            </div>
            <div class="sc-t-separator"></div>
            <?php if ( $opts['footer_message'] ) : ?>
                <div class="sc-t-center" style="margin-top:4pt;font-size:8pt;"><?php echo esc_html( $opts['footer_message'] ); ?></div>
            <?php endif; ?>
            <div class="sc-t-center" style="margin-top:8pt;"><?php esc_html_e( 'Thank you!', 'space-core' ); ?></div>
        </div>
        <?php
    }

    // ── Settings ──────────────────────────────────────────────────

    public function ajax_save_settings(): void {
        check_ajax_referer( 'space_core_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [], 403 );

        $data = [
            'shop_name'      => sanitize_text_field( wp_unslash( $_POST['shop_name']      ?? '' ) ), // phpcs:ignore
            'logo_id'        => absint( $_POST['logo_id']        ?? 0 ), // phpcs:ignore
            'store_address'  => sanitize_textarea_field( wp_unslash( $_POST['store_address']  ?? '' ) ), // phpcs:ignore
            'footer_message' => sanitize_text_field( wp_unslash( $_POST['footer_message'] ?? '' ) ), // phpcs:ignore
            'default_format' => in_array( $_POST['default_format'] ?? '', [ 'a4', 'thermal' ], true ) ? $_POST['default_format'] : 'a4', // phpcs:ignore
        ];
        update_option( 'space_core_print_orders', $data );
        wp_send_json_success( [ 'message' => __( 'Saved!', 'space-core' ) ] );
    }

    public function render_settings(): void {
        $opts  = $this->opts();
        $nonce = wp_create_nonce( 'space_core_admin' );
        $logo_url = $opts['logo_id'] ? wp_get_attachment_image_url( (int) $opts['logo_id'], 'thumbnail' ) : '';
        ?>
        <div style="max-width:600px;">
            <p class="description"><?php esc_html_e( 'Customize the print template used for A4 and thermal print pages.', 'space-core' ); ?></p>
            <table class="form-table" style="margin-top:16px;">
                <tr>
                    <th><?php esc_html_e( 'Shop Name', 'space-core' ); ?></th>
                    <td><input type="text" id="sc-po-shop-name" class="regular-text" value="<?php echo esc_attr( $opts['shop_name'] ); ?>"></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Logo', 'space-core' ); ?></th>
                    <td>
                        <input type="hidden" id="sc-po-logo-id" value="<?php echo esc_attr( $opts['logo_id'] ); ?>">
                        <?php if ( $logo_url ) : ?>
                            <img id="sc-po-logo-preview" src="<?php echo esc_url( $logo_url ); ?>" style="max-height:60px;display:block;margin-bottom:8px;">
                        <?php else : ?>
                            <img id="sc-po-logo-preview" src="" style="max-height:60px;display:none;margin-bottom:8px;">
                        <?php endif; ?>
                        <button type="button" class="button" id="sc-po-logo-pick"><?php esc_html_e( 'Select Logo', 'space-core' ); ?></button>
                        <button type="button" class="button" id="sc-po-logo-remove" style="margin-left:4px;<?php echo $opts['logo_id'] ? '' : 'display:none;'; ?>"><?php esc_html_e( 'Remove', 'space-core' ); ?></button>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Store Address', 'space-core' ); ?></th>
                    <td><textarea id="sc-po-address" class="large-text" rows="3"><?php echo esc_textarea( $opts['store_address'] ); ?></textarea></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Footer Message', 'space-core' ); ?></th>
                    <td><input type="text" id="sc-po-footer" class="regular-text" value="<?php echo esc_attr( $opts['footer_message'] ); ?>"></td>
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
            </table>

            <p style="margin-top:16px;">
                <button type="button" class="button button-primary" id="sc-po-save"><?php esc_html_e( 'Save Settings', 'space-core' ); ?></button>
                <span id="sc-po-status" style="margin-left:10px;font-weight:600;"></span>
            </p>

            <hr style="margin:24px 0;">
            <p><?php printf( esc_html__( 'Print buttons are added to the %s (row actions and bulk actions).', 'space-core' ), '<a href="' . esc_url( admin_url( 'edit.php?post_type=shop_order' ) ) . '">' . esc_html__( 'Orders list', 'space-core' ) . '</a>' ); ?></p>
        </div>

        <script>
        jQuery(function($){
            // Media picker.
            $('#sc-po-logo-pick').on('click', function(){
                var frame = wp.media({ title: '<?php echo esc_js( __( 'Select Logo', 'space-core' ) ); ?>', button: { text: '<?php echo esc_js( __( 'Use this image', 'space-core' ) ); ?>' }, multiple: false });
                frame.on('select', function(){
                    var att = frame.state().get('selection').first().toJSON();
                    $('#sc-po-logo-id').val(att.id);
                    $('#sc-po-logo-preview').attr('src', att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url).show();
                    $('#sc-po-logo-remove').show();
                });
                frame.open();
            });
            $('#sc-po-logo-remove').on('click', function(){
                $('#sc-po-logo-id').val(0);
                $('#sc-po-logo-preview').attr('src','').hide();
                $(this).hide();
            });

            // Save.
            $('#sc-po-save').on('click', function(){
                var $btn = $(this);
                $btn.prop('disabled', true);
                $.post(spaceCore.ajaxUrl, {
                    action:         'sc_save_print_settings',
                    nonce:          spaceCore.nonce,
                    shop_name:      $('#sc-po-shop-name').val(),
                    logo_id:        $('#sc-po-logo-id').val(),
                    store_address:  $('#sc-po-address').val(),
                    footer_message: $('#sc-po-footer').val(),
                    default_format: $('#sc-po-format').val(),
                }, function(res){
                    $('#sc-po-status').text(res.success ? '<?php echo esc_js( __( 'Saved!', 'space-core' ) ); ?>' : '<?php echo esc_js( __( 'Error.', 'space-core' ) ); ?>')
                        .css('color', res.success ? '#2e7d32' : '#c62828');
                    setTimeout(function(){ $('#sc-po-status').text(''); }, 3000);
                }).always(function(){ $btn.prop('disabled', false); });
            });
        });
        </script>
        <?php
    }
}
