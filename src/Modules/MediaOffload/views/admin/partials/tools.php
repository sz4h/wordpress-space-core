<?php

defined( 'ABSPATH' ) || exit;
?>
<h3><?php esc_html_e( 'Migration Tools', 'space-core' ); ?></h3>
<p class="description"><?php esc_html_e( 'Use dry runs first. Database rewrite tools can affect posts, metadata, options, and term descriptions.', 'space-core' ); ?></p>

<div class="sc-mo-card">
    <p><?php esc_html_e( 'Current local uploads base:', 'space-core' ); ?> <code><?php echo esc_html( $old_base_url ); ?></code></p>
    <p><?php esc_html_e( 'Current remote/public base:', 'space-core' ); ?> <code><?php echo esc_html( $new_base_url ); ?></code></p>
</div>

<div class="sc-mo-card">
    <div class="sc-mo-tool-actions">
        <button type="button" class="button" id="sc-mo-start-offload"><?php esc_html_e( 'Offload Existing Library', 'space-core' ); ?></button>
        <button type="button" class="button" id="sc-mo-start-migrate"><?php esc_html_e( 'Migrate Database URLs', 'space-core' ); ?></button>
        <button type="button" class="button" id="sc-mo-start-restore"><?php esc_html_e( 'Restore Local URLs', 'space-core' ); ?></button>
        <button type="button" class="button" id="sc-mo-start-fix-broken"><?php esc_html_e( 'Fix Broken URLs', 'space-core' ); ?></button>
        <button type="button" class="button button-primary" id="sc-mo-start-regenerate"><?php esc_html_e( 'Regenerate Images', 'space-core' ); ?></button>
    </div>
</div>

<div class="sc-mo-card">
    <h4><?php esc_html_e( 'Generic Find / Replace', 'space-core' ); ?></h4>
    <div class="sc-mo-find-replace">
        <textarea id="sc-mo-find-text" rows="4" placeholder="<?php esc_attr_e( 'Text to search for', 'space-core' ); ?>"></textarea>
        <textarea id="sc-mo-replace-text" rows="4" placeholder="<?php esc_attr_e( 'Replacement text', 'space-core' ); ?>"></textarea>
    </div>
    <p><button type="button" class="button" id="sc-mo-start-find-replace"><?php esc_html_e( 'Run Find / Replace', 'space-core' ); ?></button></p>
</div>

<div class="sc-mo-card">
    <div class="sc-mo-inline-fields">
        <label>
            <span><?php esc_html_e( 'Images per batch', 'space-core' ); ?></span>
            <input type="number" id="sc-mo-regenerate-limit" min="1" max="50" value="10">
        </label>
        <label class="sc-mo-checkbox-label">
            <input type="checkbox" id="sc-mo-dry-run">
            <span><?php esc_html_e( 'Dry run', 'space-core' ); ?></span>
        </label>
    </div>
</div>

<div id="sc-mo-progress" class="sc-mo-progress" aria-live="polite">
    <?php esc_html_e( 'Ready. Save settings, test the connection, then run the tools you need.', 'space-core' ); ?>
</div>
