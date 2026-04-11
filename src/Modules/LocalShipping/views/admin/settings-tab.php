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
            action: 'sc_save_ls_settings',
            nonce: scAdmin.nonce,
            express_enabled: $('input[name="express_enabled"]').is(':checked') ? 1 : 0,
        }, function (res) {
            $btn.prop('disabled', false);
            $status.text(res.success ? '<?php esc_html_e( 'Saved!', 'space-core' ); ?>' : '<?php esc_html_e( 'Error.', 'space-core' ); ?>');
            setTimeout(function () { $status.text(''); }, 2000);
        });
    });
});
</script>
