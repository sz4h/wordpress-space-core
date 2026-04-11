<?php defined( 'ABSPATH' ) || exit; ?>
<div class="sc-ls-row-actions">
    <button type="button" class="button-link sc-ls-icon-action sc-save-row" data-icon="check" aria-label="<?php esc_attr_e( 'Save', 'space-core' ); ?>"><span class="sc-ls-material-icon" aria-hidden="true">check</span></button>
    <button type="button" class="button-link sc-ls-icon-action <?php echo $saved ? 'sc-delete-row' : 'sc-remove-new-row'; ?>" data-icon="close" aria-label="<?php echo esc_attr( $saved ? __( 'Delete', 'space-core' ) : __( 'Remove', 'space-core' ) ); ?>"><span class="sc-ls-material-icon" aria-hidden="true">close</span></button>
</div>
