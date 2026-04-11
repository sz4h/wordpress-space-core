<?php defined( 'ABSPATH' ) || exit; ?>
<div class="sc-admin-delivery"
     style="margin-top:12px;padding:10px;background:#f9f9f9;border:1px solid #e5e5e5;border-radius:4px;">
    <strong><?php esc_html_e( 'Delivery Details', 'space-core' ); ?></strong><br>
    <?php if ( $data['city'] ) : ?>
        <span><?php esc_html_e( 'City:', 'space-core' ); ?> <strong><?php echo esc_html( $data['city'] ); ?></strong></span>
        <br>
    <?php endif; ?>
    <?php if ( $data['area'] ) : ?>
        <span><?php esc_html_e( 'Area:', 'space-core' ); ?> <strong><?php echo esc_html( $data['area'] ); ?></strong></span>
        <br>
    <?php endif; ?>
    <span><?php esc_html_e( 'Type:', 'space-core' ); ?> <strong><?php echo esc_html( $type_label ); ?></strong></span><br>
    <?php if ( $data['price'] ) : ?>
        <span><?php esc_html_e( 'Fee:', 'space-core' ); ?> <strong><?php echo esc_html( number_format( (float) $data['price'], 3 ) . $currency ); ?></strong></span>
    <?php endif; ?>
</div>
