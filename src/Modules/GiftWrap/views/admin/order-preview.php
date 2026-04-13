<?php defined( 'ABSPATH' ) || exit; ?>
<# if ( data.sc_gift_wrap === 'yes' ) { #>
<div class="sc-gift-wrap-preview">
    <strong><?php esc_html_e( 'Gift Wrap', 'space-core' ); ?> ✓</strong>
    <# if ( data.sc_gift_message ) { #>
    <p>{{ data.sc_gift_message }}</p>
    <# } #>
</div>
<# } #>
