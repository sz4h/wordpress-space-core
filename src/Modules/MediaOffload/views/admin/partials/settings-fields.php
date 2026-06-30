<?php

defined( 'ABSPATH' ) || exit;

use Space\Core\Admin\SettingsAPI;
?>
<h3><?php esc_html_e( 'Settings', 'space-core' ); ?></h3>
<table class="form-table" role="presentation">
    <tr>
        <th><?php esc_html_e( 'Enabled', 'space-core' ); ?></th>
        <td>
            <?php SettingsAPI::checkbox( 'space_core_media_offload', 'enabled', $options['enabled'] ?? 0, __( 'Enable media offload for new and existing uploads.', 'space-core' ), [ 'id' => 'sc-mo-enabled' ] ); ?>
        </td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Storage adapter', 'space-core' ); ?></th>
        <td>
            <?php SettingsAPI::select( 'space_core_media_offload', 'adapter', $selected, $adapters, [ 'id' => 'sc-mo-adapter' ] ); ?>
        </td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Bucket / Space / Storage Zone', 'space-core' ); ?></th>
        <td>
            <?php SettingsAPI::text( 'space_core_media_offload_group', 'space_core_media_offload', 'bucket', $options['bucket'] ?? '', '', [ 'id' => 'sc-mo-bucket', 'class' => 'regular-text' ] ); ?>
        </td>
    </tr>
    <tr class="sc-mo-adapter-row" data-adapter="bunny do_spaces cloudflare_r2">
        <th><?php esc_html_e( 'Public base URL', 'space-core' ); ?></th>
        <td>
            <?php SettingsAPI::url( 'space_core_media_offload_group', 'space_core_media_offload', 'base_url', $options['base_url'] ?? '', 'https://cdn.example.com/media', [ 'id' => 'sc-mo-base-url', 'class' => 'regular-text' ] ); ?>
            <p class="description"><?php esc_html_e( 'Optional. When empty, the module will fall back to the adapter default URL shape.', 'space-core' ); ?></p>
        </td>
    </tr>
    <tr class="sc-mo-adapter-row" data-adapter="bunny do_spaces cloudflare_r2">
        <th><?php esc_html_e( 'Path prefix', 'space-core' ); ?></th>
        <td>
            <?php SettingsAPI::text( 'space_core_media_offload_group', 'space_core_media_offload', 'prefix', $options['prefix'] ?? '', 'media', [ 'id' => 'sc-mo-prefix', 'class' => 'regular-text' ] ); ?>
        </td>
    </tr>
    <tr class="sc-mo-adapter-row" data-adapter="bunny">
        <th><?php esc_html_e( 'Endpoint', 'space-core' ); ?></th>
        <td>
            <?php SettingsAPI::text( 'space_core_media_offload_group', 'space_core_media_offload', 'endpoint', $options['bunny_endpoint'] ?? $options['endpoint'] ?? '', 'storage.bunnycdn.com', [ 'id' => 'sc-mo-endpoint', 'class' => 'regular-text' ] ); ?>
            <p class="description"><?php esc_html_e( 'Bunny storage host, for example `storage.bunnycdn.com` or your storage endpoint.', 'space-core' ); ?></p>
        </td>
    </tr>
    <tr class="sc-mo-adapter-row" data-adapter="bunny">
        <th><?php esc_html_e( 'Access key', 'space-core' ); ?></th>
        <td>
            <input type="password" id="sc-mo-access-key" class="regular-text" placeholder="<?php esc_attr_e( 'Leave blank to keep the current saved key', 'space-core' ); ?>">
            <p class="description"><?php esc_html_e( 'Bunny storage API access key. The saved value is never shown again.', 'space-core' ); ?></p>
        </td>
    </tr>
    <tr class="sc-mo-adapter-row" data-adapter="do_spaces">
        <th><?php esc_html_e( 'Visibility', 'space-core' ); ?></th>
        <td>
            <?php SettingsAPI::select( 'space_core_media_offload', 'visibility', $options['visibility'] ?? 'public', [
                'public'  => __( 'Public', 'space-core' ),
                'private' => __( 'Private', 'space-core' ),
            ], [ 'id' => 'sc-mo-visibility' ] ); ?>
            <p class="description"><?php esc_html_e( 'Public adds `public-read` on upload. Private storage is saved as private, but this module does not generate signed delivery URLs in this pass.', 'space-core' ); ?></p>
        </td>
    </tr>
    <tr class="sc-mo-adapter-row" data-adapter="do_spaces">
        <th><?php esc_html_e( 'Region', 'space-core' ); ?></th>
        <td>
            <?php SettingsAPI::text( 'space_core_media_offload_group', 'space_core_media_offload', 'region', $options['do_region'] ?? $options['region'] ?? '', 'nyc3', [ 'id' => 'sc-mo-region', 'class' => 'regular-text' ] ); ?>
        </td>
    </tr>
    <tr class="sc-mo-adapter-row" data-adapter="do_spaces">
        <th><?php esc_html_e( 'Endpoint', 'space-core' ); ?></th>
        <td>
            <?php SettingsAPI::text( 'space_core_media_offload_group', 'space_core_media_offload', 'endpoint', $options['do_endpoint'] ?? $options['endpoint'] ?? '', 'https://nyc3.digitaloceanspaces.com', [ 'id' => 'sc-mo-do-endpoint', 'class' => 'regular-text' ] ); ?>
            <p class="description"><?php esc_html_e( 'Optional override for the API endpoint. Leave empty to derive it from the region.', 'space-core' ); ?></p>
        </td>
    </tr>
    <tr class="sc-mo-adapter-row" data-adapter="do_spaces">
        <th><?php esc_html_e( 'Access key', 'space-core' ); ?></th>
        <td>
            <input type="password" id="sc-mo-do-access-key" class="regular-text" placeholder="<?php esc_attr_e( 'Leave blank to keep the current saved key', 'space-core' ); ?>">
            <p class="description"><?php esc_html_e( 'DO Spaces access key. The saved value is never shown again.', 'space-core' ); ?></p>
        </td>
    </tr>
    <tr class="sc-mo-adapter-row" data-adapter="do_spaces">
        <th><?php esc_html_e( 'Secret key', 'space-core' ); ?></th>
        <td>
            <input type="password" id="sc-mo-secret-key" class="regular-text" placeholder="<?php esc_attr_e( 'Leave blank to keep the current saved secret', 'space-core' ); ?>">
            <p class="description"><?php esc_html_e( 'DO Spaces secret key. The saved value is never shown again.', 'space-core' ); ?></p>
        </td>
    </tr>
    <tr class="sc-mo-adapter-row" data-adapter="cloudflare_r2">
        <th><?php esc_html_e( 'Account endpoint', 'space-core' ); ?></th>
        <td>
            <?php SettingsAPI::text( 'space_core_media_offload_group', 'space_core_media_offload', 'r2_endpoint', $options['r2_endpoint'] ?? $options['endpoint'] ?? '', 'https://<account_id>.r2.cloudflarestorage.com', [ 'id' => 'sc-mo-r2-endpoint', 'class' => 'regular-text' ] ); ?>
            <p class="description"><?php esc_html_e( 'Your R2 S3 API endpoint, for example `https://<account_id>.r2.cloudflarestorage.com`. The region is fixed to `auto`.', 'space-core' ); ?></p>
        </td>
    </tr>
    <tr class="sc-mo-adapter-row" data-adapter="cloudflare_r2">
        <th><?php esc_html_e( 'Access key ID', 'space-core' ); ?></th>
        <td>
            <input type="password" id="sc-mo-r2-access-key" class="regular-text" placeholder="<?php esc_attr_e( 'Leave blank to keep the current saved key', 'space-core' ); ?>">
            <p class="description"><?php esc_html_e( 'R2 API token Access Key ID. The saved value is never shown again.', 'space-core' ); ?></p>
        </td>
    </tr>
    <tr class="sc-mo-adapter-row" data-adapter="cloudflare_r2">
        <th><?php esc_html_e( 'Secret access key', 'space-core' ); ?></th>
        <td>
            <input type="password" id="sc-mo-r2-secret-key" class="regular-text" placeholder="<?php esc_attr_e( 'Leave blank to keep the current saved secret', 'space-core' ); ?>">
            <p class="description"><?php esc_html_e( 'R2 API token Secret Access Key. Set a public base URL (custom domain or r2.dev) for public delivery.', 'space-core' ); ?></p>
        </td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Delete local files after offload', 'space-core' ); ?></th>
        <td>
            <?php SettingsAPI::checkbox( 'space_core_media_offload', 'delete_local', $options['delete_local'] ?? 0, __( 'Delete local originals and generated sizes after a successful upload.', 'space-core' ), [ 'id' => 'sc-mo-delete-local' ] ); ?>
        </td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Output sanitization', 'space-core' ); ?></th>
        <td>
            <?php SettingsAPI::checkbox( 'space_core_media_offload', 'sanitize_output', $options['sanitize_output'] ?? 0, __( 'Enable frontend/admin string sanitization to repair malformed media URLs.', 'space-core' ), [ 'id' => 'sc-mo-sanitize-output' ] ); ?>
            <p class="description"><?php esc_html_e( 'Disabled by default because it can be expensive on busy sites.', 'space-core' ); ?></p>
        </td>
    </tr>
</table>
