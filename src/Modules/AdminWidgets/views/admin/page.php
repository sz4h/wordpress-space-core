<?php

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap sc-wrap">
    <h1>
        <span class="dashicons dashicons-star-filled sc-logo-icon"></span>
        <?php esc_html_e( 'Admin Widgets', 'space-core' ); ?>
        <span class="sc-by"><?php esc_html_e( 'by Space Zone', 'space-core' ); ?></span>
    </h1>
    <div class="sc-tab-content" style="border-top:1px solid #c3c4c7;margin-top:16px;">
        <p class="description"><?php esc_html_e( 'Check widgets to hide from the WordPress dashboard.', 'space-core' ); ?></p>
        <p>
            <button type="button" class="button" id="sc-widgets-refresh">
                <span class="dashicons dashicons-update" style="vertical-align:text-top;"></span>
                <?php esc_html_e( 'Refresh Widget List', 'space-core' ); ?>
            </button>
            <span class="description"
                  style="margin-left:8px;"><?php esc_html_e( 'Click after installing or enabling plugins that add dashboard widgets.', 'space-core' ); ?></span>
        </p>
        <?php if ( empty( $widget_snapshot ) ) : ?>
            <div class="notice notice-info inline">
                <p><?php esc_html_e( 'No widgets detected yet. Click "Refresh Widget List" above.', 'space-core' ); ?></p>
            </div>
        <?php else : ?>
            <ul class="sc-cleaner-list" style="max-width:600px;">
                <?php foreach ( $widget_snapshot as $id => $title ) : ?>
                    <li>
                        <label>
                            <input type="checkbox" class="sc-hidden-widget"
                                   value="<?php echo esc_attr( $id ); ?>"
                                    <?php checked( in_array( $id, $hidden_widgets, true ) ); ?>>
                            <?php echo esc_html( $title ); ?>
                            <code style="font-size:.8rem;color:#888;"><?php echo esc_html( $id ); ?></code>
                        </label>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p style="margin-top:16px;">
                <button type="button" class="button button-primary" id="sc-widgets-save">
                    <?php esc_html_e( 'Save Widget Settings', 'space-core' ); ?>
                </button>
                <span id="sc-widgets-status" style="margin-left:10px;font-weight:600;"></span>
            </p>
        <?php endif; ?>
    </div>
</div>
<script>
jQuery(function ($) {
    $('#sc-widgets-refresh').on('click', function () {
        var $btn = $(this);
        $btn.prop('disabled', true);
        $.post(spaceCore.ajaxUrl, {
            action: 'sc_refresh_widgets',
            nonce: spaceCore.nonce,
        }, function (res) {
            if (res.success) {
                location.reload();
            } else {
                alert('<?php echo esc_js( __( 'Could not refresh widgets.', 'space-core' ) ); ?>');
            }
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    $('#sc-widgets-save').on('click', function () {
        var $btn = $(this);
        var hiddenWidgets = [];
        $('.sc-hidden-widget:checked').each(function () {
            hiddenWidgets.push($(this).val());
        });
        $btn.prop('disabled', true);
        $.post(spaceCore.ajaxUrl, {
            action: 'sc_save_admin_widgets',
            nonce: spaceCore.nonce,
            data: JSON.stringify(hiddenWidgets),
        }, function (res) {
            $('#sc-widgets-status').text(res.success ? '<?php echo esc_js( __( 'Saved!', 'space-core' ) ); ?>' : '<?php echo esc_js( __( 'Error.', 'space-core' ) ); ?>')
                .css('color', res.success ? '#2e7d32' : '#c62828');
            setTimeout(function () {
                $('#sc-widgets-status').text('');
            }, 3000);
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });
});
</script>
