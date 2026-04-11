<?php

defined( 'ABSPATH' ) || exit;

use Space\Core\Admin\SettingsAPI;

SettingsAPI::open_form( 'space_core_wa_group' );
?>
<h2><?php esc_html_e( 'WhatsApp Floating Button', 'space-core' ); ?></h2>
<table class="form-table" role="presentation">
    <tr>
        <th><?php esc_html_e( 'Phone Number', 'space-core' ); ?></th>
        <td><?php SettingsAPI::text( 'space_core_wa_group', 'space_core_whatsapp_float', 'phone', $options['phone'], '+9665XXXXXXXX' ); ?>
        <p class="description"><?php esc_html_e( 'International format without spaces. E.g. +9665XXXXXXXX', 'space-core' ); ?></p></td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Pre-filled Message', 'space-core' ); ?></th>
        <td><?php SettingsAPI::text( 'space_core_wa_group', 'space_core_whatsapp_float', 'message', $options['message'] ); ?></td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Button Color', 'space-core' ); ?></th>
        <td><?php SettingsAPI::color( 'space_core_whatsapp_float', 'color', $options['color'] ); ?></td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Load Animation', 'space-core' ); ?></th>
        <td><?php SettingsAPI::select( 'space_core_whatsapp_float', 'load_animation', $options['load_animation'], $anim_options ); ?></td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Hover Animation', 'space-core' ); ?></th>
        <td><?php SettingsAPI::select( 'space_core_whatsapp_float', 'hover_animation', $options['hover_animation'], $hover_options ); ?></td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Position (LTR)', 'space-core' ); ?></th>
        <td><?php SettingsAPI::select( 'space_core_whatsapp_float', 'position_ltr', $options['position_ltr'], $position_options ); ?></td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Position (RTL)', 'space-core' ); ?></th>
        <td><?php SettingsAPI::select( 'space_core_whatsapp_float', 'position_rtl', $options['position_rtl'], $position_options ); ?></td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Horizontal Margin (px)', 'space-core' ); ?></th>
        <td><?php SettingsAPI::number( 'space_core_whatsapp_float', 'margin_x', $options['margin_x'], 0, 200 ); ?></td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Vertical Margin (px)', 'space-core' ); ?></th>
        <td><?php SettingsAPI::number( 'space_core_whatsapp_float', 'margin_y', $options['margin_y'], 0, 200 ); ?></td>
    </tr>
</table>
<?php
SettingsAPI::close_form();
?>
