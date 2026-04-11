<?php defined( 'ABSPATH' ) || exit; ?>
<form method="post" id="sc-ls-settings-form">
    <?php wp_nonce_field( 'sc_local_shipping_nonce', 'sc_ls_settings_nonce' ); ?>
    <table class="form-table">
        <tr>
            <th scope="row"><?php esc_html_e( 'Enable Express Delivery', 'space-core' ); ?></th>
            <td>
                <label><input type="checkbox" name="express_enabled" value="1" <?php checked( $express_enabled ); ?>><?php esc_html_e( 'Show an Express Delivery option at checkout with an additional fee per area.', 'space-core' ); ?></label>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e( 'Sync WC State / City Fields', 'space-core' ); ?></th>
            <td>
                <label><input type="checkbox" name="sync_state_city" value="1" <?php checked( $sync_state_city ); ?>><?php esc_html_e( 'When a country has configured cities, hide WC\'s "State/County" and "Town/City" fields and auto-populate them with the selected city and area names.', 'space-core' ); ?></label>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e( 'Locale for Stored Names', 'space-core' ); ?></th>
            <td>
                <select name="sync_locale">
                    <option value="auto" <?php selected( $sync_locale, 'auto' ); ?>><?php esc_html_e( 'Auto (site locale)', 'space-core' ); ?></option>
                    <option value="en"   <?php selected( $sync_locale, 'en' ); ?>><?php esc_html_e( 'English', 'space-core' ); ?></option>
                    <option value="ar"   <?php selected( $sync_locale, 'ar' ); ?>><?php esc_html_e( 'Arabic', 'space-core' ); ?></option>
                </select>
                <p class="description"><?php esc_html_e( 'Which language to use when writing area/city names into WC state and city fields.', 'space-core' ); ?></p>
            </td>
        </tr>
    </table>
    <p class="submit">
        <button type="button" id="sc-ls-save-settings" class="button button-primary"><?php esc_html_e( 'Save Settings', 'space-core' ); ?></button>
        <span class="sc-save-status" style="margin-left:10px;"></span>
    </p>
</form>
<script>
jQuery(function ($) {
    $('#sc-ls-save-settings').on('click', function () {
        var $btn = $(this);
        var $status = $('.sc-save-status');
        $btn.prop('disabled', true);
        $.post(scAdmin.ajaxUrl, {
            action:           'sc_save_ls_settings',
            nonce:            scAdmin.nonce,
            express_enabled:  $('input[name="express_enabled"]').is(':checked') ? 1 : 0,
            sync_state_city:  $('input[name="sync_state_city"]').is(':checked') ? 1 : 0,
            sync_locale:      $('select[name="sync_locale"]').val(),
        }, function (res) {
            $btn.prop('disabled', false);
            $status.text(res.success ? '<?php esc_html_e( 'Saved!', 'space-core' ); ?>' : '<?php esc_html_e( 'Error.', 'space-core' ); ?>');
            setTimeout(function () { $status.text(''); }, 2000);
        });
    });
});
</script>
