/**
 * Multi-Currency — Admin + Frontend JS
 *
 * Admin (scMCAdmin):
 *   - Currency table CRUD (save, delete, set-default, reorder)
 *   - GCC seeder button
 *   - Settings form save
 *   - Effective-rate live preview
 *
 * Frontend (scMC):
 *   - Currency switcher dropdown AJAX + page reload
 */
(function ($) {
    'use strict';

    // =========================================================================
    // Shared helpers
    // =========================================================================

    function showMsg($el, text, isError) {
        $el.text(text).css('color', isError ? '#c62828' : '#2e7d32');
        setTimeout(function () { $el.text(''); }, 4000);
    }

    // =========================================================================
    // FRONTEND — currency switcher
    // =========================================================================

    if (typeof scMC !== 'undefined') {
        $(document).on('change', '.sc-currency-select', function () {
            var $sel  = $(this);
            var code  = $sel.val();
            var ajax  = scMC.ajaxUrl;
            var nonce = scMC.nonce;

            $sel.prop('disabled', true);

            $.post(ajax, {
                action:   'sc_switch_currency',
                nonce:    nonce,
                currency: code
            }, function (res) {
                if (res.success) {
                    window.location.reload();
                } else {
                    $sel.prop('disabled', false);
                }
            }).fail(function () {
                $sel.prop('disabled', false);
            });
        });
    }

    // =========================================================================
    // ADMIN — only runs when scMCAdmin is defined (currencies settings page)
    // =========================================================================

    if (typeof scMCAdmin === 'undefined') { return; }

    var ajax  = scMCAdmin.ajaxUrl;
    var nonce = scMCAdmin.nonce;
    var i18n  = scMCAdmin.i18n || {};

    // -------------------------------------------------------------------------
    // Effective-rate live update (rate + modifier → effective)
    // -------------------------------------------------------------------------

    function updateEffective($row) {
        var rate     = parseFloat($row.find('[name="rate"]').val()) || 0;
        var modifier = parseFloat($row.find('[name="rate_modifier"]').val()) || 0;
        $row.find('.sc-mc-effective').text((rate + modifier).toFixed(4));
    }

    $(document).on('input', '.sc-mc-rate, .sc-mc-modifier', function () {
        updateEffective($(this).closest('tr'));
    });

    // -------------------------------------------------------------------------
    // Drag-to-reorder
    // -------------------------------------------------------------------------

    $('#sc-mc-tbody').sortable({
        handle: '.sc-mc-sort-handle',
        axis:   'y',
        update: function () {
            var ids = [];
            $('#sc-mc-tbody tr[data-id]').each(function () {
                var id = $(this).data('id');
                if (id) { ids.push(id); }
            });
            $.post(ajax, { action: 'sc_mc_reorder', nonce: nonce, ids: ids });
        }
    });

    // -------------------------------------------------------------------------
    // Save currency row
    // -------------------------------------------------------------------------

    $(document).on('click', '.sc-mc-save-btn', function () {
        var $btn = $(this);
        var $row = $btn.closest('tr');
        var id   = $row.data('id') || '';
        var $msg = $('#sc-mc-msg');

        var data = { action: 'sc_mc_save_currency', nonce: nonce, id: id };
        $row.find('.sc-mc-field').each(function () {
            data[$(this).attr('name')] = $(this).val();
        });

        $btn.prop('disabled', true);

        $.post(ajax, data, function (res) {
            if (res.success) {
                if (!id && res.data && res.data.id) {
                    $row.attr('data-id', res.data.id);
                    $row.find('.sc-mc-save-btn, .sc-mc-delete-btn, .sc-mc-set-default-btn').attr('data-id', res.data.id);
                }
                $row.removeClass('sc-mc-new-row');
                $('#sc-mc-empty-row').remove();
                showMsg($msg, i18n.saved || 'Saved!', false);
            } else {
                showMsg($msg, (res.data && res.data.message) || i18n.error, true);
            }
        }).fail(function () {
            showMsg($msg, i18n.error, true);
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    // -------------------------------------------------------------------------
    // Delete currency row
    // -------------------------------------------------------------------------

    $(document).on('click', '.sc-mc-delete-btn', function () {
        var $btn = $(this);
        var id   = $btn.data('id');
        var $msg = $('#sc-mc-msg');

        if (!id || !window.confirm(i18n.confirmDelete || 'Delete this currency?')) {
            return;
        }

        $btn.prop('disabled', true);

        $.post(ajax, { action: 'sc_mc_delete_currency', nonce: nonce, id: id }, function (res) {
            if (res.success) {
                $btn.closest('tr').fadeOut(300, function () { $(this).remove(); });
            } else {
                showMsg($msg, (res.data && res.data.message) || i18n.error, true);
                $btn.prop('disabled', false);
            }
        }).fail(function () {
            $btn.prop('disabled', false);
        });
    });

    // -------------------------------------------------------------------------
    // Set default currency
    // -------------------------------------------------------------------------

    $(document).on('click', '.sc-mc-set-default-btn', function () {
        var $btn = $(this);
        var id   = $btn.data('id');
        var $msg = $('#sc-mc-msg');

        $btn.prop('disabled', true);

        $.post(ajax, { action: 'sc_mc_set_default', nonce: nonce, id: id }, function (res) {
            if (res.success) {
                // Reload to reflect ★ / ☆ changes in all rows.
                location.reload();
            } else {
                showMsg($msg, (res.data && res.data.message) || i18n.error, true);
                $btn.prop('disabled', false);
            }
        }).fail(function () {
            $btn.prop('disabled', false);
        });
    });

    // -------------------------------------------------------------------------
    // Add new currency row (from template)
    // -------------------------------------------------------------------------

    $('#sc-mc-add-btn').on('click', function () {
        var $template = $('#sc-mc-row-template');
        if (!$template.length) { return; }
        var html = $template.html();
        var $newRow = $(html);
        $newRow.addClass('sc-mc-new-row').css('background', '#fffde7');
        $newRow.attr('data-id', '');
        $newRow.find('[data-id]').attr('data-id', '');
        // Remove delete button from new unsaved row
        $newRow.find('.sc-mc-delete-btn').remove();
        $('#sc-mc-tbody').append($newRow);
        $('#sc-mc-empty-row').remove();
        $newRow.find('[name="currency_code"]').trigger('focus');
    });

    // -------------------------------------------------------------------------
    // Seed GCC currencies
    // -------------------------------------------------------------------------

    $('#sc-mc-seed-btn').on('click', function () {
        var $btn = $(this);
        var $msg = $('#sc-mc-msg');
        $btn.prop('disabled', true);

        $.post(ajax, { action: 'sc_mc_seed', nonce: nonce }, function (res) {
            if (res.success) {
                showMsg($msg, (res.data && res.data.message) || i18n.seeded, false);
                // Reload to show seeded rows.
                setTimeout(function () { location.reload(); }, 800);
            } else {
                showMsg($msg, (res.data && res.data.message) || i18n.error, true);
                $btn.prop('disabled', false);
            }
        }).fail(function () {
            showMsg($msg, i18n.error, true);
            $btn.prop('disabled', false);
        });
    });

    // -------------------------------------------------------------------------
    // Save settings tab
    // -------------------------------------------------------------------------

    $('#sc-mc-save-settings').on('click', function () {
        var $btn = $(this);
        var $msg = $('#sc-mc-settings-msg');

        var data = {
            rate_api_url:           $('#sc-mc-api-url').val(),
            rate_api_key:           $('#sc-mc-api-key').val(),
            rate_api_base_currency: $('#sc-mc-api-base').val(),
            rate_cron_period:       $('#sc-mc-cron-period').val(),
            shortcode_show_flag:    $('#sc-mc-show-flag').is(':checked') ? 1 : 0,
            shortcode_show_code:    $('#sc-mc-show-code').is(':checked') ? 1 : 0,
            shortcode_show_name:    $('#sc-mc-show-name').is(':checked') ? 1 : 0,
            shortcode_show_symbol:  $('#sc-mc-show-symbol').is(':checked') ? 1 : 0,
        };

        $btn.prop('disabled', true);

        $.post(ajax, {
            action: 'sc_mc_save_settings',
            nonce:  nonce,
            data:   JSON.stringify(data)
        }, function (res) {
            if (res.success) {
                showMsg($msg, (res.data && res.data.message) || i18n.saved, false);
            } else {
                showMsg($msg, (res.data && res.data.message) || i18n.error, true);
            }
        }).fail(function () {
            showMsg($msg, i18n.error, true);
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

}(jQuery));
