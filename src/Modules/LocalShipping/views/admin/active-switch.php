<?php defined( 'ABSPATH' ) || exit; ?>
<label class="sc-ls-switch">
    <input type="checkbox" class="sc-field" data-key="is_active" <?php checked( $checked ); ?>>
    <span class="sc-ls-switch-slider" aria-hidden="true"></span>
    <span class="screen-reader-text"><?php esc_html_e( 'Active', 'space-core' ); ?></span>
</label>
