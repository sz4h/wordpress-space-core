<?php

defined( 'ABSPATH' ) || exit;
?>
<div class="sc-table-wrap">
    <p class="description"><?php esc_html_e( 'Add custom WooCommerce order statuses. Slug will be prefixed with wc- automatically.', 'space-core' ); ?></p>

    <table class="widefat sc-responsive-table" id="sc-order-status-table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Slug', 'space-core' ); ?></th>
                <th><?php esc_html_e( 'Label', 'space-core' ); ?></th>
                <th><?php esc_html_e( 'Color', 'space-core' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'space-core' ); ?></th>
            </tr>
        </thead>
        <tbody id="sc-os-tbody">
            <?php foreach ( $statuses as $s ) : ?>
                <tr data-slug="<?php echo esc_attr( $s['slug'] ); ?>">
                    <td data-label="Slug">
                        <code>wc-<?php echo esc_html( ltrim( $s['slug'], 'wc-' ) ); ?></code>
                        <input type="hidden" class="sc-os-slug" value="<?php echo esc_attr( $s['slug'] ); ?>">
                    </td>
                    <td data-label="Label">
                        <input type="text" class="sc-os-label" value="<?php echo esc_attr( $s['label'] ); ?>" placeholder="Label">
                    </td>
                    <td data-label="Color">
                        <input type="color" class="sc-os-color" value="<?php echo esc_attr( $s['color'] ?? '#888888' ); ?>">
                    </td>
                    <td>
                        <button type="button" class="button button-small sc-os-delete" style="color:#b32d2e;"
                                data-nonce="<?php echo esc_attr( $nonce ); ?>"
                                data-slug="<?php echo esc_attr( $s['slug'] ); ?>">
                            <?php esc_html_e( 'Delete', 'space-core' ); ?>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <p style="margin-top:12px;">
        <button type="button" id="sc-os-add" class="button">+ <?php esc_html_e( 'Add Status', 'space-core' ); ?></button>
        <button type="button" id="sc-os-save" class="button button-primary" style="margin-left:8px;"
                data-nonce="<?php echo esc_attr( $nonce ); ?>">
            <?php esc_html_e( 'Save All', 'space-core' ); ?>
        </button>
        <span id="sc-os-status" style="margin-left:10px;font-weight:600;"></span>
    </p>
</div>

<script>
jQuery(function($){
    var rowTpl = '<tr data-slug=""><td data-label="Slug"><input type="text" class="sc-os-slug" placeholder="packed" style="width:120px;"></td>' +
        '<td data-label="Label"><input type="text" class="sc-os-label" placeholder="Packed"></td>' +
        '<td data-label="Color"><input type="color" class="sc-os-color" value="#888888"></td>' +
        '<td><button type="button" class="button button-small sc-os-remove-row" style="color:#b32d2e;"><?php echo esc_js( __( 'Remove', 'space-core' ) ); ?></button></td></tr>';

    $('#sc-os-add').on('click', function(){
        $('#sc-os-tbody').append(rowTpl);
    });

    $(document).on('click', '.sc-os-remove-row', function(){ $(this).closest('tr').remove(); });

    $(document).on('click', '.sc-os-delete', function(){
        if(!confirm('<?php echo esc_js( __( 'Delete this status?', 'space-core' ) ); ?>')) return;
        var $btn = $(this), slug = $btn.data('slug'), nonce = $btn.data('nonce');
        $btn.prop('disabled', true);
        $.post(spaceCore.ajaxUrl, { action:'sc_delete_order_status', nonce:nonce, slug:slug }, function(res){
            if(res.success){ $btn.closest('tr').fadeOut(200, function(){ $(this).remove(); }); }
            else $btn.prop('disabled', false);
        });
    });

    $('#sc-os-save').on('click', function(){
        var $btn = $(this), nonce = $btn.data('nonce'), rows = [];
        $('#sc-os-tbody tr').each(function(){
            var slug  = $(this).find('.sc-os-slug').val();
            var label = $(this).find('.sc-os-label').val();
            var color = $(this).find('.sc-os-color').val();
            if(slug && label) rows.push({ slug:slug, label:label, color:color });
        });
        $btn.prop('disabled', true);
        $.post(spaceCore.ajaxUrl, { action:'sc_save_order_statuses', nonce:nonce, data:JSON.stringify(rows) }, function(res){
            $('#sc-os-status').text(res.success ? '<?php echo esc_js( __( 'Saved!', 'space-core' ) ); ?>' : '<?php echo esc_js( __( 'Error.', 'space-core' ) ); ?>')
                             .css('color', res.success ? '#2e7d32' : '#c62828');
            setTimeout(function(){ $('#sc-os-status').text(''); }, 3000);
        }).always(function(){ $btn.prop('disabled', false); });
    });
});
</script>
