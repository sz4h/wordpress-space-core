<?php defined( 'ABSPATH' ) || exit; ?>
<div style="margin-bottom:24px;font-family:Arial,sans-serif;">
    <h2 style="font-size:18px;color:#333;border-bottom:2px solid #e5e5e5;padding-bottom:8px;">
        <?php esc_html_e( 'Delivery Details', 'space-core' ); ?>
    </h2>
    <table style="width:100%;border-collapse:collapse;">
        <?php if ( $data['city'] ) : ?>
            <tr>
                <td style="padding:8px 12px;border-bottom:1px solid #f0f0f0;font-weight:bold;width:40%;">
                    <?php esc_html_e( 'City', 'space-core' ); ?>
                </td>
                <td style="padding:8px 12px;border-bottom:1px solid #f0f0f0;">
                    <?php echo esc_html( $data['city'] ); ?>
                </td>
            </tr>
        <?php endif; ?>
        <?php if ( $data['area'] ) : ?>
            <tr>
                <td style="padding:8px 12px;border-bottom:1px solid #f0f0f0;font-weight:bold;width:40%;">
                    <?php esc_html_e( 'Area', 'space-core' ); ?>
                </td>
                <td style="padding:8px 12px;border-bottom:1px solid #f0f0f0;">
                    <?php echo esc_html( $data['area'] ); ?>
                </td>
            </tr>
        <?php endif; ?>
        <tr>
            <td style="padding:8px 12px;border-bottom:1px solid #f0f0f0;font-weight:bold;width:40%;">
                <?php esc_html_e( 'Delivery Type', 'space-core' ); ?>
            </td>
            <td style="padding:8px 12px;border-bottom:1px solid #f0f0f0;">
                <?php echo esc_html( $type_label ); ?>
            </td>
        </tr>
        <?php if ( $data['price'] ) : ?>
            <tr>
                <td style="padding:8px 12px;font-weight:bold;width:40%;">
                    <?php esc_html_e( 'Delivery Fee', 'space-core' ); ?>
                </td>
                <td style="padding:8px 12px;font-weight:600;">
                    <?php echo esc_html( number_format( (float) $data['price'], 3 ) . $currency ); ?>
                </td>
            </tr>
        <?php endif; ?>
    </table>
</div>
