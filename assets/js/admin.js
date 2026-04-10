/* Space Core – Admin JS */
(function ($) {
    'use strict';

    // ── Color Pickers ─────────────────────────────────────────────
    $(function () {
        $('.sc-color-picker').wpColorPicker();
    });

    // ── Media Uploader ────────────────────────────────────────────
    $(document).on('click', '.sc-upload-image', function (e) {
        e.preventDefault();
        var targetId  = $(this).data('target');
        var $container = $(this).closest('.sc-image-field, td, div');
        var frame = wp.media({
            title:  'Select Image',
            button: { text: 'Use this image' },
            multiple: false,
        });
        frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            $('#' + targetId).val(attachment.id);
            var $img = $container.find('img');
            if ($img.length) {
                $img.attr('src', attachment.url);
            } else {
                $container.prepend('<img src="' + attachment.url + '" style="max-width:120px;display:block;margin-bottom:8px;" />');
            }
            $container.find('.sc-remove-image').show();
        });
        frame.open();
    });

    $(document).on('click', '.sc-remove-image', function (e) {
        e.preventDefault();
        var targetId = $(this).data('target');
        $('#' + targetId).val('');
        $(this).closest('.sc-image-field, td').find('img').remove();
        $(this).hide();
    });

    // ── Module card toggle highlight ──────────────────────────────
    $(document).on('change', '.sc-module-toggle input[type="checkbox"]', function () {
        $(this).closest('.sc-module-card').toggleClass('sc-module-enabled', this.checked);
    });

    // ══════════════════════════════════════════════════════════════
    // AJAX TABLE ENGINE
    // Handles: CPT, Custom Fields, Taxonomies
    // ══════════════════════════════════════════════════════════════

    var rowIndex = 1000; // unique index for new rows

    // ── Auto-slug from name/label ─────────────────────────────────
    $(document).on('input', '.sc-table-row .sc-field[data-field="name"], .sc-table-row .sc-field[data-field="label"]', function () {
        var $row  = $(this).closest('tr');
        var $slug = $row.find('.sc-slug-field[data-field="slug"], .sc-slug-field[data-field="key"]');
        // Only auto-fill if slug is empty (new row) or row is new.
        if ($row.hasClass('sc-new-row') && $slug.val() === '') {
            $slug.val(slugify($(this).val()));
        }
    });

    // ── Show/hide choices textarea based on type select ───────────
    $(document).on('change', '.sc-type-select', function () {
        var $cell = $(this).closest('tr').find('.sc-choices-cell');
        if ($(this).val() === 'select') {
            $cell.removeClass('sc-hidden');
        } else {
            $cell.addClass('sc-hidden');
        }
    });

    // ── Add row (template in separate <script> tag) ───────────────
    $(document).on('click', '.sc-add-row[data-template]', function () {
        var tableId  = $(this).data('table');
        var tplId    = $(this).data('template');
        var $table   = $('#' + tableId);
        var tpl      = $('#' + tplId).html();
        if (!tpl || !$table.length) return;

        tpl = tpl.replace(/__INDEX__/g, ++rowIndex);
        var $row = $(tpl);
        $table.find('tbody').append($row);
        $row.find('input[type="text"]:first').trigger('focus');
    });

    // ── Remove NEW (unsaved) row ──────────────────────────────────
    $(document).on('click', '.sc-remove-new-row', function () {
        $(this).closest('tr').remove();
    });

    // ── Delete SAVED row (AJAX) — legacy handler for CPT/CF/Taxonomy tables ──
    // These buttons carry data-action and data-nonce directly on the button.
    // LocalShipping uses the newer per-row handler added below (reads from table).
    $(document).on('click', '.sc-delete-row[data-action]', function () {
        if (!confirm('Delete this row?')) return;
        var $btn    = $(this);
        var $row    = $btn.closest('tr');
        var action  = $btn.data('action');
        var nonce   = $btn.data('nonce');
        var payload = { action: action, nonce: nonce };

        // Pass all data-* on the button as extra params (slug, key, post_type etc.)
        $.each($btn.data(), function (k, v) {
            if (k !== 'action' && k !== 'nonce') payload[k] = v;
        });

        $btn.prop('disabled', true);
        $.post(spaceCore.ajaxUrl, payload, function (res) {
            if (res.success) {
                $row.fadeOut(200, function () { $(this).remove(); });
            } else {
                alert(res.data.message || 'Error deleting row.');
                $btn.prop('disabled', false);
            }
        }).fail(function () {
            alert('Server error.');
            $btn.prop('disabled', false);
        });
    });

    // ── Collect all rows from a table ─────────────────────────────
    function collectRows(tableId) {
        var rows = [];
        $('#' + tableId + ' tbody tr.sc-table-row').each(function () {
            var $row = $(this);
            var row  = {};
            $row.find('.sc-field').each(function () {
                var field = $(this).data('field');
                if (!field) return;
                if ($(this).hasClass('sc-bool-field')) {
                    row[field] = $(this).is(':checked');
                } else if ($(this).hasClass('sc-multiselect')) {
                    row[field] = $(this).val() || [];
                } else {
                    row[field] = $(this).val();
                }
            });
            if (Object.keys(row).length) rows.push(row);
        });
        return rows;
    }

    // ── Save all rows ─────────────────────────────────────────────
    $(document).on('click', '.sc-save-table', function () {
        var $btn    = $(this);
        var tableId = $btn.data('table');
        var action  = $btn.data('action');
        var nonce   = $btn.data('nonce');
        var $status = $btn.siblings('.sc-save-status');
        var rows    = collectRows(tableId);

        $btn.prop('disabled', true);
        $status.text('Saving…').css('color', '#888');

        $.post(spaceCore.ajaxUrl, {
            action: action,
            nonce:  nonce,
            rows:   JSON.stringify(rows),
        }, function (res) {
            if (res.success) {
                $status.text(res.data.message || 'Saved!').css('color', '#2e7d32');
                // Mark all new rows as saved.
                $('#' + tableId + ' tbody tr.sc-new-row').removeClass('sc-new-row');
            } else {
                $status.text(res.data.message || 'Error saving.').css('color', '#c62828');
            }
        }).fail(function () {
            $status.text('Server error.').css('color', '#c62828');
        }).always(function () {
            $btn.prop('disabled', false);
            setTimeout(function () { $status.text(''); }, 4000);
        });
    });

    // ══════════════════════════════════════════════════════════════
    // CHECKOUT FIELDS – sortable + save all sections
    // ══════════════════════════════════════════════════════════════

    // Init jQuery UI sortable on checkout field tables.
    function initSortable() {
        $('.sc-sortable-body').sortable({
            handle: '.sc-sort-handle',
            axis: 'y',
            cursor: 'move',
            placeholder: 'sc-sort-placeholder',
            update: function () { /* priority recalculated on save */ },
        }).disableSelection();
    }
    $(initSortable);

    // Add custom row to a section table.
    $(document).on('click', '.sc-wcf-add-row', function () {
        var section = $(this).data('section');
        var $tbody  = $('#sc-wcf-' + section + '-table tbody');
        var $row    = $('<tr class="sc-table-row sc-custom-field sc-new-row" data-custom="1" data-section="' + section + '" data-priority="999">' +
            '<td class="sc-sort-handle" data-label="⠿">⠿</td>' +
            '<td data-label="Key"><input type="text" class="sc-field sc-slug-field" data-field="key" placeholder="my_field" /></td>' +
            '<td data-label="Label"><input type="text" class="sc-field" data-field="label" placeholder="My Field" /></td>' +
            '<td data-label="Type"><select class="sc-field" data-field="type">' +
                '<option value="text">Text</option><option value="email">Email</option><option value="tel">Phone</option>' +
                '<option value="textarea">Textarea</option><option value="select">Select</option>' +
                '<option value="checkbox">Checkbox</option><option value="hidden">Hidden</option>' +
            '</select></td>' +
            '<td data-label="Width"><select class="sc-field" data-field="width">' +
                '<option value="wide">Full (1 col)</option>' +
                '<option value="first">Left (2 col)</option>' +
                '<option value="last">Right (2 col)</option>' +
            '</select></td>' +
            '<td data-label="Required"><input type="checkbox" class="sc-field sc-bool-field" data-field="required" /></td>' +
            '<td data-label="Enabled"><input type="checkbox" class="sc-field sc-bool-field" data-field="enabled" checked /></td>' +
            '<td class="sc-row-actions"><button type="button" class="button button-small sc-wcf-delete-row sc-remove-new-row">Remove</button></td>' +
        '</tr>');
        $tbody.append($row);
    });

    // Delete custom row (new = just remove from DOM).
    $(document).on('click', '.sc-wcf-delete-row.sc-remove-new-row', function () {
        $(this).closest('tr').remove();
    });

    // Delete saved custom row.
    $(document).on('click', '.sc-wcf-delete-row:not(.sc-remove-new-row)', function () {
        if (!confirm('Delete this custom field?')) return;
        $(this).closest('tr').fadeOut(200, function () { $(this).remove(); });
    });

    // Collect all checkout field rows across all sections.
    function collectCheckoutRows() {
        var rows = [];
        $('.sc-sortable-body').each(function () {
            var section = $(this).closest('table').data('section');
            var priority = 10;
            $(this).find('tr.sc-table-row').each(function () {
                priority += 10;
                var $row    = $(this);
                var isCustom = $row.data('custom') === 1 || $row.data('custom') === '1';
                var row     = { section: section, priority: priority, custom: isCustom };
                $row.find('.sc-field').each(function () {
                    var field = $(this).data('field');
                    if (!field) return;
                    if ($(this).hasClass('sc-bool-field')) {
                        row[field] = $(this).is(':checked');
                    } else {
                        row[field] = $(this).val();
                    }
                });
                rows.push(row);
            });
        });
        return rows;
    }

    $(document).on('click', '.sc-wcf-save-all', function () {
        var $btn    = $(this);
        var action  = $btn.data('action');
        var nonce   = $btn.data('nonce');
        var $status = $btn.closest('.sc-wcf-global-footer').find('.sc-save-status');
        var rows    = collectCheckoutRows();

        $btn.prop('disabled', true);
        $status.text('Saving…').css('color', '#888');

        $.post(spaceCore.ajaxUrl, {
            action: action,
            nonce:  nonce,
            rows:   JSON.stringify(rows),
        }, function (res) {
            $status.text(res.success ? (res.data.message || 'Saved!') : (res.data.message || 'Error'))
                   .css('color', res.success ? '#2e7d32' : '#c62828');
        }).fail(function () {
            $status.text('Server error.').css('color', '#c62828');
        }).always(function () {
            $btn.prop('disabled', false);
            setTimeout(function () { $status.text(''); }, 4000);
        });
    });

    $(document).on('click', '.sc-wcf-reset', function () {
        if (!confirm('Reset all checkout field settings to WooCommerce defaults?')) return;
        var $btn    = $(this);
        var $status = $btn.closest('.sc-wcf-global-footer').find('.sc-save-status');
        $btn.prop('disabled', true);
        $.post(spaceCore.ajaxUrl, {
            action: $btn.data('action'),
            nonce:  $btn.data('nonce'),
        }, function (res) {
            if (res.success) {
                $status.text(res.data.message).css('color', '#2e7d32');
                setTimeout(function () { location.reload(); }, 1500);
            }
        }).always(function () { $btn.prop('disabled', false); });
    });

    // ══════════════════════════════════════════════════════════════
    // PER-ROW SAVE / DELETE (used by LocalShipping cities & areas)
    // Table must have: data-action-save, data-action-delete, data-nonce
    // Rows use: data-id (0 = new), .sc-field[data-key]
    // ══════════════════════════════════════════════════════════════

    // Add row — clones the hidden .sc-new-row-template inside the same table.
    $(document).on('click', '.sc-add-row[data-table]', function () {
        var tableId = $(this).data('table');
        var $table  = $('#' + tableId);
        var $tpl    = $table.find('.sc-new-row-template');
        if (!$table.length || !$tpl.length) return;

        var $row = $tpl.clone().removeClass('sc-new-row-template').removeAttr('style').show();
        $row.attr('data-id', '0');
        $table.find('tbody').append($row);
        $row.find('input[type="text"]:first').trigger('focus');
    });

    // Collect all sc-field values from a row into a plain object.
    function collectRowData($row) {
        var data = {};
        $row.find('.sc-field').each(function () {
            var key = $(this).data('key');
            if (!key) return;
            if ($(this).is('input[type="checkbox"]')) {
                data[key] = $(this).is(':checked') ? 1 : 0;
            } else {
                data[key] = $(this).val();
            }
        });
        return data;
    }

    // Save a single row.
    $(document).on('click', '.sc-save-row', function () {
        var $btn    = $(this);
        var $row    = $btn.closest('tr');
        var $table  = $row.closest('table.sc-ajax-table');
        var action  = $table.data('action-save');
        var nonce   = $table.data('nonce');
        var id      = parseInt($row.data('id'), 10) || 0;
        var rowData = collectRowData($row);

        if (!action) return;

        $btn.prop('disabled', true).text('…');

        var payload = $.extend({ action: action, nonce: nonce, id: id }, rowData);

        // Use scAdmin nonce URL if available.
        var ajaxUrl = (window.scAdmin && scAdmin.ajaxUrl) ? scAdmin.ajaxUrl : ajaxurl;

        $.post(ajaxUrl, payload, function (res) {
            if (res.success) {
                // Update row id from response (new row).
                if (res.data && res.data.id) {
                    $row.data('id', res.data.id).attr('data-id', res.data.id);
                }
                // Swap Remove button for Delete button on newly saved rows.
                $row.find('.sc-remove-new-row')
                    .removeClass('sc-remove-new-row')
                    .addClass('sc-delete-row')
                    .text('Delete')
                    .css('color', '#b32d2e');
                $btn.text('Save');
            } else {
                alert((res.data && res.data.message) || 'Error saving row.');
                $btn.text('Save');
            }
        }).fail(function () {
            alert('Server error.');
            $btn.text('Save');
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    // Delete a saved row — reads action/nonce from parent table (LocalShipping style).
    $(document).on('click', '.sc-delete-row:not([data-action])', function () {
        if (!confirm('Delete this row?')) return;
        var $btn   = $(this);
        var $row   = $btn.closest('tr');
        var $table = $row.closest('table.sc-ajax-table');
        var action = $table.data('action-delete');
        var nonce  = $table.data('nonce');
        var id     = parseInt($row.data('id'), 10) || 0;

        if (!action || !id) {
            // Fallback: button may have its own data-action / data-nonce.
            action = $btn.data('action') || action;
            nonce  = $btn.data('nonce') || nonce;
        }

        if (!id) { $row.remove(); return; }

        $btn.prop('disabled', true);
        var ajaxUrl = (window.scAdmin && scAdmin.ajaxUrl) ? scAdmin.ajaxUrl : ajaxurl;

        $.post(ajaxUrl, { action: action, nonce: nonce, id: id }, function (res) {
            if (res.success) {
                $row.fadeOut(200, function () { $(this).remove(); });
            } else {
                alert((res.data && res.data.message) || 'Error deleting.');
                $btn.prop('disabled', false);
            }
        }).fail(function () {
            alert('Server error.');
            $btn.prop('disabled', false);
        });
    });

    // ── Utility ───────────────────────────────────────────────────
    function slugify(str) {
        return str.toLowerCase()
            .replace(/[^\w\s-]/g, '')
            .replace(/[\s_-]+/g, '_')
            .replace(/^-+|-+$/g, '');
    }

}(jQuery));
