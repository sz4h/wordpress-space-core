<?php defined( 'ABSPATH' ) || exit; ?>
<div class="sc-gift-wrap-section" style="margin-bottom:20px;padding:16px;border:1px solid #e5e5e5;border-radius:4px;">
    <?php if ( $optional ) : ?>
        <label style="font-weight:600;cursor:pointer;">
            <input type="checkbox" id="sc-gift-wrap-check" name="sc_gift_wrap" value="1"
                   <?php checked( $session ); ?>>
            <?php echo esc_html( $label . $price_label ); ?>
        </label>
    <?php else : ?>
        <input type="hidden" name="sc_gift_wrap" value="1">
        <strong><?php echo esc_html( $label . $price_label ); ?></strong>
    <?php endif; ?>

    <div id="sc-gift-message-wrap" style="margin-top:12px;<?php echo ( $optional && ! $session ) ? 'display:none;' : ''; ?>">
        <label for="sc-gift-message" style="display:block;margin-bottom:4px;font-weight:600;">
            <?php echo esc_html( $msg_lbl ); ?>
        </label>
        <textarea id="sc-gift-message" name="sc_gift_message" rows="3"
                  style="width:100%;box-sizing:border-box;"
                  placeholder="<?php esc_attr_e( 'Write your message here…', 'space-core' ); ?>"><?php echo esc_textarea( WC()->session ? (string) WC()->session->get( 'sc_gift_message', '' ) : '' ); ?></textarea>
    </div>
</div>
