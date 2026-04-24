/* global scBMC, jQuery */
(function ($) {
    'use strict';

    var cfg       = window.scBMC || {};
    var ajaxUrl   = cfg.ajaxUrl   || '';
    var nonce     = cfg.nonce     || '';
    var i18n      = cfg.i18n      || {};
    var DEBOUNCE  = 700;

    // Per-field debounce timers keyed by "<type>:<id>:<field>".
    var saveTimers = {};

    function getFieldValue($field) {
        if ($field.is(':checkbox')) {
            return $field.is(':checked') ? '1' : '';
        }

        return $field.val();
    }

    // ══════════════════════════════════════════════════════════════
    // Tab helpers
    // ══════════════════════════════════════════════════════════════

    function activateTopTab(name) {
        var $wrap = $('#sc-bmc-app');
        $wrap.find('.sc-bmc-tab-btn').removeClass('active').attr('aria-selected', 'false');
        $wrap.find('.sc-bmc-panel').removeClass('active');
        $wrap.find('.sc-bmc-tab-btn[data-panel="' + name + '"]').addClass('active').attr('aria-selected', 'true');
        $wrap.find('#sc-bmc-panel-' + name).addClass('active');

        // Auto-activate first sub-tab in this panel.
        var $firstSub = $wrap.find('#sc-bmc-panel-' + name + ' .sc-bmc-sub-tab-btn:first');
        if ($firstSub.length) {
            activateSubTab($firstSub);
        }
    }

    function activateSubTab($btn) {
        var $panel = $btn.closest('.sc-bmc-panel');
        $panel.find('.sc-bmc-sub-tab-btn').removeClass('active');
        $btn.addClass('active');

        var type = $btn.data('panel-type'); // 'post_type' | 'taxonomy'
        var slug = $btn.data('slug');
        var prefix = type === 'post_type' ? 'pt' : 'tax';

        $panel.find('.sc-bmc-sub-panel').removeClass('active');
        var $sub = $('#sc-bmc-' + prefix + '-' + slug);
        $sub.addClass('active');

        if ($sub.data('loaded') == 0) {
            loadTable($sub, type, slug, 1, 25);
        }
    }

    // ══════════════════════════════════════════════════════════════
    // Table loader
    // ══════════════════════════════════════════════════════════════

    function loadTable($panel, type, slug, page, perPage) {
        $panel.find('.sc-bmc-table-scroll, .sc-bmc-table-controls, div[style*="margin-top"]').remove();
        $panel.html('<div class="sc-bmc-loading"><span class="spinner is-active"></span></div>');

        var action = type === 'post_type' ? 'sc_bulk_load_posts' : 'sc_bulk_load_terms';
        var payload = {
            action:   action,
            nonce:    nonce,
            paged:    page,
            per_page: perPage,
        };
        if (type === 'post_type') {
            payload.post_type = slug;
        } else {
            payload.taxonomy = slug;
        }

        $.post(ajaxUrl, payload, function (res) {
            if (!res.success) {
                $panel.html('<div class="sc-bmc-load-error">' + (res.data && res.data.message ? res.data.message : i18n.error) + '</div>');
                return;
            }
            $panel.html(res.data.html);
            $panel.data('loaded', 1).attr('data-loaded', '1');
            $panel.data('current-page', res.data.current_page).attr('data-current-page', res.data.current_page);
            $panel.data('per-page', perPage).attr('data-per-page', perPage);
        }).fail(function () {
            $panel.html('<div class="sc-bmc-load-error">' + i18n.error + '</div>');
        });
    }

    function getSubPanel(slug, type) {
        var prefix = type === 'post_type' ? 'pt' : 'tax';
        return $('#sc-bmc-' + prefix + '-' + slug);
    }

    // ══════════════════════════════════════════════════════════════
    // Inline field save (debounced)
    // ══════════════════════════════════════════════════════════════

    function flashStatus($input, state, text) {
        var $status = $input.siblings('.sc-bmc-field-status');
        $status.removeClass('saving saved error').addClass(state).text(text);
        if (state !== 'saving') {
            clearTimeout($status.data('fadeTimer'));
            $status.data('fadeTimer', setTimeout(function () {
                $status.removeClass('saving saved error').text('');
            }, 2000));
        }
    }

    function savePostField($input) {
        var postId   = $input.data('post-id');
        var fieldKey = $input.data('field-key');
        var value    = getFieldValue($input);

        flashStatus($input, 'saving', i18n.saving);

        $.post(ajaxUrl, {
            action:    'sc_save_post_field',
            nonce:     nonce,
            post_id:   postId,
            field_key: fieldKey,
            value:     value,
        }, function (res) {
            flashStatus($input, res.success ? 'saved' : 'error', res.success ? i18n.saved : i18n.error);
        }).fail(function () {
            flashStatus($input, 'error', i18n.error);
        });
    }

    function saveTermField($input) {
        var termId   = $input.data('term-id');
        var taxonomy = $input.data('taxonomy');
        var fieldKey = $input.data('field-key');
        var value    = getFieldValue($input);

        flashStatus($input, 'saving', i18n.saving);

        $.post(ajaxUrl, {
            action:    'sc_save_term_field',
            nonce:     nonce,
            term_id:   termId,
            taxonomy:  taxonomy,
            field_key: fieldKey,
            value:     value,
        }, function (res) {
            flashStatus($input, res.success ? 'saved' : 'error', res.success ? i18n.saved : i18n.error);
        }).fail(function () {
            flashStatus($input, 'error', i18n.error);
        });
    }

    function scheduleFieldSave($input) {
        var row  = $input.closest('tr');
        var type = row.data('type'); // 'post_type' | 'taxonomy'
        var id   = row.data('id');
        var key  = type + ':' + id + ':' + $input.data('field-key');

        clearTimeout(saveTimers[key]);
        saveTimers[key] = setTimeout(function () {
            if (type === 'post_type') {
                savePostField($input);
            } else {
                saveTermField($input);
            }
        }, DEBOUNCE);
    }

    // ══════════════════════════════════════════════════════════════
    // Add New row
    // ══════════════════════════════════════════════════════════════

    function saveNewPost($btn) {
        var $row      = $btn.closest('tr');
        var $table    = $btn.closest('table');
        var postType  = $btn.data('post-type');
        var titleEn   = $row.find('[name="title_en"]').val().trim();
        var titleAr   = $row.find('[name="title_ar"]').val().trim();
        var status    = $row.find('[name="status"]').val() || 'draft';

        if (!titleEn) {
            alert(i18n.required);
            return;
        }

        var meta = {};
        $row.find('.sc-bmc-new-field[name]').each(function () {
            var $field = $(this);
            var name = $field.attr('name');
            if (name !== 'title_en' && name !== 'title_ar' && name !== 'status') {
                meta[name] = getFieldValue($field);
            }
        });

        $btn.prop('disabled', true);

        $.post(ajaxUrl, {
            action:    'sc_add_post',
            nonce:     nonce,
            post_type: postType,
            title_en:  titleEn,
            title_ar:  titleAr,
            status:    status,
            meta:      JSON.stringify(meta),
        }, function (res) {
            if (!res.success) {
                alert(res.data && res.data.message ? res.data.message : i18n.error);
                $btn.prop('disabled', false);
                return;
            }
            // Insert the new saved row before the new-row template.
            $(res.data.html_row).insertBefore($row);
            // Clear and hide the new row.
            $row.find('.sc-bmc-new-field').each(function () {
                var $field = $(this);
                if ($field.is(':checkbox')) {
                    $field.prop('checked', false);
                } else if ($field.is('select')) {
                    $field.prop('selectedIndex', 0);
                } else {
                    $field.val('');
                }
            });
            $row.hide();
            $btn.prop('disabled', false);
        }).fail(function () {
            alert(i18n.error);
            $btn.prop('disabled', false);
        });
    }

    function saveNewTerm($btn) {
        var $row     = $btn.closest('tr');
        var taxonomy = $btn.data('taxonomy');
        var nameEn   = $row.find('[name="name_en"]').val().trim();
        var nameAr   = $row.find('[name="name_ar"]').val().trim();

        if (!nameEn) {
            alert(i18n.required);
            return;
        }

        var meta = {};
        $row.find('.sc-bmc-new-field[name]').each(function () {
            var $field = $(this);
            var name = $field.attr('name');
            if (name !== 'name_en' && name !== 'name_ar') {
                meta[name] = getFieldValue($field);
            }
        });

        $btn.prop('disabled', true);

        $.post(ajaxUrl, {
            action:   'sc_add_term',
            nonce:    nonce,
            taxonomy: taxonomy,
            name_en:  nameEn,
            name_ar:  nameAr,
            meta:     JSON.stringify(meta),
        }, function (res) {
            if (!res.success) {
                alert(res.data && res.data.message ? res.data.message : i18n.error);
                $btn.prop('disabled', false);
                return;
            }
            $(res.data.html_row).insertBefore($row);
            $row.find('.sc-bmc-new-field').each(function () {
                var $field = $(this);
                if ($field.is(':checkbox')) {
                    $field.prop('checked', false);
                } else if ($field.is('select')) {
                    $field.prop('selectedIndex', 0);
                } else {
                    $field.val('');
                }
            });
            $row.hide();
            $btn.prop('disabled', false);
        }).fail(function () {
            alert(i18n.error);
            $btn.prop('disabled', false);
        });
    }

    // ══════════════════════════════════════════════════════════════
    // Event delegation (on #sc-bmc-app so it survives AJAX replaces)
    // ══════════════════════════════════════════════════════════════

    var $app = $(document);

    // Top-tab click.
    $app.on('click', '#sc-bmc-app .sc-bmc-tab-btn', function () {
        activateTopTab($(this).data('panel'));
    });

    // Sub-tab click.
    $app.on('click', '#sc-bmc-app .sc-bmc-sub-tab-btn', function () {
        activateSubTab($(this));
    });

    // Inline field change — debounced save (existing rows only).
    $app.on('change input', '#sc-bmc-app .sc-bmc-inline-field', function () {
        scheduleFieldSave($(this));
    });

    // Pagination: prev / next.
    $app.on('click', '#sc-bmc-app .sc-bmc-prev, #sc-bmc-app .sc-bmc-next', function () {
        var $btn  = $(this);
        if ($btn.prop('disabled')) return;
        var type  = $btn.data('table-type');
        var slug  = $btn.data('slug');
        var page  = parseInt($btn.data('page'), 10) || 1;
        var $sub  = getSubPanel(slug, type);
        var pp    = parseInt($sub.data('per-page'), 10) || 25;
        loadTable($sub, type, slug, page, pp);
    });

    // Per-page dropdown.
    $app.on('change', '#sc-bmc-app .sc-bmc-per-page', function () {
        var type  = $(this).data('table-type');
        var slug  = $(this).data('slug');
        var pp    = parseInt($(this).val(), 10) || 25;
        var $sub  = getSubPanel(slug, type);
        loadTable($sub, type, slug, 1, pp);
    });

    // Show "Add New" row.
    $app.on('click', '#sc-bmc-app .sc-bmc-show-new-row', function () {
        var $table = $(this).prev('.sc-bmc-table-scroll').find('table');
        $table.find('.sc-bmc-new-row').show();
        $table.find('.sc-bmc-new-row input:first').trigger('focus');
        $(this).hide();
    });

    // Cancel new row.
    $app.on('click', '#sc-bmc-app .sc-bmc-cancel-new', function () {
        var $row = $(this).closest('tr');
        $row.find('.sc-bmc-new-field').each(function () {
            var $field = $(this);
            if ($field.is(':checkbox')) {
                $field.prop('checked', false);
            } else if ($field.is('select')) {
                $field.prop('selectedIndex', 0);
            } else {
                $field.val('');
            }
        });
        $row.hide();
        $row.closest('.sc-bmc-sub-panel').find('.sc-bmc-show-new-row').show();
    });

    // Save new post row.
    $app.on('click', '#sc-bmc-app .sc-bmc-save-new[data-post-type]', function () {
        saveNewPost($(this));
    });

    // Save new term row.
    $app.on('click', '#sc-bmc-app .sc-bmc-save-new[data-taxonomy]', function () {
        saveNewTerm($(this));
    });

    // ══════════════════════════════════════════════════════════════
    // Init
    // ══════════════════════════════════════════════════════════════

    function init() {
        // Activate first available top tab.
        var $firstTop = $('#sc-bmc-app .sc-bmc-tab-btn:first');
        if ($firstTop.length) {
            activateTopTab($firstTop.data('panel'));
        }
    }

    $(init);

}(jQuery));
