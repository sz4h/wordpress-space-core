<?php

defined( 'ABSPATH' ) || exit;
?>
<div id="sc-mo-wrap" class="sc-mo-wrap" data-ready="<?php echo $is_ready ? '1' : '0'; ?>">
    <div class="sc-mo-header">
        <div>
            <h2><?php esc_html_e( 'Media Offload', 'space-core' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Offload uploaded media to external storage, rewrite media URLs, and run migration tools.', 'space-core' ); ?></p>
        </div>
        <div class="sc-mo-actions">
            <button type="button" id="sc-mo-save" class="button button-primary"><?php esc_html_e( 'Save Settings', 'space-core' ); ?></button>
            <button type="button" id="sc-mo-test" class="button"><?php esc_html_e( 'Test Connection', 'space-core' ); ?></button>
            <span id="<?php echo esc_attr( $page_status_id ); ?>" class="sc-mo-status" aria-live="polite"></span>
        </div>
    </div>

    <div class="sc-mo-grid">
        <div class="sc-mo-panel">
            <?php echo $this->view( 'admin/partials/settings-fields', [
                'options'  => $options,
                'adapters' => $adapters,
                'selected' => $selected,
            ] ); ?>
        </div>
        <div class="sc-mo-panel">
            <?php echo $this->view( 'admin/partials/tools', [
                'old_base_url' => $old_base_url,
                'new_base_url' => $new_base_url,
            ] ); ?>
        </div>
    </div>
</div>
