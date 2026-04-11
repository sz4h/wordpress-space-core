<?php defined( 'ABSPATH' ) || exit; ?>
<section class="sc-order-delivery woocommerce-order-details" style="margin-bottom:24px;">
    <h2 class="woocommerce-order-details__title" style="font-size:18px;margin-bottom:12px;">
        <?php esc_html_e( 'Delivery Details', 'space-core' ); ?>
    </h2>
    <table class="woocommerce-table shop_table" style="width:100%;">
        <tbody>
        <?php if ( $data['city'] ) : ?>
            <tr>
                <th style="padding:8px 12px;"><?php esc_html_e( 'City', 'space-core' ); ?></th>
                <td style="padding:8px 12px;"><?php echo esc_html( $data['city'] ); ?></td>
            </tr>
        <?php endif; ?>
        <?php if ( $data['area'] ) : ?>
            <tr>
                <th style="padding:8px 12px;"><?php esc_html_e( 'Area', 'space-core' ); ?></th>
                <td style="padding:8px 12px;"><?php echo esc_html( $data['area'] ); ?></td>
            </tr>
        <?php endif; ?>
        <tr>
            <th style="padding:8px 12px;"><?php esc_html_e( 'Delivery Type', 'space-core' ); ?></th>
            <td style="padding:8px 12px;"><?php echo esc_html( $type_label ); ?></td>
        </tr>
        <?php if ( $data['price'] ) : ?>
            <tr>
                <th style="padding:8px 12px;"><?php esc_html_e( 'Delivery Fee', 'space-core' ); ?></th>
                <td style="padding:8px 12px;font-weight:600;">
                    <?php echo esc_html( number_format( (float) $data['price'], 3 ) . $currency ); ?>
                </td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</section>
