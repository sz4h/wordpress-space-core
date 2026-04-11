<?php

defined( 'ABSPATH' ) || exit;
?>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">
<div class="sc-sn-settings">
    <?php wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false ); ?>
    <?php wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false ); ?>

    <p class="description"><?php esc_html_e( 'Create store notices shown at the bottom of the frontend. Supports multilingual text, scheduling, and granular page targeting.', 'space-core' ); ?></p>

    <p class="sc-sn-actions"><button type="button" class="button button-primary" id="sc-sn-add">+ <?php esc_html_e( 'Add Notice', 'space-core' ); ?></button></p>

    <div id="sc-sn-list" class="sc-sn-list meta-box-sortables">
        <?php foreach ( $notices as $n ) : $this->render_notice_card( $n ); endforeach; ?>
    </div>
</div>

<template id="sc-sn-template"><?php $this->render_notice_card( [] ); ?></template>

<script>
jQuery(function ($) {
    var nonce = '<?php echo esc_js( $nonce ); ?>';
    var postboxPage = '<?php echo esc_js( $postbox_page ); ?>';
    var cardTitleLocale = '<?php echo esc_js( $this->notice_card_title_locale() ); ?>';
    var defaultCardTitle = '<?php echo esc_js( __( 'New Notice', 'space-core' ) ); ?>';
    var togglePanelLabel = '<?php echo esc_js( __( 'Toggle panel:', 'space-core' ) ); ?>';

    $('#sc-sn-add').on('click', function () {
        var html = document.getElementById('sc-sn-template').innerHTML;
        var $card = $(html);
        $('#sc-sn-list').prepend($card);
        prepareNewCard($card);
    });

    $(document).on('click', '.sc-sn-delete', function () {
        if (!confirm('<?php echo esc_js( __( 'Delete this notice?', 'space-core' ) ); ?>')) return;
        var $card = $(this).closest('.sc-sn-card');
        var id = $card.data('id');
        if (!id) { $card.remove(); return; }
        $.post(spaceCore.ajaxUrl, {action: 'sc_delete_store_notice', nonce: nonce, id: id}, function (res) {
            if (res.success) $card.slideUp(200, function () { $(this).remove(); });
        });
    });

    $(document).on('click', '.sc-sn-save', function () {
        var $card = $(this).closest('.sc-sn-card');
        var $btn = $(this);
        var id = $card.data('id');
        var get = function (cls) { return $card.find('.' + cls).val(); };
        var getIds = function (cls) {
            var vals = $card.find('.' + cls).val();
            return (vals || []).map(function (v) { return parseInt(v) || 0; }).filter(Boolean);
        };
        var data = {
            id: id || 0,
            title_en: get('sc-sn-title-en'),
            title_ar: get('sc-sn-title-ar'),
            message_en: get('sc-sn-msg-en'),
            message_ar: get('sc-sn-msg-ar'),
            icon: get('sc-sn-icon'),
            start_at: get('sc-sn-start'),
            end_at: get('sc-sn-end'),
            text_color: get('sc-sn-text-color'),
            background_color: get('sc-sn-bg-color'),
            is_dismissible: $card.find('.sc-sn-dismissible').is(':checked') ? 1 : 0,
            is_active: $card.find('.sc-sn-active').is(':checked') ? 1 : 0,
            pages_all: $card.find('.sc-sn-pages-all').is(':checked') ? 1 : 0,
            pages: getIds('sc-sn-pages'),
            posts_all: $card.find('.sc-sn-posts-all').is(':checked') ? 1 : 0,
            posts: getIds('sc-sn-posts'),
            products_all: $card.find('.sc-sn-products-all').is(':checked') ? 1 : 0,
            products: getIds('sc-sn-products'),
            categories_all: $card.find('.sc-sn-categories-all').is(':checked') ? 1 : 0,
            categories: getIds('sc-sn-categories'),
            product_cat_all: $card.find('.sc-sn-product-cat-all').is(':checked') ? 1 : 0,
            product_cat: getIds('sc-sn-product-cat'),
        };
        $btn.prop('disabled', true);
        $.post(spaceCore.ajaxUrl, { action: 'sc_save_store_notice', nonce: nonce, notice: JSON.stringify(data) }, function (res) {
            if (res.success) {
                if (res.data.id) {
                    $card.data('id', res.data.id);
                    $card.attr('data-id', res.data.id);
                    $card.attr('id', 'sc-sn-card-' + res.data.id);
                }
                $card.find('.sc-sn-status').text('<?php echo esc_js( __( 'Saved!', 'space-core' ) ); ?>').addClass('sc-sn-status-success');
                setTimeout(function () { $card.find('.sc-sn-status').text('').removeClass('sc-sn-status-success'); }, 3000);
            }
        }).always(function () { $btn.prop('disabled', false); });
    });

    $(document).on('change', '.sc-sn-all-chk', function () {
        var target = $(this).data('target');
        var hidden = $(this).is(':checked');
        var $select = $(this).closest('.sc-sn-dim').find('.' + target);
        $select.toggleClass('sc-sn-is-hidden', hidden);
        $select.next('.select2-container').toggleClass('sc-sn-is-hidden', hidden);
    });

    function updatePreviewColors($card) {
        $card.find('.sc-sn-preview').css({
            background: $card.find('.sc-sn-bg-color').val(),
            color: $card.find('.sc-sn-text-color').val(),
        });
    }

    $(document).on('input', '.sc-sn-bg-color,.sc-sn-text-color', function () {
        updatePreviewColors($(this).closest('.sc-sn-card'));
    });

    function cardTitleFromLocale($card) {
        var titleClass = cardTitleLocale === 'ar' ? 'sc-sn-title-ar' : 'sc-sn-title-en';
        var title = $.trim($card.find('.' + titleClass).val() || '');
        if (!title && titleClass !== 'sc-sn-title-en') title = $.trim($card.find('.sc-sn-title-en').val() || '');
        if (!title) title = $.trim($card.find('.sc-sn-title-ar').val() || '');
        return title || defaultCardTitle;
    }

    function updateCardTitle($card) {
        var title = cardTitleFromLocale($card);
        $card.find('.sc-sn-card-title').text(title);
        $card.find('.handlediv .screen-reader-text').text(togglePanelLabel + ' ' + title);
    }

    $(document).on('input', '.sc-sn-title-en,.sc-sn-title-ar', function () { updateCardTitle($(this).closest('.sc-sn-card')); });
    $(document).on('input', '.sc-sn-icon', function () {
        var $card = $(this).closest('.sc-sn-card');
        var val = $(this).val().trim();
        var $icon = $card.find('.sc-sn-preview-icon');
        $icon.text(val);
        $icon.toggleClass('sc-sn-is-hidden', !val);
    });
    $(document).on('input', '.sc-sn-title-en', function () { $(this).closest('.sc-sn-card').find('.sc-sn-preview-title').text($(this).val()); });
    $(document).on('input', '.sc-sn-msg-en', function () { $(this).closest('.sc-sn-card').find('.sc-sn-preview-msg').text($(this).val()); });

    function initSelect2($ctx) {
        var fn = $.fn.selectWoo || $.fn.select2;
        if (!fn) return;
        $ctx.find('.sc-sn-select2').each(function () {
            if ($(this).data('select2')) return;
            fn.call($(this), {
                width: '100%',
                placeholder: '<?php echo esc_js( __( 'Search...', 'space-core' ) ); ?>',
                allowClear: true,
                minimumInputLength: 2,
                ajax: {
                    url: spaceCore.ajaxUrl,
                    dataType: 'json',
                    delay: 300,
                    data: function (params) {
                        return { action: 'sc_search_items', nonce: nonce, type: $(this).data('type'), q: params.term };
                    }.bind(this),
                    processResults: function (data) { return {results: data.results || []}; }
                }
            });
            $(this).next('.select2-container').toggleClass('sc-sn-is-hidden', $(this).hasClass('sc-sn-is-hidden'));
        });
    }

    function bindNewPostbox($card) {
        if (!window.postboxes) return;
        $card.find('.hndle, .handlediv').on('click.scStoreNoticesPostbox', postboxes.handle_click);
        $card.find('.handlediv').attr('aria-expanded', !$card.hasClass('closed'));
        if ($('#sc-sn-list').data('ui-sortable')) $('#sc-sn-list').sortable('refresh');
    }

    function prepareNewCard($card) {
        $card.attr('id', 'sc-sn-card-new-' + Date.now() + '-' + Math.floor(Math.random() * 100000));
        updatePreviewColors($card);
        updateCardTitle($card);
        initSelect2($card);
        bindNewPostbox($card);
    }

    if (window.postboxes) postboxes.add_postbox_toggles(postboxPage);
    initSelect2($('#sc-sn-list'));
    $('#sc-sn-list .sc-sn-card').each(function () { var $card = $(this); updatePreviewColors($card); updateCardTitle($card); });
    $(document).on('toggle', 'details', function () { if (this.open) initSelect2($(this)); });
});
</script>
