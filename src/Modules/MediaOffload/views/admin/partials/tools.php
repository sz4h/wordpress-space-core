<?php

defined( 'ABSPATH' ) || exit;

use Space\Core\Admin\SettingsAPI;
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
    <h4><?php esc_html_e( 'Transfer Between Providers', 'space-core' ); ?></h4>
    <p class="description"><?php esc_html_e( 'Copy already-offloaded files from a source provider to the active (destination) provider configured above. Object keys are preserved, so afterwards point the destination public base URL above and run Find / Replace (source host -> destination host) to rewrite database URLs.', 'space-core' ); ?></p>
    <table class="form-table" role="presentation">
        <tr>
            <th><?php esc_html_e( 'Source provider', 'space-core' ); ?></th>
            <td>
                <?php SettingsAPI::select( 'space_core_media_offload_transfer', 'src_adapter', 'bunny', [
                    'bunny'         => __( 'Bunny Storage / BunnyCDN', 'space-core' ),
                    'do_spaces'     => __( 'DO Spaces', 'space-core' ),
                    'cloudflare_r2' => __( 'Cloudflare R2', 'space-core' ),
                ], [ 'id' => 'sc-mo-src-adapter' ] ); ?>
            </td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Source bucket', 'space-core' ); ?></th>
            <td><input type="text" id="sc-mo-src-bucket" class="regular-text"></td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Source endpoint', 'space-core' ); ?></th>
            <td>
                <input type="text" id="sc-mo-src-endpoint" class="regular-text" placeholder="storage.bunnycdn.com">
                <p class="description"><?php esc_html_e( 'Bunny storage host, DO Spaces endpoint, or the R2 account endpoint (https://<account_id>.r2.cloudflarestorage.com).', 'space-core' ); ?></p>
            </td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Source region', 'space-core' ); ?></th>
            <td>
                <input type="text" id="sc-mo-src-region" class="regular-text" placeholder="nyc3">
                <p class="description"><?php esc_html_e( 'Required for DO Spaces. Ignored for Bunny and R2 (R2 always uses auto).', 'space-core' ); ?></p>
            </td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Source access key', 'space-core' ); ?></th>
            <td><input type="password" id="sc-mo-src-access-key" class="regular-text" autocomplete="off"></td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Source secret key', 'space-core' ); ?></th>
            <td>
                <input type="password" id="sc-mo-src-secret-key" class="regular-text" autocomplete="off">
                <p class="description"><?php esc_html_e( 'Leave blank for Bunny (uses the access key only).', 'space-core' ); ?></p>
            </td>
        </tr>
    </table>
    <p>
        <label class="sc-mo-checkbox-label">
            <input type="checkbox" id="sc-mo-transfer-delete-source">
            <span><?php esc_html_e( 'Delete each file from the source after a successful copy', 'space-core' ); ?></span>
        </label>
    </p>
    <p><button type="button" class="button" id="sc-mo-start-transfer"><?php esc_html_e( 'Transfer Files To Destination', 'space-core' ); ?></button></p>
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
