<?php $currency_note = $this->currency_note_for_order( $order ); ?>
<div class="print-page page-thermal sc-thermal-page<?php echo $is_last ? ' print-page-last' : ''; ?>">
    <div class="sc-t-center sc-t-bold sc-t-shop-name"><?php echo esc_html( $shop_name ); ?></div>
    <div class="sc-t-center"><?php printf( esc_html__( 'Order #%s', 'space-core' ), esc_html( $order->get_order_number() ) ); ?></div>
    <div class="sc-t-center"><?php echo esc_html( $order->get_date_created() ? $order->get_date_created()->format( 'd/m/Y H:i' ) : '' ); ?></div>
    <?php if ( $currency_note ) : ?>
        <div class="sc-t-center sc-t-currency-note"><?php echo esc_html( $currency_note ); ?></div>
    <?php endif; ?>
    <div class="sc-t-separator"></div>

    <div class="sc-t-bold"><?php echo esc_html( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ); ?></div>
    <?php if ( $order->get_billing_phone() ) : ?>
        <div><?php echo esc_html( $order->get_billing_phone() ); ?></div>
    <?php endif; ?>
    <?php if ( $order->get_billing_address_1() ) : ?>
        <div><?php echo esc_html( $order->get_billing_address_1() ); ?></div>
    <?php endif; ?>

    <div class="sc-t-separator"></div>

    <?php foreach ( $items as $item ) : ?>
        <div class="sc-t-row">
            <span><?php echo esc_html( $item->get_name() ); ?> x<?php echo esc_html( $item->get_quantity() ); ?></span>
            <span><?php echo wp_kses_post( $this->format_price_for_order( $order, (float) $item->get_total() ) ); ?></span>
        </div>
    <?php endforeach; ?>

    <div class="sc-t-separator"></div>

    <?php foreach ( $order->get_items( 'shipping' ) as $shipping_item ) : ?>
        <div class="sc-t-row">
            <span><?php echo esc_html( $shipping_item->get_name() ); ?></span>
            <span><?php echo wp_kses_post( $this->format_price_for_order( $order, (float) $shipping_item->get_total() ) ); ?></span>
        </div>
    <?php endforeach; ?>
    <?php foreach ( $order->get_items( 'fee' ) as $fee ) : ?>
        <div class="sc-t-row">
            <span><?php echo esc_html( $fee->get_name() ); ?></span>
            <span><?php echo wp_kses_post( $this->format_price_for_order( $order, (float) $fee->get_total() ) ); ?></span>
        </div>
    <?php endforeach; ?>

    <?php
    $gift_wrap = $order->get_meta( '_sc_gift_wrap' );
    $gift_msg  = (string) $order->get_meta( '_sc_gift_message' );
    if ( 'yes' === $gift_wrap ) :
    ?>
    <div class="sc-t-row">
        <span><?php esc_html_e( 'Gift Wrap', 'space-core' ); ?></span>
        <span>✓</span>
    </div>
    <?php if ( $gift_msg ) : ?>
        <div><?php echo esc_html( $gift_msg ); ?></div>
    <?php endif; ?>
    <?php endif; ?>

    <div class="sc-t-separator"></div>
    <div class="sc-t-row sc-t-large">
        <span><?php esc_html_e( 'TOTAL', 'space-core' ); ?></span>
        <span><?php echo wp_kses_post( $this->format_price_for_order( $order, (float) $order->get_total() ) ); ?></span>
    </div>
    <div class="sc-t-separator"></div>
    <?php if ( $options['footer_message'] ) : ?>
        <div class="sc-t-center sc-t-footer-message"><?php echo esc_html( $options['footer_message'] ); ?></div>
    <?php endif; ?>
    <div class="sc-t-center sc-t-thank-you"><?php esc_html_e( 'Thank you!', 'space-core' ); ?></div>
</div>
