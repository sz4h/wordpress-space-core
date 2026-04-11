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

    <h2 style="margin-top:32px;"><?php esc_html_e( 'Login Page', 'space-core' ); ?></h2>
    <p class="description"><?php esc_html_e( 'Customize the WordPress login page appearance.', 'space-core' ); ?></p>
    <table class="form-table">
        <tr>
            <th><?php esc_html_e( 'Custom Logo', 'space-core' ); ?></th>
            <td>
                <?php
                $login_logo_id = absint( $options['login_custom_logo'] ?? 0 );
                $login_logo_url = $login_logo_id ? wp_get_attachment_image_url( $login_logo_id, 'medium' ) : '';
                ?>
                <div class="sc-image-field">
                    <?php if ( $login_logo_url ) : ?>
                        <img src="<?php echo esc_url( $login_logo_url ); ?>" style="max-width:120px;display:block;margin-bottom:8px;">
                    <?php endif; ?>
                    <input type="hidden" id="sc-mc-login-logo" value="<?php echo esc_attr( $login_logo_id ?: '' ); ?>">
                    <button type="button" class="button sc-upload-image" data-target="sc-mc-login-logo"><?php esc_html_e( 'Choose Logo', 'space-core' ); ?></button>
                    <button type="button" class="button sc-remove-image" data-target="sc-mc-login-logo" <?php echo $login_logo_id ? '' : 'style="display:none;"'; ?>><?php esc_html_e( 'Remove', 'space-core' ); ?></button>
                </div>
                <p class="description"><?php esc_html_e( 'Replaces the WordPress logo on the login page.', 'space-core' ); ?></p>
            </td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Background Color', 'space-core' ); ?></th>
            <td>
                <?php SettingsAPI::color( 'space_core_main_config_group', 'space_core_main_config', 'login_bg_color', $options['login_bg_color'] ?? '', [ 'id' => 'sc-mc-login-bg-color' ] ); ?>
            </td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Background Image', 'space-core' ); ?></th>
            <td>
                <?php
                $login_bg_id  = absint( $options['login_bg_image'] ?? 0 );
                $login_bg_url = $login_bg_id ? wp_get_attachment_image_url( $login_bg_id, 'medium' ) : '';
                ?>
                <div class="sc-image-field">
                    <?php if ( $login_bg_url ) : ?>
                        <img src="<?php echo esc_url( $login_bg_url ); ?>" style="max-width:120px;display:block;margin-bottom:8px;">
                    <?php endif; ?>
                    <input type="hidden" id="sc-mc-login-bg-image" value="<?php echo esc_attr( $login_bg_id ?: '' ); ?>">
                    <button type="button" class="button sc-upload-image" data-target="sc-mc-login-bg-image"><?php esc_html_e( 'Choose Image', 'space-core' ); ?></button>
                    <button type="button" class="button sc-remove-image" data-target="sc-mc-login-bg-image" <?php echo $login_bg_id ? '' : 'style="display:none;"'; ?>><?php esc_html_e( 'Remove', 'space-core' ); ?></button>
                </div>
            </td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Layout', 'space-core' ); ?></th>
            <td>
                <?php SettingsAPI::select( 'space_core_main_config', 'login_layout', $options['login_layout'] ?? 'standard', [
                    'standard' => __( 'Standard (centered)', 'space-core' ),
                    'side'     => __( 'Side Panel (logo left, form right)', 'space-core' ),
                ], [ 'id' => 'sc-mc-login-layout' ] ); ?>
                <p class="description"><?php esc_html_e( 'Side layout shows logo and background on the left, login form on the right.', 'space-core' ); ?></p>
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
                login_custom_logo:            $('#sc-mc-login-logo').val(),
                login_bg_color:               $('#sc-mc-login-bg-color').val(),
                login_bg_image:               $('#sc-mc-login-bg-image').val(),
                login_layout:                 $('#sc-mc-login-layout').val(),
            }),
        }, function(res){
            $('#sc-mc-status').text(res.success ? '<?php echo esc_js( __( 'Saved!', 'space-core' ) ); ?>' : '<?php echo esc_js( __( 'Error.', 'space-core' ) ); ?>')
                             .css('color', res.success ? '#2e7d32' : '#c62828');
            setTimeout(function(){ $('#sc-mc-status').text(''); }, 3000);
        }).always(function(){ $btn.prop('disabled', false); });
    });
});
</script>
