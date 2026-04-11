<?php

defined( 'ABSPATH' ) || exit;
?>
<div style="max-width:720px;">
    <p class="description"><?php esc_html_e( 'Configure the mobile bottom navigation bar. Use Material Icons icon names (e.g. home, shopping_cart, inventory_2). The "Match" field is a URL fragment used to detect the active tab.', 'space-core' ); ?></p>

    <table class="widefat" id="sc-nav-items" style="margin-top:12px;">
        <thead>
            <tr>
                <th style="width:30px;"></th>
                <th><?php esc_html_e( 'Label EN', 'space-core' ); ?></th>
                <th><?php esc_html_e( 'Label AR', 'space-core' ); ?></th>
                <th><?php esc_html_e( 'URL', 'space-core' ); ?></th>
                <th><?php esc_html_e( 'Icon (Material)', 'space-core' ); ?></th>
                <th><?php esc_html_e( 'Match (URL fragment)', 'space-core' ); ?></th>
                <th></th>
            </tr>
        </thead>
        <tbody id="sc-nav-tbody">
            <?php foreach ( $items as $item ) : ?>
                <tr class="sc-nav-row">
                    <td><span class="dashicons dashicons-move" style="cursor:grab;color:#bbb;"></span></td>
                    <td><input type="text" class="sc-nav-label-en regular-text" value="<?php echo esc_attr( is_array( $item['label'] ) ? ( $item['label']['en'] ?? '' ) : $item['label'] ); ?>" placeholder="Label (EN)"></td>
                    <td><input type="text" class="sc-nav-label-ar regular-text" value="<?php echo esc_attr( is_array( $item['label'] ) ? ( $item['label']['ar'] ?? '' ) : '' ); ?>" placeholder="اسم (AR)" dir="rtl"></td>
                    <td><input type="text" class="sc-nav-url-inp regular-text" value="<?php echo esc_attr( $item['url'] ); ?>"></td>
                    <td><input type="text" class="sc-nav-icon-inp" value="<?php echo esc_attr( $item['icon'] ); ?>" style="width:120px;"> <span class="sc-material-icon" style="vertical-align:middle;font-family:'Material Symbols Outlined';font-size:20px;"><?php echo esc_html( $item['icon'] ); ?></span></td>
                    <td><input type="text" class="sc-nav-match-inp" value="<?php echo esc_attr( $item['match'] ?? '' ); ?>" style="width:160px;"></td>
                    <td><button type="button" class="button sc-nav-remove" style="color:#c62828;"><?php esc_html_e( 'Remove', 'space-core' ); ?></button></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <p style="margin-top:12px;">
        <button type="button" class="button" id="sc-nav-add">+ <?php esc_html_e( 'Add Item', 'space-core' ); ?></button>
        <button type="button" class="button button-primary" id="sc-nav-save" style="margin-left:8px;">
            <?php esc_html_e( 'Save', 'space-core' ); ?>
        </button>
        <span id="sc-nav-status" style="margin-left:10px;font-weight:600;"></span>
    </p>
</div>

<script>
jQuery(function($){
    if ($.fn.sortable) {
        $('#sc-nav-tbody').sortable({ handle: '.dashicons-move', axis: 'y' });
    }

    $(document).on('input', '.sc-nav-icon-inp', function(){
        $(this).next('.sc-material-icon').text($(this).val());
    });

    $('#sc-nav-add').on('click', function(){
        var row = '<tr class="sc-nav-row">' +
            '<td><span class="dashicons dashicons-move" style="cursor:grab;color:#bbb;"></span></td>' +
            '<td><input type="text" class="sc-nav-label-en regular-text" value="" placeholder="Label (EN)"></td>' +
            '<td><input type="text" class="sc-nav-label-ar regular-text" value="" placeholder="اسم (AR)" dir="rtl"></td>' +
            '<td><input type="text" class="sc-nav-url-inp regular-text" value=""></td>' +
            '<td><input type="text" class="sc-nav-icon-inp" value="home" style="width:120px;"> <span class="sc-material-icon" style="vertical-align:middle;font-family:\'Material Icons\';font-size:20px;">home</span></td>' +
            '<td><input type="text" class="sc-nav-match-inp" value="" style="width:160px;"></td>' +
            '<td><button type="button" class="button sc-nav-remove" style="color:#c62828;"><?php echo esc_js( __( 'Remove', 'space-core' ) ); ?></button></td>' +
            '</tr>';
        $('#sc-nav-tbody').append(row);
    });

    $(document).on('click', '.sc-nav-remove', function(){
        $(this).closest('tr').remove();
    });

    $('#sc-nav-save').on('click', function(){
        var $btn = $(this);
        var items = [];
        $('#sc-nav-tbody .sc-nav-row').each(function(){
            items.push({
                label: {
                    en: $(this).find('.sc-nav-label-en').val(),
                    ar: $(this).find('.sc-nav-label-ar').val(),
                },
                url:   $(this).find('.sc-nav-url-inp').val(),
                icon:  $(this).find('.sc-nav-icon-inp').val(),
                match: $(this).find('.sc-nav-match-inp').val(),
            });
        });
        $btn.prop('disabled', true);
        $.post(spaceCore.ajaxUrl, {
            action: 'sc_save_admin_nav',
            nonce:  spaceCore.nonce,
            items:  JSON.stringify(items),
        }, function(res){
            $('#sc-nav-status').text(res.success ? '<?php echo esc_js( __( 'Saved!', 'space-core' ) ); ?>' : '<?php echo esc_js( __( 'Error.', 'space-core' ) ); ?>')
                .css('color', res.success ? '#2e7d32' : '#c62828');
            setTimeout(function(){ $('#sc-nav-status').text(''); }, 3000);
        }).always(function(){ $btn.prop('disabled', false); });
    });
});
</script>
