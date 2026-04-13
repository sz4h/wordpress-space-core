<?php defined( 'ABSPATH' ) || exit; ?>
<p><strong><?php esc_html_e( 'Gift Wrap:', 'space-core' ); ?></strong> ✓</p>
<?php if ( $message ) : ?>
    <p><strong><?php esc_html_e( 'Gift Message:', 'space-core' ); ?></strong><br><?php echo esc_html( $message ); ?></p>
<?php endif; ?>
