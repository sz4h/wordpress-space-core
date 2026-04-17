<?php

defined( 'ABSPATH' ) || exit;

use Space\Core\Admin\SettingsAPI;

SettingsAPI::open_form( 'space_core_code_group' );
?>
<h2><?php esc_html_e( 'Header Code', 'space-core' ); ?></h2>
<table class="form-table" role="presentation">
    <tr>
        <th><?php esc_html_e( 'Scope', 'space-core' ); ?></th>
        <td><?php SettingsAPI::select( 'space_core_custom_code', 'header_scope', $options['header_scope'], $scope_options ); ?></td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Header Code', 'space-core' ); ?></th>
        <td><?php SettingsAPI::textarea( 'space_core_custom_code', 'header_code', $options['header_code'], 12 ); ?>
        <p class="description"><?php esc_html_e( 'This is printed raw inside the document head for the selected scope. You can add meta tags, scripts, styles, or other head markup.', 'space-core' ); ?></p></td>
    </tr>
</table>

<h2><?php esc_html_e( 'Footer Code', 'space-core' ); ?></h2>
<table class="form-table" role="presentation">
    <tr>
        <th><?php esc_html_e( 'Scope', 'space-core' ); ?></th>
        <td><?php SettingsAPI::select( 'space_core_custom_code', 'footer_scope', $options['footer_scope'], $scope_options ); ?></td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Footer Code', 'space-core' ); ?></th>
        <td><?php SettingsAPI::textarea( 'space_core_custom_code', 'footer_code', $options['footer_code'], 12 ); ?>
        <p class="description"><?php esc_html_e( 'This is printed raw before the closing footer for the selected scope. You can add scripts, inline markup, or tracking code.', 'space-core' ); ?></p></td>
    </tr>
</table>
<?php
SettingsAPI::close_form();
?>
