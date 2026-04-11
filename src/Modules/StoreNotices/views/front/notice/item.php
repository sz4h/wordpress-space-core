<?php defined( 'ABSPATH' ) || exit; ?>
<div id="sc-notice-<?php echo esc_attr( $id ); ?>" class="sc-store-notice"
     style="background:<?php echo esc_attr( $bg ); ?>;color:<?php echo esc_attr( $text ); ?>;bottom:<?php echo (int) $bottom_offset; ?>px;"
     data-id="<?php echo esc_attr( $id ); ?>">

    <?php if ( $icon ) : ?>
        <span class="sc-notice-material-icon" aria-hidden="true"><?php echo esc_html( $icon ); ?></span>
    <?php endif; ?>

    <div class="sc-notice-body">
        <?php if ( $title ) : ?>
            <strong class="sc-notice-title"><?php echo esc_html( $title ); ?></strong>
        <?php endif; ?>
        <?php if ( $message ) : ?>
            <div class="sc-notice-msg"><?php echo wp_kses_post( $message ); ?></div>
        <?php endif; ?>
    </div>

    <?php if ( $dismiss ) : ?>
        <button type="button" class="sc-notice-dismiss"
                aria-label="<?php esc_attr_e( 'Dismiss', 'space-core' ); ?>"
                style="color:<?php echo esc_attr( $text ); ?>;">&#x2715;</button>
    <?php endif; ?>
</div>
