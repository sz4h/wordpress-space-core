<?php defined( 'ABSPATH' ) || exit; ?>
<section class="sc-gift-wrap-details" style="margin-bottom:20px;">
    <h2><?php esc_html_e( 'Gift Wrap', 'space-core' ); ?></h2>
    <?php if ( $message ) : ?>
        <p><em><?php echo esc_html( $message ); ?></em></p>
    <?php endif; ?>
</section>
