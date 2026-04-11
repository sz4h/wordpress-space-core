<?php defined( 'ABSPATH' ) || exit; ?>
<th>
    <span class="sc-ls-copy-heading">
        <span><?php echo esc_html( $label ); ?></span>
        <button type="button" class="button-link sc-ls-copy-column" data-key="<?php echo esc_attr( $field_key ); ?>" aria-label="<?php echo esc_attr( $aria_label ); ?>">
            <span class="sc-ls-material-icon" aria-hidden="true">save</span>
        </button>
    </span>
</th>
