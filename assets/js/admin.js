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
    $(document).on('input', '.sc-table-row .sc-field[data-field="name"], .sc-table-row .sc-field[data-field="label"], .sc-table-row .sc-field[data-field="label_en"]', function () {
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
            '<td data-label="Label EN"><input type="text" class="sc-field" data-field="label_en" placeholder="My Field" style="width:110px;" /></td>' +
            '<td data-label="Label AR"><input type="text" class="sc-field" data-field="label_ar" placeholder="حقل" dir="rtl" style="width:110px;" /></td>' +
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
            '<td data-label="Countries"><input type="text" class="sc-field" data-field="show_countries" placeholder="KW,SA,AE" style="width:90px;" /></td>' +
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
        if ($table.data('country') !== undefined) {
            $row.find('.sc-field[data-key="country_code"]').val($table.data('country'));
        }
        if ($table.data('selected-city') !== undefined) {
            $row.attr('data-city-id', $table.data('selected-city'));
            $row.find('.sc-field[data-key="city_id"]').val(String($table.data('selected-city')));
        }
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

    function adminAjaxUrl() {
        return (window.scAdmin && scAdmin.ajaxUrl) ? scAdmin.ajaxUrl : ajaxurl;
    }

    function adminNonce($table) {
        if (window.scAdmin && scAdmin.nonce) return scAdmin.nonce;
        return $table && $table.length ? $table.data('nonce') : '';
    }

    function setActionButtonBusy($btn, isBusy, fallbackText) {
        var $icon = $btn.find('.sc-ls-material-icon');
        if ($icon.length) {
            $btn.toggleClass('sc-ls-icon-action-busy', isBusy);
            $icon.text(isBusy ? 'hourglass_empty' : ($btn.data('icon') || 'check'));
            return;
        }

        $btn.text(isBusy ? '…' : fallbackText);
    }

    function markAjaxTableRowSaved($row, res) {
        if (res.data && res.data.id) {
            $row.data('id', res.data.id).attr('data-id', res.data.id);
        }
        $row.find('.sc-remove-new-row')
            .removeClass('sc-remove-new-row')
            .addClass('sc-delete-row')
            .attr('aria-label', 'Delete')
            .css('color', '#b32d2e');
        $row.find('.sc-delete-row .sc-ls-material-icon').text('close');
    }

    function saveAjaxTableRow($row) {
        var $table  = $row.closest('table.sc-ajax-table');
        var action  = $table.data('action-save');
        var nonce   = $table.data('nonce');
        var id      = parseInt($row.data('id'), 10) || 0;
        var rowData = collectRowData($row);
        var request = $.Deferred();

        if (!action) {
            request.reject('Missing save action.');
            return request.promise();
        }

        var payload = $.extend({ action: action, nonce: nonce, id: id }, rowData);

        $.post(adminAjaxUrl(), payload, function (res) {
            if (res.success) {
                markAjaxTableRowSaved($row, res);
                request.resolve(res);
            } else {
                request.reject((res.data && res.data.message) || 'Error saving row.');
            }
        }).fail(function () {
            request.reject('Server error.');
        });

        return request.promise();
    }

    // Save a single row.
    $(document).on('click', '.sc-save-row', function () {
        var $btn = $(this);
        var $row = $btn.closest('tr');

        $btn.prop('disabled', true);
        setActionButtonBusy($btn, true, 'Save');

        saveAjaxTableRow($row).done(function () {
            setActionButtonBusy($btn, false, 'Save');
        }).fail(function (message) {
            alert(message || 'Error saving row.');
            setActionButtonBusy($btn, false, 'Save');
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    function findRowField($row, key) {
        return $row.find('.sc-field').filter(function () {
            return $(this).data('key') === key;
        }).first();
    }

    function visibleAjaxRows($table) {
        return $table.find('tbody tr')
            .not('.sc-new-row-template')
            .not('.sc-ls-empty-row')
            .filter(function () {
                return $(this).is(':visible');
            });
    }

    function flashLocalShippingStatus($table, message, color) {
        var $status = $table.closest('.sc-ls-areas-panel').find('.sc-ls-save-all-status');
        if (!$status.length) return;

        clearTimeout($status.data('scTimer'));
        $status.text(message).css('color', color || '#646970');
        $status.data('scTimer', setTimeout(function () {
            $status.text('');
        }, 4000));
    }

    $(document).on('click', '.sc-ls-copy-column', function () {
        var key    = $(this).data('key');
        var $table = $(this).closest('table.sc-ajax-table');
        var $rows  = visibleAjaxRows($table);

        if (!$rows.length) {
            flashLocalShippingStatus($table, 'Add an area before copying.', '#c62828');
            return;
        }
        if ($rows.length < 2) {
            flashLocalShippingStatus($table, 'No target rows to update.', '#646970');
            return;
        }

        var $source = findRowField($rows.first(), key);
        if (!$source.length) return;

        $rows.slice(1).each(function () {
            var $target = findRowField($(this), key);
            if (!$target.length) return;

            if ($source.is('input[type="checkbox"]')) {
                $target.prop('checked', $source.is(':checked')).trigger('change');
            } else {
                $target.val($source.val()).trigger('input').trigger('change');
            }
        });

        flashLocalShippingStatus($table, 'Copied. Use Save All to persist changes.', '#646970');
    });

    $(document).on('click', '.sc-ls-save-visible', function () {
        var $btn      = $(this);
        var tableId   = $btn.data('table');
        var $table    = $('#' + tableId);
        var $status   = $btn.closest('.sc-ls-area-actions').find('.sc-ls-save-all-status');
        var $rows     = visibleAjaxRows($table);
        var total     = $rows.length;
        var completed = 0;

        if (!total) {
            $status.text('No rows to save.').css('color', '#646970');
            return;
        }

        $btn.prop('disabled', true);
        $table.find('.sc-save-row').prop('disabled', true);
        $status.text('Saving…').css('color', '#888');

        function finish() {
            $status.text('Saved!').css('color', '#2e7d32');
            $btn.prop('disabled', false);
            $table.find('.sc-save-row').prop('disabled', false);
            setTimeout(function () {
                $status.text('');
            }, 4000);
        }

        function fail(message) {
            $status.text(message || 'Error saving row.').css('color', '#c62828');
            $btn.prop('disabled', false);
            $table.find('.sc-save-row').prop('disabled', false);
        }

        function saveNext(index) {
            if (index >= total) {
                finish();
                return;
            }

            saveAjaxTableRow($rows.eq(index)).done(function () {
                completed += 1;
                $status.text('Saving ' + completed + '/' + total + '…').css('color', '#888');
                saveNext(index + 1);
            }).fail(fail);
        }

        saveNext(0);
    });

    function setLocalShippingLoading($scope, isLoading) {
        $scope.toggleClass('sc-ls-is-loading', isLoading);
    }

    function updateAreasControls(selectedCityId) {
        var disabled = !selectedCityId;
        $('.sc-ls-area-actions .sc-add-row, .sc-ls-area-actions .sc-ls-save-visible').prop('disabled', disabled);
    }

    function markSelectedCity(cityId) {
        $('.sc-ls-city-link')
            .removeClass('sc-ls-city-link-active')
            .removeAttr('aria-current')
            .filter(function () {
                return parseInt($(this).data('city-id'), 10) === parseInt(cityId, 10);
            })
            .addClass('sc-ls-city-link-active')
            .attr('aria-current', 'page');
    }

    function loadLocalShippingAreas(cityId, country) {
        var $table = $('#sc-areas-table');
        if (!$table.length) return;

        var $panel = $table.closest('.sc-ls-areas-panel');
        setLocalShippingLoading($panel, true);

        $.post(adminAjaxUrl(), {
            action: 'sc_get_ls_areas',
            nonce: adminNonce($table),
            city_id: cityId || 0,
            country: country || $('#sc-ls-areas-country').val() || '',
        }, function (res) {
            if (!res.success) {
                alert((res.data && res.data.message) || 'Error loading areas.');
                return;
            }

            var data = res.data || {};
            $table.find('tbody').html(data.rows || '');
            $table.data('selected-city', data.selected_city_id || 0).attr('data-selected-city', data.selected_city_id || 0);
            $table.data('country', data.country || '').attr('data-country', data.country || '');
            $('.sc-ls-areas-title').text(data.title || 'Areas');
            markSelectedCity(data.selected_city_id || 0);
            updateAreasControls(data.selected_city_id || 0);
        }).fail(function () {
            alert('Server error.');
        }).always(function () {
            setLocalShippingLoading($panel, false);
        });
    }

    function loadLocalShippingCities(country, target) {
        var $table = target === 'areas' ? $('#sc-areas-table') : $('#sc-cities-table');
        var $scope = target === 'areas' ? $('.sc-ls-areas-layout') : $('#sc-cities-table').closest('.sc-table-wrap');

        setLocalShippingLoading($scope, true);

        $.post(adminAjaxUrl(), {
            action: 'sc_get_ls_cities',
            nonce: adminNonce($table),
            country: country || '',
        }, function (res) {
            if (!res.success) {
                alert((res.data && res.data.message) || 'Error loading cities.');
                return;
            }

            var data = res.data || {};
            if (target === 'areas') {
                $('.sc-ls-city-menu .sc-ls-city-list').replaceWith(data.city_menu || '<ul class="sc-ls-city-list"></ul>');
                loadLocalShippingAreas(data.first_city_id || 0, data.country || country || '');
            } else {
                $('#sc-cities-table').data('country', data.country || '').attr('data-country', data.country || '');
                $('#sc-cities-table tbody').html(data.rows || '');
            }
        }).fail(function () {
            alert('Server error.');
        }).always(function () {
            setLocalShippingLoading($scope, false);
        });
    }

    $(document).on('change', '.sc-ls-country-select', function () {
        var $select = $(this);
        loadLocalShippingCities($select.val(), $select.data('target'));
    });

    $(document).on('click', '.sc-ls-city-link', function (e) {
        if (!$('#sc-areas-table').length) return;

        e.preventDefault();
        loadLocalShippingAreas($(this).data('city-id'), $('#sc-ls-areas-country').val() || '');
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

        $.post(adminAjaxUrl(), { action: action, nonce: nonce, id: id }, function (res) {
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

    // ══════════════════════════════════════════════════════════════
    // ADMIN MENU ORGANIZER
    // ══════════════════════════════════════════════════════════════

    function adminMenuConfig() {
        return window.spaceAdminMenu || {};
    }

    function adminMenuI18n(key, fallback) {
        var config = adminMenuConfig();
        return (config.i18n && config.i18n[key]) ? config.i18n[key] : fallback;
    }

    function adminMenuNonce() {
        var config = adminMenuConfig();
        if (config.nonce) return config.nonce;
        return window.spaceCore ? spaceCore.nonce : '';
    }

    function escapeAdminMenuHtml(value) {
        return String(value === undefined || value === null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function adminMenuNewId(prefix) {
        return prefix + ':' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8);
    }

    function adminMenuRolesHtml(selectedRoles) {
        var roles = adminMenuConfig().roles || {};
        var selected = selectedRoles || [];
        var html = '<fieldset class="sc-am-roles"><legend>' + escapeAdminMenuHtml(adminMenuI18n('roles', 'Roles')) + '</legend>';

        $.each(roles, function (role, label) {
            var checked = selected.indexOf(role) !== -1 ? ' checked' : '';
            html += '<label><input type="checkbox" class="sc-am-role" value="' + escapeAdminMenuHtml(role) + '"' + checked + '> ' + escapeAdminMenuHtml(label) + '</label>';
        });

        return html + '</fieldset>';
    }

    function adminMenuVisibilityOptions(selected) {
        var options = {
            show: 'Show',
            hide_all: 'Hide from all roles',
            hide_roles: 'Hide for selected roles',
            hide_except_roles: 'Show only selected roles'
        };
        var html = '';

        $.each(options, function (value, label) {
            html += '<option value="' + value + '"' + (selected === value ? ' selected' : '') + '>' + escapeAdminMenuHtml(label) + '</option>';
        });

        return html;
    }

    function adminMenuControlsHtml(item) {
        var type = item.type || 'menu';
        var visibility = item.visibility_mode || 'show';
        var html = '<div class="sc-am-controls">';

        if (type !== 'separator') {
            html += '<label class="sc-am-field">' +
                '<span>' + escapeAdminMenuHtml(adminMenuI18n('custom_url', 'Custom URL')) + '</span>' +
                '<input type="url" class="sc-am-url" value="' + escapeAdminMenuHtml(item.url || '') + '">' +
                '</label>';
            html += '<label class="sc-am-checkbox">' +
                '<input type="checkbox" class="sc-am-open-new"' + (item.open_new ? ' checked' : '') + '> ' +
                escapeAdminMenuHtml(adminMenuI18n('open_new', 'Open in new window')) +
                '</label>';
            if (type === 'custom') {
                html += '<label class="sc-am-field">' +
                    '<span>' + escapeAdminMenuHtml(adminMenuI18n('icon', 'Dashicon')) + '</span>' +
                    '<input type="text" class="sc-am-icon" value="' + escapeAdminMenuHtml(item.icon || 'dashicons-admin-links') + '" placeholder="dashicons-admin-links">' +
                    '</label>';
            }
        }

        html += '<label class="sc-am-field">' +
            '<span>' + escapeAdminMenuHtml(adminMenuI18n('visibility', 'Visibility')) + '</span>' +
            '<select class="sc-am-visibility-mode">' + adminMenuVisibilityOptions(visibility) + '</select>' +
            '</label>';
        html += adminMenuRolesHtml(item.roles || []);
        html += '</div>';

        return html;
    }

    function adminMenuTopItemHtml(item) {
        var type = item.type || 'custom';
        var label = item.label || (type === 'separator' ? adminMenuI18n('separator', '-- Separator --') : adminMenuI18n('custom_link', 'Custom Link'));
        var readonly = type === 'separator' ? ' readonly' : '';
        var checked = item.visibility_mode && item.visibility_mode !== 'show' ? ' checked' : '';
        var meta = '';

        if (type === 'custom') {
            meta = '<span class="sc-am-meta">[ ' + escapeAdminMenuHtml(adminMenuI18n('custom_url', 'Custom URL')) + ' ]</span>';
        } else if (type === 'promoted_submenu') {
            meta = '<span class="sc-am-meta sc-am-meta-moved">[ ' + escapeAdminMenuHtml(adminMenuI18n('moved_out', 'Moved out')) + ' ]</span>';
        }

        return '<li class="sc-am-item sc-am-item-' + escapeAdminMenuHtml(type) + '" data-id="' + escapeAdminMenuHtml(item.id) + '" data-type="' + escapeAdminMenuHtml(type) + '" data-parent="' + escapeAdminMenuHtml(item.parent || '') + '" data-slug="' + escapeAdminMenuHtml(item.slug || '') + '">' +
            '<div class="sc-am-item-main">' +
            '<span class="sc-am-drag dashicons dashicons-menu" aria-hidden="true"></span>' +
            '<div class="sc-am-title">' +
            '<input type="text" class="sc-am-label' + (type === 'separator' ? ' sc-am-label-readonly' : '') + '" value="' + escapeAdminMenuHtml(label) + '"' + readonly + '>' +
            meta +
            '</div>' +
            '<label class="sc-am-switch" title="Hide item"><input type="checkbox" class="sc-am-hide-toggle"' + checked + '><span class="sc-am-switch-slider"></span></label>' +
            '<button type="button" class="button-link sc-am-expand" aria-expanded="false"><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span><span class="screen-reader-text">Toggle settings</span></button>' +
            '</div>' +
            '<div class="sc-am-details" hidden>' + adminMenuControlsHtml(item) + '</div>' +
            '</li>';
    }

    function adminMenuSubmenuItemHtml(item) {
        var visibility = item.visibility_mode || 'show';
        var checked = visibility !== 'show' ? ' checked' : '';

        return '<li class="sc-am-submenu-item" data-id="' + escapeAdminMenuHtml(item.id) + '" data-type="submenu" data-parent="' + escapeAdminMenuHtml(item.parent || '') + '" data-slug="' + escapeAdminMenuHtml(item.slug || '') + '">' +
            '<div class="sc-am-submenu-main">' +
            '<span class="sc-am-submenu-drag dashicons dashicons-menu" aria-hidden="true"></span>' +
            '<input type="text" class="sc-am-label" value="' + escapeAdminMenuHtml(item.label || '') + '">' +
            '<label class="sc-am-switch sc-am-switch-small" title="Hide item"><input type="checkbox" class="sc-am-hide-toggle"' + checked + '><span class="sc-am-switch-slider"></span></label>' +
            '</div>' +
            '<div class="sc-am-submenu-controls">' + adminMenuControlsHtml(item) + '</div>' +
            '</li>';
    }

    function adminMenuControlRoot($item) {
        if ($item.hasClass('sc-am-submenu-item')) {
            return $item.children('.sc-am-submenu-controls').children('.sc-am-controls');
        }

        return $item.children('.sc-am-details').children('.sc-am-controls');
    }

    function syncAdminMenuRoles($item) {
        var $controls = adminMenuControlRoot($item);
        var mode = $controls.find('.sc-am-visibility-mode').val();
        $controls.find('.sc-am-roles').toggleClass('sc-am-roles-disabled', mode === 'show' || mode === 'hide_all');
    }

    function refreshAdminMenuSortable($list) {
        if ($.fn.sortable && $list.data('ui-sortable')) {
            $list.sortable('refresh');
        }
    }

    function refreshAdminMenuSortables() {
        refreshAdminMenuSortable($('#sc-am-menu-list'));
        $('.sc-am-submenu-list').each(function () {
            refreshAdminMenuSortable($(this));
        });
    }

    function promotedAdminMenuData($item) {
        var data = collectAdminMenuItem($item);

        data.id = String($item.data('id') || '');
        data.type = 'promoted_submenu';
        data.parent = String($item.data('parent') || data.parent || '');
        data.slug = String($item.data('slug') || data.slug || '');

        return data;
    }

    function submenuAdminMenuData($item) {
        var data = collectAdminMenuItem($item);

        data.id = String($item.data('id') || '');
        data.type = 'submenu';
        data.parent = String($item.data('parent') || data.parent || '');
        data.slug = String($item.data('slug') || data.slug || '');

        return data;
    }

    function promoteAdminMenuSubmenu($item) {
        var data = promotedAdminMenuData($item);
        var $row = $(adminMenuTopItemHtml(data));

        $item.replaceWith($row);
        syncAdminMenuRoles($row);
        refreshAdminMenuSortables();

        return $row;
    }

    function restoreAdminMenuSubmenu($item) {
        var data = submenuAdminMenuData($item);
        var $row = $(adminMenuSubmenuItemHtml(data));

        $item.replaceWith($row);
        syncAdminMenuRoles($row);
        refreshAdminMenuSortables();

        return $row;
    }

    function rejectAdminMenuDrop(ui, message) {
        if (message) {
            $('#sc-am-status').text(message).css('color', '#c62828');
            setTimeout(function () { $('#sc-am-status').text(''); }, 3000);
        }

        if (ui.sender && ui.sender.length && ui.sender.data('ui-sortable')) {
            ui.sender.sortable('cancel');
        }

        refreshAdminMenuSortables();
    }

    function initAdminMenuOrganizer() {
        if (!$('.sc-am-wrap').length) return;

        if ($.fn.sortable) {
            $('#sc-am-menu-list').sortable({
                connectWith: '.sc-am-submenu-list',
                handle: '.sc-am-drag, .sc-am-submenu-drag',
                items: '> .sc-am-item, > .sc-am-submenu-item',
                cursor: 'move',
                tolerance: 'pointer',
                placeholder: 'sc-am-sort-placeholder',
                receive: function (event, ui) {
                    if (ui.item.hasClass('sc-am-submenu-item')) {
                        promoteAdminMenuSubmenu(ui.item);
                    }
                }
            });
            $('.sc-am-submenu-list').sortable({
                connectWith: '#sc-am-menu-list',
                handle: '.sc-am-submenu-drag, .sc-am-drag',
                items: '> .sc-am-submenu-item, > .sc-am-item-promoted_submenu',
                cursor: 'move',
                tolerance: 'pointer',
                placeholder: 'sc-am-submenu-sort-placeholder',
                receive: function (event, ui) {
                    var parent = String($(this).data('parent') || '');
                    var itemParent = String(ui.item.data('parent') || '');
                    var type = String(ui.item.data('type') || '');

                    if (!ui.item.hasClass('sc-am-item') || type !== 'promoted_submenu') {
                        rejectAdminMenuDrop(ui, 'Only moved-out submenu links can be restored into a submenu.');
                        return;
                    }

                    if (parent !== itemParent) {
                        rejectAdminMenuDrop(ui, 'Moved-out submenu links can only return to their original parent.');
                        return;
                    }

                    restoreAdminMenuSubmenu(ui.item);
                }
            });
        }

        $('.sc-am-item, .sc-am-submenu-item').each(function () {
            syncAdminMenuRoles($(this));
        });
    }

    $(initAdminMenuOrganizer);

    $(document).on('click', '.sc-am-expand', function () {
        var $btn = $(this);
        var $item = $btn.closest('.sc-am-item');
        var $details = $item.children('.sc-am-details');
        var expanded = $btn.attr('aria-expanded') === 'true';

        $btn.attr('aria-expanded', expanded ? 'false' : 'true');
        $details.prop('hidden', expanded);
        $item.toggleClass('sc-am-item-expanded', !expanded);
    });

    $(document).on('change', '.sc-am-hide-toggle', function () {
        var $item = $(this).closest('.sc-am-submenu-item, .sc-am-item');
        var $select = adminMenuControlRoot($item).find('.sc-am-visibility-mode').first();

        if ($(this).is(':checked')) {
            if ($select.val() === 'show') {
                $select.val('hide_all');
            }
        } else {
            $select.val('show');
        }

        syncAdminMenuRoles($item);
    });

    $(document).on('change', '.sc-am-visibility-mode', function () {
        var $item = $(this).closest('.sc-am-submenu-item, .sc-am-item');
        var isHidden = $(this).val() !== 'show';

        $item.children('.sc-am-item-main, .sc-am-submenu-main').find('.sc-am-hide-toggle').prop('checked', isHidden);
        syncAdminMenuRoles($item);
    });

    $(document).on('click', '#sc-am-add-separator', function () {
        var item = {
            id: adminMenuNewId('separator'),
            type: 'separator',
            label: adminMenuI18n('separator', '-- Separator --'),
            visibility_mode: 'show',
            roles: []
        };
        var $list = $('#sc-am-menu-list');
        $list.append(adminMenuTopItemHtml(item));
        refreshAdminMenuSortable($list);
    });

    $(document).on('click', '#sc-am-add-custom', function () {
        var item = {
            id: adminMenuNewId('custom'),
            type: 'custom',
            label: adminMenuI18n('custom_link', 'Custom Link'),
            url: '',
            icon: 'dashicons-admin-links',
            open_new: 0,
            visibility_mode: 'show',
            roles: []
        };
        var $row = $(adminMenuTopItemHtml(item));
        var $list = $('#sc-am-menu-list');
        $list.append($row);
        refreshAdminMenuSortable($list);
        $row.find('.sc-am-expand').trigger('click');
        $row.find('.sc-am-label').trigger('focus').trigger('select');
    });

    function collectAdminMenuItem($item) {
        var $controls = adminMenuControlRoot($item);
        var roles = [];

        $controls.find('.sc-am-role:checked').each(function () {
            roles.push($(this).val());
        });

        return {
            type: String($item.data('type') || 'menu'),
            slug: String($item.data('slug') || ''),
            parent: String($item.data('parent') || ''),
            label: $item.children('.sc-am-item-main, .sc-am-submenu-main').find('.sc-am-label').first().val() || '',
            url: $controls.find('.sc-am-url').val() || '',
            open_new: $controls.find('.sc-am-open-new').is(':checked') ? 1 : 0,
            icon: $controls.find('.sc-am-icon').val() || '',
            visibility_mode: $controls.find('.sc-am-visibility-mode').val() || 'show',
            roles: roles
        };
    }

    function collectAdminMenuConfig() {
        var data = {
            version: 2,
            order: [],
            items: {},
            submenu_order: {}
        };

        $('#sc-am-menu-list > .sc-am-item').each(function () {
            var $item = $(this);
            var id = String($item.data('id') || '');
            var parentSlug = String($item.data('slug') || '');

            if (!id) return;
            data.order.push(id);
            data.items[id] = collectAdminMenuItem($item);

            $item.find('> .sc-am-details > .sc-am-submenus > .sc-am-submenu-list').each(function () {
                var $submenuList = $(this);
                parentSlug = String($submenuList.data('parent') || parentSlug || '');

                if (parentSlug) {
                    data.submenu_order[parentSlug] = [];
                }

                $submenuList.children('.sc-am-submenu-item').each(function () {
                    var $sub = $(this);
                    var subId = String($sub.data('id') || '');
                    if (!subId) return;

                    if (parentSlug) {
                        data.submenu_order[parentSlug].push(subId);
                    }
                    data.items[subId] = collectAdminMenuItem($sub);
                });
            });
        });

        return data;
    }

    $(document).on('click', '#sc-am-save', function () {
        var $btn = $(this);
        var $status = $('#sc-am-status');

        $btn.prop('disabled', true);
        $status.text(adminMenuI18n('saving', 'Saving...')).css('color', '#646970');

        $.post(adminAjaxUrl(), {
            action: 'sc_save_admin_menu',
            nonce: adminMenuNonce(),
            data: JSON.stringify(collectAdminMenuConfig())
        }, function (res) {
            $status.text(res.success ? adminMenuI18n('saved', 'Saved!') : ((res.data && res.data.message) || adminMenuI18n('error', 'Error.')))
                .css('color', res.success ? '#2e7d32' : '#c62828');
        }).fail(function () {
            $status.text('Server error.').css('color', '#c62828');
        }).always(function () {
            $btn.prop('disabled', false);
            setTimeout(function () { $status.text(''); }, 4000);
        });
    });

    $(document).on('click', '#sc-am-reset', function () {
        if (!confirm(adminMenuI18n('reset_confirm', 'Reset all admin menu customizations?'))) return;

        var $btn = $(this);
        var $status = $('#sc-am-status');

        $btn.prop('disabled', true);
        $status.text(adminMenuI18n('resetting', 'Resetting...')).css('color', '#646970');

        $.post(adminAjaxUrl(), {
            action: 'sc_reset_admin_menu',
            nonce: adminMenuNonce()
        }, function (res) {
            if (res.success) {
                window.location.reload();
                return;
            }

            $status.text((res.data && res.data.message) || adminMenuI18n('error', 'Error.')).css('color', '#c62828');
        }).fail(function () {
            $status.text('Server error.').css('color', '#c62828');
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    // ══════════════════════════════════════════════════════════════
    // Media Offload
    // ══════════════════════════════════════════════════════════════

    function mediaOffloadPage() {
        return $('#sc-mo-wrap');
    }

    function mediaOffloadSelectedAdapter() {
        return $('#sc-mo-adapter').val() || 'bunny';
    }

    function mediaOffloadStatus(message, color) {
        $('#sc-mo-status').text(message || '').css('color', color || '');
    }

    function mediaOffloadProgress(html) {
        $('#sc-mo-progress').html(html);
    }

    function mediaOffloadSettingsPayload() {
        var adapter = mediaOffloadSelectedAdapter();
        var payload = {
            enabled: $('#sc-mo-enabled').is(':checked') ? 1 : 0,
            adapter: adapter,
            bucket: $('#sc-mo-bucket').val() || '',
            base_url: $('#sc-mo-base-url').val() || '',
            prefix: $('#sc-mo-prefix').val() || '',
            visibility: $('#sc-mo-visibility').val() || 'public',
            delete_local: $('#sc-mo-delete-local').is(':checked') ? 1 : 0,
            sanitize_output: $('#sc-mo-sanitize-output').is(':checked') ? 1 : 0,
            endpoint: '',
            region: '',
            access_key: '',
            secret_key: ''
        };

        if (adapter === 'bunny') {
            payload.endpoint = $('#sc-mo-endpoint').val() || '';
            payload.access_key = $('#sc-mo-access-key').val() || '';
        } else {
            payload.region = $('#sc-mo-region').val() || '';
            payload.endpoint = $('#sc-mo-do-endpoint').val() || '';
            payload.access_key = $('#sc-mo-do-access-key').val() || '';
            payload.secret_key = $('#sc-mo-secret-key').val() || '';
        }

        return payload;
    }

    function mediaOffloadToggleAdapterFields() {
        var adapter = mediaOffloadSelectedAdapter();
        $('.sc-mo-adapter-row').each(function () {
            var adapters = String($(this).data('adapter') || '').split(/\s+/);
            $(this).toggle(adapters.indexOf(adapter) !== -1);
        });
    }

    function mediaOffloadPost(action, data) {
        return $.post(spaceCore.ajaxUrl, $.extend({
            action: action,
            nonce: spaceCore.nonce
        }, data || {}));
    }

    function mediaOffloadDisable(disabled) {
        $('#sc-mo-save, #sc-mo-test, #sc-mo-start-offload, #sc-mo-start-migrate, #sc-mo-start-restore, #sc-mo-start-fix-broken, #sc-mo-start-regenerate, #sc-mo-start-find-replace').prop('disabled', disabled);
    }

    function mediaOffloadRunRegenerate(lastId, totals) {
        totals = totals || { processed: 0, regenerated: 0, skipped_missing: 0, failed: 0 };
        var dryRun = $('#sc-mo-dry-run').is(':checked') ? 1 : 0;
        var limit = parseInt($('#sc-mo-regenerate-limit').val() || '10', 10);

        if (!isFinite(limit) || limit < 1) limit = 10;
        if (limit > 50) limit = 50;

        mediaOffloadProgress('<strong>Regenerate images</strong><br>Batch: ' + limit + ' | Processed: ' + totals.processed + ' | Regenerated: ' + totals.regenerated + ' | Missing: ' + totals.skipped_missing + ' | Failed: ' + totals.failed + '<br>Dry run: ' + (dryRun ? 'Yes' : 'No'));

        return mediaOffloadPost('sc_media_offload_regenerate_batch', {
            last_id: lastId,
            limit: limit,
            max_seconds: 15,
            dry_run: dryRun
        }).then(function (res) {
            if (!res || !res.success) throw new Error((res && res.data && (res.data.detail || res.data.message)) || 'Request failed');

            totals.processed += res.data.processed;
            totals.regenerated += res.data.regenerated;
            totals.skipped_missing += res.data.skipped_missing;
            totals.failed += res.data.failed;

            if (res.data.done) return totals;

            return new Promise(function (resolve) { setTimeout(resolve, 250); }).then(function () {
                return mediaOffloadRunRegenerate(res.data.last_id, totals);
            });
        });
    }

    function mediaOffloadRunOffload(lastId, totals, logLines) {
        totals = totals || { processed: 0, offloaded: 0, skipped: 0, skipped_already: 0, skipped_missing: 0, retrying: 0, failed: 0 };
        logLines = logLines || [];

        var recentLog = logLines.slice(-15).map(function (line) {
            return '<div class="sc-mo-log-line">' + line + '</div>';
        }).join('');

        mediaOffloadProgress('<strong>Offload existing library</strong><br>Processed: ' + totals.processed + ' | Offloaded: ' + totals.offloaded + ' | Skipped: ' + totals.skipped + ' (Already: ' + totals.skipped_already + ', Missing: ' + totals.skipped_missing + ') | Failed: ' + totals.failed + '<br><br><strong>Recent files:</strong><div class="sc-mo-log">' + recentLog + '</div>');

        return mediaOffloadPost('sc_media_offload_offload_batch', {
            last_id: lastId,
            limit: 30
        }).then(function (res) {
            if (!res || !res.success) throw new Error((res && res.data && (res.data.detail || res.data.message)) || 'Request failed');

            totals.processed += res.data.processed;
            totals.offloaded += res.data.offloaded;
            totals.skipped += res.data.skipped;
            totals.skipped_already += res.data.skipped_already;
            totals.skipped_missing += res.data.skipped_missing;
            totals.retrying += res.data.retrying;
            totals.failed += res.data.failed;

            if (res.data.files_detail && res.data.files_detail.length) {
                res.data.files_detail.forEach(function (file) {
                    var icon = file.status === 'offloaded' ? 'OK' : (file.status === 'skipped' ? 'SKIP' : 'ERR');
                    logLines.push(icon + ' <strong>ID:' + file.id + '</strong> ' + file.file + ' -> <code>' + file.key + '</code> (' + file.status + ')');
                });
            }

            if (res.data.done) {
                var finalLog = logLines.slice(-30).map(function (line) {
                    return '<div class="sc-mo-log-line">' + line + '</div>';
                }).join('');

                mediaOffloadProgress('<strong>Offload complete</strong><br>Processed: ' + totals.processed + ' | Offloaded: ' + totals.offloaded + ' | Skipped: ' + totals.skipped + ' | Failed: ' + totals.failed + '<br><br><strong>Recent files:</strong><div class="sc-mo-log">' + finalLog + '</div>');
                return;
            }

            return mediaOffloadRunOffload(res.data.last_id, totals, logLines);
        });
    }

    function mediaOffloadRunMigrate(action, state, totals) {
        totals = totals || { posts_scanned: 0, posts_updated: 0, meta_scanned: 0, meta_updated: 0, options_scanned: 0, options_updated: 0, termtax_scanned: 0, termtax_updated: 0, termmeta_scanned: 0, termmeta_updated: 0 };
        var dryRun = $('#sc-mo-dry-run').is(':checked') ? 1 : 0;
        var title = action === 'sc_media_offload_migrate_urls' ? 'Migrate database URLs' : 'Restore local URLs';

        mediaOffloadProgress('<strong>' + title + '</strong><br>Posts scanned: ' + totals.posts_scanned + ' | Posts updated: ' + totals.posts_updated + '<br>Postmeta scanned: ' + totals.meta_scanned + ' | Postmeta updated: ' + totals.meta_updated + '<br>Options scanned: ' + totals.options_scanned + ' | Options updated: ' + totals.options_updated + '<br>Terms scanned: ' + totals.termtax_scanned + ' | Terms updated: ' + totals.termtax_updated + '<br>Termmeta scanned: ' + totals.termmeta_scanned + ' | Termmeta updated: ' + totals.termmeta_updated + '<br>Dry run: ' + (dryRun ? 'Yes' : 'No'));

        return mediaOffloadPost(action, {
            last_post_id: state.last_post_id,
            last_meta_id: state.last_meta_id,
            last_option_id: state.last_option_id,
            last_termtax_id: state.last_termtax_id,
            last_termmeta_id: state.last_termmeta_id,
            limit: 50,
            dry_run: dryRun
        }).then(function (res) {
            if (!res || !res.success) throw new Error((res && res.data && (res.data.detail || res.data.message)) || 'Request failed');

            totals.posts_scanned += res.data.posts_scanned;
            totals.posts_updated += res.data.posts_updated;
            totals.meta_scanned += res.data.meta_scanned;
            totals.meta_updated += res.data.meta_updated;
            totals.options_scanned += res.data.options_scanned;
            totals.options_updated += res.data.options_updated;
            totals.termtax_scanned += res.data.termtax_scanned;
            totals.termtax_updated += res.data.termtax_updated;
            totals.termmeta_scanned += res.data.termmeta_scanned;
            totals.termmeta_updated += res.data.termmeta_updated;

            if (res.data.done) {
                mediaOffloadProgress('<strong>' + title + ' complete</strong><br>Posts scanned: ' + totals.posts_scanned + ' | Posts updated: ' + totals.posts_updated + '<br>Postmeta scanned: ' + totals.meta_scanned + ' | Postmeta updated: ' + totals.meta_updated + '<br>Options scanned: ' + totals.options_scanned + ' | Options updated: ' + totals.options_updated + '<br>Terms scanned: ' + totals.termtax_scanned + ' | Terms updated: ' + totals.termtax_updated + '<br>Termmeta scanned: ' + totals.termmeta_scanned + ' | Termmeta updated: ' + totals.termmeta_updated + '<br>Dry run: ' + (dryRun ? 'Yes' : 'No'));
                return;
            }

            return mediaOffloadRunMigrate(action, {
                last_post_id: res.data.last_post_id,
                last_meta_id: res.data.last_meta_id,
                last_option_id: res.data.last_option_id,
                last_termtax_id: res.data.last_termtax_id,
                last_termmeta_id: res.data.last_termmeta_id
            }, totals);
        });
    }

    function mediaOffloadRunFixBroken(state, totals) {
        totals = totals || { posts_scanned: 0, posts_updated: 0, meta_scanned: 0, meta_updated: 0, options_scanned: 0, options_updated: 0, termtax_scanned: 0, termtax_updated: 0, termmeta_scanned: 0, termmeta_updated: 0 };
        var dryRun = $('#sc-mo-dry-run').is(':checked') ? 1 : 0;
        var title = 'Fix broken URLs';

        mediaOffloadProgress('<strong>' + title + '</strong><br>Posts scanned: ' + totals.posts_scanned + ' | Posts updated: ' + totals.posts_updated + '<br>Postmeta scanned: ' + totals.meta_scanned + ' | Postmeta updated: ' + totals.meta_updated + '<br>Options scanned: ' + totals.options_scanned + ' | Options updated: ' + totals.options_updated + '<br>Terms scanned: ' + totals.termtax_scanned + ' | Terms updated: ' + totals.termtax_updated + '<br>Termmeta scanned: ' + totals.termmeta_scanned + ' | Termmeta updated: ' + totals.termmeta_updated + '<br>Dry run: ' + (dryRun ? 'Yes' : 'No'));

        return mediaOffloadPost('sc_media_offload_fix_broken_urls', {
            last_post_id: state.last_post_id,
            last_meta_id: state.last_meta_id,
            last_option_id: state.last_option_id,
            last_termtax_id: state.last_termtax_id,
            last_termmeta_id: state.last_termmeta_id,
            limit: 200,
            max_seconds: 10,
            dry_run: dryRun
        }).then(function (res) {
            if (!res || !res.success) throw new Error((res && res.data && (res.data.detail || res.data.message)) || 'Request failed');

            totals.posts_scanned += res.data.posts_scanned;
            totals.posts_updated += res.data.posts_updated;
            totals.meta_scanned += res.data.meta_scanned;
            totals.meta_updated += res.data.meta_updated;
            totals.options_scanned += res.data.options_scanned;
            totals.options_updated += res.data.options_updated;
            totals.termtax_scanned += res.data.termtax_scanned;
            totals.termtax_updated += res.data.termtax_updated;
            totals.termmeta_scanned += res.data.termmeta_scanned;
            totals.termmeta_updated += res.data.termmeta_updated;

            var failures = (res.data && typeof res.data.failures === 'number') ? res.data.failures : 0;
            var html = '<strong>' + title + (res.data.done ? ' complete' : '') + '</strong><br>Posts scanned: ' + totals.posts_scanned + ' | Posts updated: ' + totals.posts_updated + '<br>Postmeta scanned: ' + totals.meta_scanned + ' | Postmeta updated: ' + totals.meta_updated + '<br>Options scanned: ' + totals.options_scanned + ' | Options updated: ' + totals.options_updated + '<br>Terms scanned: ' + totals.termtax_scanned + ' | Terms updated: ' + totals.termtax_updated + '<br>Termmeta scanned: ' + totals.termmeta_scanned + ' | Termmeta updated: ' + totals.termmeta_updated + (failures ? '<br><strong>Failures:</strong> ' + failures : '') + '<br>Dry run: ' + (dryRun ? 'Yes' : 'No');

            if (res.data.examples && res.data.examples.length) {
                html += '<br><br><strong>Examples:</strong>';
                res.data.examples.forEach(function (example) {
                    var before = (example.before || []).map(function (url) { return '<code>' + url + '</code>'; }).join(' ');
                    var after = (example.after || []).map(function (url) { return '<code>' + url + '</code>'; }).join(' ');
                    html += '<div class="sc-mo-log-line"><strong>' + example.table + ' #' + example.id + '</strong><br>Before: ' + before + '<br>After: ' + after + '</div>';
                });
            }

            mediaOffloadProgress(html);

            if (res.data.done) return;

            return mediaOffloadRunFixBroken({
                last_post_id: res.data.last_post_id,
                last_meta_id: res.data.last_meta_id,
                last_option_id: res.data.last_option_id,
                last_termtax_id: res.data.last_termtax_id,
                last_termmeta_id: res.data.last_termmeta_id
            }, totals);
        });
    }

    function mediaOffloadRunFindReplace(state, totals) {
        totals = totals || { posts_scanned: 0, posts_updated: 0, meta_scanned: 0, meta_updated: 0, options_scanned: 0, options_updated: 0, termtax_scanned: 0, termtax_updated: 0, termmeta_scanned: 0, termmeta_updated: 0 };
        var dryRun = $('#sc-mo-dry-run').is(':checked') ? 1 : 0;
        var findText = String($('#sc-mo-find-text').val() || '');
        var replaceText = String($('#sc-mo-replace-text').val() || '');

        if (!findText) throw new Error('Enter the text to search for first.');

        mediaOffloadProgress('<strong>Find / Replace</strong><br>Posts scanned: ' + totals.posts_scanned + ' | Posts updated: ' + totals.posts_updated + '<br>Postmeta scanned: ' + totals.meta_scanned + ' | Postmeta updated: ' + totals.meta_updated + '<br>Options scanned: ' + totals.options_scanned + ' | Options updated: ' + totals.options_updated + '<br>Terms scanned: ' + totals.termtax_scanned + ' | Terms updated: ' + totals.termtax_updated + '<br>Termmeta scanned: ' + totals.termmeta_scanned + ' | Termmeta updated: ' + totals.termmeta_updated + '<br>Dry run: ' + (dryRun ? 'Yes' : 'No'));

        return mediaOffloadPost('sc_media_offload_find_replace_text', {
            find_text: findText,
            replace_text: replaceText,
            last_post_id: state.last_post_id,
            last_meta_id: state.last_meta_id,
            last_option_id: state.last_option_id,
            last_termtax_id: state.last_termtax_id,
            last_termmeta_id: state.last_termmeta_id,
            limit: 100,
            max_seconds: 12,
            dry_run: dryRun
        }).then(function (res) {
            if (!res || !res.success) throw new Error((res && res.data && (res.data.detail || res.data.message)) || 'Request failed');

            totals.posts_scanned += res.data.posts_scanned;
            totals.posts_updated += res.data.posts_updated;
            totals.meta_scanned += res.data.meta_scanned;
            totals.meta_updated += res.data.meta_updated;
            totals.options_scanned += res.data.options_scanned;
            totals.options_updated += res.data.options_updated;
            totals.termtax_scanned += res.data.termtax_scanned;
            totals.termtax_updated += res.data.termtax_updated;
            totals.termmeta_scanned += res.data.termmeta_scanned;
            totals.termmeta_updated += res.data.termmeta_updated;

            if (res.data.done) {
                mediaOffloadProgress('<strong>Find / Replace complete</strong><br>Posts scanned: ' + totals.posts_scanned + ' | Posts updated: ' + totals.posts_updated + '<br>Postmeta scanned: ' + totals.meta_scanned + ' | Postmeta updated: ' + totals.meta_updated + '<br>Options scanned: ' + totals.options_scanned + ' | Options updated: ' + totals.options_updated + '<br>Terms scanned: ' + totals.termtax_scanned + ' | Terms updated: ' + totals.termtax_updated + '<br>Termmeta scanned: ' + totals.termmeta_scanned + ' | Termmeta updated: ' + totals.termmeta_updated + '<br>Dry run: ' + (dryRun ? 'Yes' : 'No'));
                return;
            }

            return mediaOffloadRunFindReplace({
                last_post_id: res.data.last_post_id,
                last_meta_id: res.data.last_meta_id,
                last_option_id: res.data.last_option_id,
                last_termtax_id: res.data.last_termtax_id,
                last_termmeta_id: res.data.last_termmeta_id
            }, totals);
        });
    }

    function initMediaOffload() {
        if (!mediaOffloadPage().length) return;

        mediaOffloadToggleAdapterFields();

        $(document).on('change', '#sc-mo-adapter', mediaOffloadToggleAdapterFields);

        $(document).on('click', '#sc-mo-save', function () {
            var $btn = $(this);
            $btn.prop('disabled', true);
            mediaOffloadStatus('Saving...', '#646970');

            mediaOffloadPost('sc_save_media_offload_settings', {
                data: JSON.stringify(mediaOffloadSettingsPayload())
            }).done(function (res) {
                mediaOffloadStatus(res.success ? 'Saved!' : ((res.data && res.data.message) || 'Error.'), res.success ? '#2e7d32' : '#c62828');
                if (res.success) {
                    $('#sc-mo-access-key, #sc-mo-do-access-key, #sc-mo-secret-key').val('');
                }
            }).fail(function () {
                mediaOffloadStatus('Server error.', '#c62828');
            }).always(function () {
                $btn.prop('disabled', false);
                setTimeout(function () { mediaOffloadStatus('', ''); }, 4000);
            });
        });

        $(document).on('click', '#sc-mo-test', function () {
            var $btn = $(this);
            $btn.prop('disabled', true);
            mediaOffloadStatus('Testing connection...', '#646970');

            mediaOffloadPost('sc_media_offload_test_connection', mediaOffloadSettingsPayload()).done(function (res) {
                mediaOffloadStatus(res.success ? ((res.data && res.data.message) || 'Connection test succeeded.') : ((res.data && res.data.message) || 'Connection test failed.'), res.success ? '#2e7d32' : '#c62828');
            }).fail(function (xhr) {
                var response = xhr.responseJSON;
                mediaOffloadStatus((response && response.data && response.data.message) || 'Connection test failed.', '#c62828');
            }).always(function () {
                $btn.prop('disabled', false);
            });
        });

        $(document).on('click', '#sc-mo-start-offload', function () {
            mediaOffloadDisable(true);
            mediaOffloadRunOffload(0).catch(function (error) {
                mediaOffloadProgress('<strong>Error</strong><br>' + String(error && error.message ? error.message : error));
            }).finally(function () {
                mediaOffloadDisable(false);
            });
        });

        $(document).on('click', '#sc-mo-start-migrate', function () {
            mediaOffloadDisable(true);
            mediaOffloadRunMigrate('sc_media_offload_migrate_urls', { last_post_id: 0, last_meta_id: 0, last_option_id: 0, last_termtax_id: 0, last_termmeta_id: 0 }).catch(function (error) {
                mediaOffloadProgress('<strong>Error</strong><br>' + String(error && error.message ? error.message : error));
            }).finally(function () {
                mediaOffloadDisable(false);
            });
        });

        $(document).on('click', '#sc-mo-start-restore', function () {
            if (!window.confirm('Restore remote URLs back to local URLs?')) return;
            mediaOffloadDisable(true);
            mediaOffloadRunMigrate('sc_media_offload_restore_urls', { last_post_id: 0, last_meta_id: 0, last_option_id: 0, last_termtax_id: 0, last_termmeta_id: 0 }).catch(function (error) {
                mediaOffloadProgress('<strong>Error</strong><br>' + String(error && error.message ? error.message : error));
            }).finally(function () {
                mediaOffloadDisable(false);
            });
        });

        $(document).on('click', '#sc-mo-start-fix-broken', function () {
            mediaOffloadDisable(true);
            mediaOffloadRunFixBroken({ last_post_id: 0, last_meta_id: 0, last_option_id: 0, last_termtax_id: 0, last_termmeta_id: 0 }).catch(function (error) {
                mediaOffloadProgress('<strong>Error</strong><br>' + String(error && error.message ? error.message : error));
            }).finally(function () {
                mediaOffloadDisable(false);
            });
        });

        $(document).on('click', '#sc-mo-start-find-replace', function () {
            mediaOffloadDisable(true);
            Promise.resolve().then(function () {
                return mediaOffloadRunFindReplace({ last_post_id: 0, last_meta_id: 0, last_option_id: 0, last_termtax_id: 0, last_termmeta_id: 0 });
            }).catch(function (error) {
                mediaOffloadProgress('<strong>Error</strong><br>' + String(error && error.message ? error.message : error));
            }).finally(function () {
                mediaOffloadDisable(false);
            });
        });

        $(document).on('click', '#sc-mo-start-regenerate', function () {
            if (!window.confirm('This will regenerate images and offload the new files. Continue?')) return;
            mediaOffloadDisable(true);
            mediaOffloadRunRegenerate(0).then(function (totals) {
                mediaOffloadProgress('<strong>Regenerate complete</strong><br>Processed: ' + totals.processed + ' | Regenerated: ' + totals.regenerated + ' | Missing: ' + totals.skipped_missing + ' | Failed: ' + totals.failed);
            }).catch(function (error) {
                mediaOffloadProgress('<strong>Error</strong><br>' + String(error && error.message ? error.message : error));
            }).finally(function () {
                mediaOffloadDisable(false);
            });
        });
    }

    $(initMediaOffload);

    // ── Utility ───────────────────────────────────────────────────
    function slugify(str) {
        return str.toLowerCase()
            .replace(/[^\w\s-]/g, '')
            .replace(/[\s_-]+/g, '_')
            .replace(/^-+|-+$/g, '');
    }

}(jQuery));
