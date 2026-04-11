<?php

defined( 'ABSPATH' ) || exit;

use Space\Core\Admin\SettingsAPI;
?>
<div style="max-width:600px;">
    <p class="description"><?php esc_html_e( 'Customize the print template used for A4 and thermal print pages.', 'space-core' ); ?></p>
    <table class="form-table" style="margin-top:16px;">
        <tr>
            <th><?php esc_html_e( 'Shop Name', 'space-core' ); ?></th>
            <td><?php SettingsAPI::text( 'space_core_print_orders_group', 'space_core_print_orders', 'shop_name', $options['shop_name'], '', [ 'id' => 'sc-po-shop-name' ] ); ?></td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Logo', 'space-core' ); ?></th>
            <td>
                <?php SettingsAPI::hidden( 'space_core_print_orders', 'logo_id', $options['logo_id'], [ 'id' => 'sc-po-logo-id' ] ); ?>
                <?php if ( $logo_url ) : ?>
                    <img id="sc-po-logo-preview" src="<?php echo esc_url( $logo_url ); ?>"
                         style="max-height:60px;display:block;margin-bottom:8px;">
                <?php else : ?>
                    <img id="sc-po-logo-preview" src="" style="max-height:60px;display:none;margin-bottom:8px;">
                <?php endif; ?>
                <button type="button" class="button" id="sc-po-logo-pick"><?php esc_html_e( 'Select Logo', 'space-core' ); ?></button>
                <button type="button" class="button" id="sc-po-logo-remove"
                        style="margin-left:4px;<?php echo $options['logo_id'] ? '' : 'display:none;'; ?>"><?php esc_html_e( 'Remove', 'space-core' ); ?></button>
            </td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Store Address', 'space-core' ); ?></th>
            <td><?php SettingsAPI::textarea( 'space_core_print_orders', 'store_address', $options['store_address'], 3, [ 'id' => 'sc-po-address' ] ); ?></td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Footer Message', 'space-core' ); ?></th>
            <td><?php SettingsAPI::text( 'space_core_print_orders_group', 'space_core_print_orders', 'footer_message', $options['footer_message'], '', [ 'id' => 'sc-po-footer' ] ); ?></td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Default Format', 'space-core' ); ?></th>
            <td>
                <?php SettingsAPI::select( 'space_core_print_orders', 'default_format', $options['default_format'], [
                    'a4'      => __( 'A4', 'space-core' ),
                    'thermal' => __( 'Thermal 80mm', 'space-core' ),
                ], [ 'id' => 'sc-po-format' ] ); ?>
            </td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Print Currency', 'space-core' ); ?></th>
            <td>
                <?php SettingsAPI::select( 'space_core_print_orders', 'print_currency', $options['print_currency'] ?? 'order', [
                    'order'   => __( 'Order currency (as placed by customer)', 'space-core' ),
                    'default' => __( 'Default currency (convert back using saved rate)', 'space-core' ),
                ], [ 'id' => 'sc-po-print-currency' ] ); ?>
                <p class="description"><?php esc_html_e( 'Applies only when Multi-Currency module is active and the order was placed in a non-default currency.', 'space-core' ); ?></p>
            </td>
        </tr>
    </table>

    <p style="margin-top:16px;">
        <button type="button" class="button button-primary" id="sc-po-save"><?php esc_html_e( 'Save Settings', 'space-core' ); ?></button>
        <span id="sc-po-status" style="margin-left:10px;font-weight:600;"></span>
    </p>

    <hr style="margin:24px 0;">
    <p><?php printf( esc_html__( 'Print buttons are added to the %s (row actions and bulk actions).', 'space-core' ), '<a href="' . esc_url( admin_url( 'edit.php?post_type=shop_order' ) ) . '">' . esc_html__( 'Orders list', 'space-core' ) . '</a>' ); ?></p>
</div>

<script>
jQuery(function ($) {
    $('#sc-po-logo-pick').on('click', function () {
        var frame = wp.media({
            title: '<?php echo esc_js( __( 'Select Logo', 'space-core' ) ); ?>',
            button: {text: '<?php echo esc_js( __( 'Use this image', 'space-core' ) ); ?>'},
            multiple: false
        });
        frame.on('select', function () {
            var att = frame.state().get('selection').first().toJSON();
            $('#sc-po-logo-id').val(att.id);
            $('#sc-po-logo-preview').attr('src', att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url).show();
            $('#sc-po-logo-remove').show();
        });
        frame.open();
    });
    $('#sc-po-logo-remove').on('click', function () {
        $('#sc-po-logo-id').val(0);
        $('#sc-po-logo-preview').attr('src', '').hide();
        $(this).hide();
    });

    $('#sc-po-save').on('click', function () {
        var $btn = $(this);
        $btn.prop('disabled', true);
        $.post(spaceCore.ajaxUrl, {
            action: 'sc_save_print_settings',
            nonce: spaceCore.nonce,
            shop_name: $('#sc-po-shop-name').val(),
            logo_id: $('#sc-po-logo-id').val(),
            store_address: $('#sc-po-address').val(),
            footer_message: $('#sc-po-footer').val(),
            default_format: $('#sc-po-format').val(),
            print_currency: $('#sc-po-print-currency').val(),
        }, function (res) {
            $('#sc-po-status').text(res.success ? '<?php echo esc_js( __( 'Saved!', 'space-core' ) ); ?>' : '<?php echo esc_js( __( 'Error.', 'space-core' ) ); ?>')
                .css('color', res.success ? '#2e7d32' : '#c62828');
            setTimeout(function () {
                $('#sc-po-status').text('');
            }, 3000);
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });
});
</script>
