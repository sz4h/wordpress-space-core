<?php

defined( 'ABSPATH' ) || exit;

use Space\Core\Admin\SettingsAPI;

SettingsAPI::open_form( 'space_core_code_group' );
?>
<h2><?php esc_html_e( 'Custom CSS', 'space-core' ); ?></h2>
<table class="form-table" role="presentation">
    <tr>
        <th><?php esc_html_e( 'Scope', 'space-core' ); ?></th>
        <td><?php SettingsAPI::select( 'space_core_custom_code', 'css_scope', $options['css_scope'], $scope_options ); ?></td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'CSS Code', 'space-core' ); ?></th>
        <td><?php SettingsAPI::textarea( 'space_core_custom_code', 'css', $options['css'], 12 ); ?>
        <p class="description"><?php esc_html_e( 'Enter raw CSS without style tags.', 'space-core' ); ?></p></td>
    </tr>
</table>

<h2><?php esc_html_e( 'Custom JavaScript', 'space-core' ); ?></h2>
<table class="form-table" role="presentation">
    <tr>
        <th><?php esc_html_e( 'Scope', 'space-core' ); ?></th>
        <td><?php SettingsAPI::select( 'space_core_custom_code', 'js_scope', $options['js_scope'], $scope_options ); ?></td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'JS Code', 'space-core' ); ?></th>
        <td><?php SettingsAPI::textarea( 'space_core_custom_code', 'js', $options['js'], 12 ); ?>
        <p class="description"><?php esc_html_e( 'Enter raw JavaScript without script tags.', 'space-core' ); ?></p></td>
    </tr>
</table>
<?php
SettingsAPI::close_form();
?>
