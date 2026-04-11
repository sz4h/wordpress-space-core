<?php defined( 'ABSPATH' ) || exit; ?>
<div class="sc-stock-notifier" id="sc-sn-<?php echo esc_attr( $product_id ); ?>">
    <p class="sc-sn-heading"><?php esc_html_e( 'Notify me when available', 'space-core' ); ?></p>
    <form class="sc-sn-form" data-product="<?php echo esc_attr( $product_id ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">
        <?php if ( $collect_email ) : ?>
            <input type="email" name="sc_contact_email" placeholder="<?php esc_attr_e( 'Your email address', 'space-core' ); ?>" />
        <?php endif; ?>
        <?php if ( $collect_phone ) : ?>
            <input type="tel" name="sc_contact_phone" placeholder="<?php esc_attr_e( 'Your phone number', 'space-core' ); ?>" />
        <?php endif; ?>
        <button type="submit"><?php esc_html_e( 'Notify Me', 'space-core' ); ?></button>
        <span class="sc-sn-msg"></span>
    </form>
</div>
