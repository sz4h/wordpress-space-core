<?php

defined( 'ABSPATH' ) || exit;

use Space\Core\Admin\SettingsAPI;

$disabled_pts = (array) ( $options['disable_comments_post_types'] ?? [] );
?>
<div class="sc-main-config-wrap" style="max-width:700px;">
    <table class="form-table">
        <tr>
            <th><?php esc_html_e( 'Disable Help & Screen Options', 'space-core' ); ?></th>
            <td>
                <?php SettingsAPI::checkbox( 'space_core_main_config', 'disable_help', $options['disable_help'] ?? 0, __( 'Hide the Help tab and Screen Options button for all users.', 'space-core' ), [ 'id' => 'sc-mc-disable-help' ] ); ?>
            </td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Whitelabel WP Logo', 'space-core' ); ?></th>
            <td>
                <?php SettingsAPI::checkbox( 'space_core_main_config', 'whitelabel_logo', $options['whitelabel_logo'] ?? 0, __( 'Replace the WP logo in the admin bar with a site name.', 'space-core' ), [ 'id' => 'sc-mc-whitelabel' ] ); ?>
                <br><br>
                <label><?php esc_html_e( 'Site name to display:', 'space-core' ); ?>
                    <?php SettingsAPI::text( 'space_core_main_config_group', 'space_core_main_config', 'site_name', $options['site_name'] ?? get_bloginfo( 'name' ), get_bloginfo( 'name' ), [ 'id' => 'sc-mc-site-name' ] ); ?>
                </label>
            </td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Remove WP Version & Copyrights', 'space-core' ); ?></th>
            <td>
                <?php SettingsAPI::checkbox( 'space_core_main_config', 'remove_version', $options['remove_version'] ?? 0, __( 'Remove WordPress version from admin footer and generator meta tag.', 'space-core' ), [ 'id' => 'sc-mc-remove-version' ] ); ?>
                <br><br>
                <label><?php esc_html_e( 'Custom footer text:', 'space-core' ); ?>
                    <?php SettingsAPI::text( 'space_core_main_config_group', 'space_core_main_config', 'footer_text', $options['footer_text'] ?? '', __( 'Powered by Your Brand', 'space-core' ), [ 'id' => 'sc-mc-footer-text' ] ); ?>
                </label>
                <br><br>
                <label><?php esc_html_e( 'Custom footer URL:', 'space-core' ); ?>
                    <?php SettingsAPI::url( 'space_core_main_config_group', 'space_core_main_config', 'footer_url', $options['footer_url'] ?? '', 'https://example.com', [ 'id' => 'sc-mc-footer-url' ] ); ?>
                </label>
            </td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Admin Color Scheme', 'space-core' ); ?></th>
            <td>
                <?php SettingsAPI::select( 'space_core_main_config', 'admin_color', $options['admin_color'] ?? '', [ '' => __( '— User default —', 'space-core' ) ] + $colors, [ 'id' => 'sc-mc-admin-color' ] ); ?>
                <p class="description"><?php esc_html_e( 'Forces this color scheme for all users.', 'space-core' ); ?></p>
            </td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Disable Comments', 'space-core' ); ?></th>
            <td>
                <?php SettingsAPI::checkbox( 'space_core_main_config', 'disable_comments', $options['disable_comments'] ?? 0, __( 'Completely disable comments.', 'space-core' ), [ 'id' => 'sc-mc-disable-comments' ] ); ?>
                <br><br>
                <p class="description"><?php esc_html_e( 'Limit to specific post types (leave empty = all):', 'space-core' ); ?></p>
                <?php foreach ( $post_types as $pt ) : ?>
                    <label style="display:inline-block;margin-right:12px;">
                        <input type="checkbox" class="sc-mc-disable-pt" value="<?php echo esc_attr( $pt->name ); ?>"
                               <?php checked( in_array( $pt->name, $disabled_pts, true ) ); ?>>
                        <?php echo esc_html( $pt->label ); ?>
                    </label>
                <?php endforeach; ?>
            </td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Disable Pingback & Trackback', 'space-core' ); ?></th>
            <td>
                <?php SettingsAPI::checkbox( 'space_core_main_config', 'disable_pingback', $options['disable_pingback'] ?? 0, __( 'Remove pingback from XML-RPC, strip X-Pingback header, prevent self-pingbacks.', 'space-core' ), [ 'id' => 'sc-mc-disable-pingback' ] ); ?>
            </td>
        </tr>
    </table>

    <p>
        <button type="button" id="sc-mc-save" class="button button-primary">
            <?php esc_html_e( 'Save Configuration', 'space-core' ); ?>
        </button>
        <span id="sc-mc-status" style="margin-left:10px;font-weight:600;"></span>
    </p>
</div>

<script>
jQuery(function($){
    $('#sc-mc-save').on('click', function(){
        var $btn = $(this);
        var pts  = [];
        $('.sc-mc-disable-pt:checked').each(function(){ pts.push($(this).val()); });

        $btn.prop('disabled', true);
        $.post(spaceCore.ajaxUrl, {
            action: 'sc_save_main_config',
            nonce:  spaceCore.nonce,
            data:   JSON.stringify({
                disable_help:                 $('#sc-mc-disable-help').is(':checked') ? 1 : 0,
                whitelabel_logo:              $('#sc-mc-whitelabel').is(':checked') ? 1 : 0,
                site_name:                    $('#sc-mc-site-name').val(),
                remove_version:               $('#sc-mc-remove-version').is(':checked') ? 1 : 0,
                footer_text:                  $('#sc-mc-footer-text').val(),
                footer_url:                   $('#sc-mc-footer-url').val(),
                admin_color:                  $('#sc-mc-admin-color').val(),
                disable_comments:             $('#sc-mc-disable-comments').is(':checked') ? 1 : 0,
                disable_comments_post_types:  pts,
                disable_pingback:             $('#sc-mc-disable-pingback').is(':checked') ? 1 : 0,
            }),
        }, function(res){
            $('#sc-mc-status').text(res.success ? '<?php echo esc_js( __( 'Saved!', 'space-core' ) ); ?>' : '<?php echo esc_js( __( 'Error.', 'space-core' ) ); ?>')
                             .css('color', res.success ? '#2e7d32' : '#c62828');
            setTimeout(function(){ $('#sc-mc-status').text(''); }, 3000);
        }).always(function(){ $btn.prop('disabled', false); });
    });
});
</script>
