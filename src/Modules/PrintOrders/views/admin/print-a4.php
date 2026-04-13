<div class="print-page page-a4 sc-order-page<?php echo $is_last ? ' print-page-last' : ''; ?>">
    <div class="sc-header">
        <div>
            <?php if ( $logo_url ) : ?>
                <img src="<?php echo esc_url( $logo_url ); ?>" alt="" class="sc-logo">
            <?php endif; ?>
            <div class="sc-site-name"><?php echo esc_html( $shop_name ); ?></div>
            <?php if ( $options['store_address'] ) : ?>
                <div class="sc-store-address"><?php echo esc_html( $options['store_address'] ); ?></div>
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
        <?php foreach ( $items as $item ) : ?>
            <?php $product = $item->get_product(); ?>
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

    <?php
    $gift_wrap = $order->get_meta( '_sc_gift_wrap' );
    $gift_msg  = (string) $order->get_meta( '_sc_gift_message' );
    if ( 'yes' === $gift_wrap ) :
        ?>
        <div class="sc-section-title" style="margin-top:12px;"><?php esc_html_e( 'Gift Wrap', 'space-core' ); ?> ✓</div>
        <?php if ( $gift_msg ) : ?>
        <p style="margin:4px 0 0;"><?php echo nl2br( esc_html( $gift_msg ) ); ?></p>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ( $order->get_customer_note() ) : ?>
        <div class="sc-section-title"><?php esc_html_e( 'Customer Note', 'space-core' ); ?></div>
        <p><?php echo esc_html( $order->get_customer_note() ); ?></p>
    <?php endif; ?>

    <?php if ( $options['footer_message'] ) : ?>
        <div class="sc-footer"><?php echo esc_html( $options['footer_message'] ); ?></div>
    <?php endif; ?>
</div>
