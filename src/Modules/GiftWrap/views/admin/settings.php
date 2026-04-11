<?php

defined( 'ABSPATH' ) || exit;

use Space\Core\Admin\SettingsAPI;
?>
<div style="max-width:700px;">
    <table class="form-table">
        <tr>
            <th><?php esc_html_e( 'Checkbox Label', 'space-core' ); ?></th>
            <td>
                <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                    <label style="display:flex;align-items:center;gap:6px;">
                        <span style="min-width:26px;font-weight:600;font-size:.75rem;color:#666;">EN</span>
                        <?php SettingsAPI::text( 'space_core_gift_wrap_group', 'space_core_gift_wrap', 'label_en', $options['label_en'] ?? '', 'Add Gift Wrap', [ 'id' => 'sc-gw-label-en' ] ); ?>
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;">
                        <span style="min-width:26px;font-weight:600;font-size:.75rem;color:#666;">AR</span>
                        <?php SettingsAPI::text( 'space_core_gift_wrap_group', 'space_core_gift_wrap', 'label_ar', $options['label_ar'] ?? '', 'أضف تغليف الهدايا', [ 'id' => 'sc-gw-label-ar', 'dir' => 'rtl' ] ); ?>
                    </label>
                </div>
            </td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Message Field Label', 'space-core' ); ?></th>
            <td>
                <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                    <label style="display:flex;align-items:center;gap:6px;">
                        <span style="min-width:26px;font-weight:600;font-size:.75rem;color:#666;">EN</span>
                        <?php SettingsAPI::text( 'space_core_gift_wrap_group', 'space_core_gift_wrap', 'message_label_en', $options['message_label_en'] ?? '', 'Gift Message', [ 'id' => 'sc-gw-msg-en' ] ); ?>
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;">
                        <span style="min-width:26px;font-weight:600;font-size:.75rem;color:#666;">AR</span>
                        <?php SettingsAPI::text( 'space_core_gift_wrap_group', 'space_core_gift_wrap', 'message_label_ar', $options['message_label_ar'] ?? '', 'رسالة الهدية', [ 'id' => 'sc-gw-msg-ar', 'dir' => 'rtl' ] ); ?>
                    </label>
                </div>
            </td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Gift Wrap Price', 'space-core' ); ?></th>
            <td>
                <?php SettingsAPI::number( 'space_core_gift_wrap', 'price', $options['price'] ?? '0', 0, 9999, [ 'id' => 'sc-gw-price', 'step' => '0.01', 'style' => 'width:100px;' ] ); ?>
                <p class="description"><?php esc_html_e( 'Set to 0 for free gift wrap.', 'space-core' ); ?></p>
            </td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Force Gift Wrap', 'space-core' ); ?></th>
            <td>
                <?php SettingsAPI::checkbox( 'space_core_gift_wrap', 'force_wrap', $options['force_wrap'] ?? 0, __( 'Always apply gift wrap (no opt-in checkbox)', 'space-core' ), [ 'id' => 'sc-gw-force' ] ); ?>
            </td>
        </tr>
    </table>
    <p>
        <button type="button" id="sc-gw-save" class="button button-primary">
            <?php esc_html_e( 'Save', 'space-core' ); ?>
        </button>
        <span id="sc-gw-status" style="margin-left:10px;font-weight:600;"></span>
    </p>
</div>
<script>
jQuery(function($){
    $('#sc-gw-save').on('click', function(){
        var $btn = $(this);
        $btn.prop('disabled', true);
        $.post(spaceCore.ajaxUrl, {
            action: 'sc_save_gift_wrap',
            nonce:  spaceCore.nonce,
            data:   JSON.stringify({
                label_en:         $('#sc-gw-label-en').val(),
                label_ar:         $('#sc-gw-label-ar').val(),
                message_label_en: $('#sc-gw-msg-en').val(),
                message_label_ar: $('#sc-gw-msg-ar').val(),
                price:            $('#sc-gw-price').val(),
                force_wrap:       $('#sc-gw-force').is(':checked') ? 1 : 0,
            }),
        }, function(res){
            $('#sc-gw-status').text(res.success ? '<?php echo esc_js( __( 'Saved!', 'space-core' ) ); ?>' : '<?php echo esc_js( __( 'Error.', 'space-core' ) ); ?>')
                             .css('color', res.success ? '#2e7d32' : '#c62828');
            setTimeout(function(){ $('#sc-gw-status').text(''); }, 3000);
        }).always(function(){ $btn.prop('disabled', false); });
    });
});
</script>
