<?php

defined( 'ABSPATH' ) || exit;

use Space\Core\Admin\SettingsAPI;

SettingsAPI::open_form( 'space_core_pwa_group' );
?>
<h2><?php esc_html_e( 'PWA Settings', 'space-core' ); ?></h2>
<p>
    <?php
    printf(
        /* translators: 1: manifest URL 2: sw URL */
        esc_html__( 'Manifest: %1$s | Service Worker: %2$s', 'space-core' ),
        '<a href="' . esc_url( $manifest_url ) . '" target="_blank">' . esc_html( $manifest_url ) . '</a>',
        '<a href="' . esc_url( $service_worker_url ) . '" target="_blank">' . esc_html( $service_worker_url ) . '</a>'
    );
    ?>
</p>
<table class="form-table" role="presentation">
    <tr>
        <th><?php esc_html_e( 'App Name', 'space-core' ); ?></th>
        <td><?php SettingsAPI::text( 'space_core_pwa_group', 'space_core_pwa', 'name', $options['name'] ); ?></td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Short Name', 'space-core' ); ?></th>
        <td><?php SettingsAPI::text( 'space_core_pwa_group', 'space_core_pwa', 'short_name', $options['short_name'] ); ?></td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Theme Color', 'space-core' ); ?></th>
        <td><?php SettingsAPI::color( 'space_core_pwa', 'theme_color', $options['theme_color'] ); ?></td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Background Color', 'space-core' ); ?></th>
        <td><?php SettingsAPI::color( 'space_core_pwa', 'background_color', $options['background_color'] ); ?></td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'App Icon (PNG)', 'space-core' ); ?></th>
        <td>
            <input type="hidden" name="space_core_pwa[icon_id]" id="sc_pwa_icon_id" value="<?php echo absint( $options['icon_id'] ); ?>" />
            <?php if ( $options['icon_id'] ) : ?>
                <?php echo wp_get_attachment_image( $options['icon_id'], [ 80, 80 ] ); ?>
            <?php endif; ?>
            <button type="button" class="button sc-upload-image" data-target="sc_pwa_icon_id"><?php esc_html_e( 'Select Icon', 'space-core' ); ?></button>
            <p class="description"><?php esc_html_e( 'Recommend: 512×512 PNG. Will be served at 192×192 and 512×512.', 'space-core' ); ?></p>
        </td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Cache Strategy', 'space-core' ); ?></th>
        <td><?php SettingsAPI::select( 'space_core_pwa', 'cache_strategy', $options['cache_strategy'], $cache_options ); ?></td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Offline Fallback URL', 'space-core' ); ?></th>
        <td><?php SettingsAPI::text( 'space_core_pwa_group', 'space_core_pwa', 'offline_url', $options['offline_url'] ); ?></td>
    </tr>
</table>
<?php
SettingsAPI::close_form();
?>
