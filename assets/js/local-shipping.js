/**
 * Local Shipping — checkout combo field + session sync.
 *
 * Depends on: jQuery, scLocalShipping (localized object)
 */
(function ($) {
    'use strict';

    var cfg = window.scLocalShipping || {};
    var strings = cfg.strings || {};
    var cartSubtotal = parseFloat(cfg.cartSubtotal) || 0;
    var currency = cfg.currencySymbol || '';

    // -------------------------------------------------------------------------
    // Price label logic (mirrors PHP format_area_price_label)
    // -------------------------------------------------------------------------
    function priceLabel(price, freeMin) {
        price   = parseFloat(price)   || 0;
        freeMin = parseFloat(freeMin) || 0;

        if (price <= 0 && freeMin <= 0) {
            return strings.free || 'Free';
        }

        if (freeMin > 0) {
            if (cartSubtotal >= freeMin) {
                return strings.free || 'Free';
            }
            var overStr = (strings.freeOver || 'Free on orders over %s')
                .replace('%s', freeMin.toFixed(3) + currency);
            return overStr;
        }

        return price.toFixed(3) + currency;
    }

    // -------------------------------------------------------------------------
    // Update price labels on load (cart subtotal might differ from PHP render)
    // -------------------------------------------------------------------------
    function refreshPriceLabels() {
        $('.sc-combo-item').each(function () {
            var $item   = $(this);
            var price   = $item.data('price');
            var freeMin = $item.data('freeminimum');
            $item.find('.sc-item-price').text(priceLabel(price, freeMin));
        });
    }

    // -------------------------------------------------------------------------
    // Combo open / close
    // -------------------------------------------------------------------------
    function openCombo() {
        var $trigger = $('#sc-combo-trigger');
        var $panel   = $('#sc-combo-panel');
        $trigger.attr('aria-expanded', 'true');
        $panel.show();
        $panel.find('.sc-combo-search').val('').trigger('input').focus();
    }

    function closeCombo() {
        $('#sc-combo-trigger').attr('aria-expanded', 'false');
        $('#sc-combo-panel').hide();
    }

    // -------------------------------------------------------------------------
    // Select an area
    // -------------------------------------------------------------------------
    function selectArea($item) {
        var areaId  = $item.data('value');
        var cityId  = $item.data('city');
        var name    = $item.data('name');

        // Update hidden inputs.
        $('#billing_sc_area_id').val(areaId);
        $('#billing_sc_city_id').val(cityId);

        // Update trigger label.
        $('#sc-combo-trigger .sc-combo-placeholder').text(name).addClass('has-value');

        // Mark selected.
        $('.sc-combo-item').removeClass('sc-selected').attr('aria-selected', 'false');
        $item.addClass('sc-selected').attr('aria-selected', 'true');

        closeCombo();

        // Send to server (WC session + fee).
        var deliveryType = $('#billing_sc_delivery_type').val() || 'normal';

        $.post(cfg.ajaxUrl, {
            action:        'sc_set_delivery_session',
            nonce:         cfg.nonce,
            area_id:       areaId,
            city_id:       cityId,
            delivery_type: deliveryType,
        }, function (res) {
            if (res.success) {
                // Trigger WC cart fragment refresh.
                $(document.body).trigger('update_checkout');
            }
        });
    }

    // -------------------------------------------------------------------------
    // Search / filter
    // -------------------------------------------------------------------------
    function filterItems(query) {
        var q = query.toLowerCase().trim();
        var anyVisible = false;

        $('.sc-combo-group').each(function () {
            var $group    = $(this);
            var groupVis  = false;
            $group.find('.sc-combo-item').each(function () {
                var $item  = $(this);
                var name   = ($item.data('name') || '').toLowerCase();
                var match  = !q || name.indexOf(q) !== -1;
                $item.toggle(match);
                if (match) groupVis = true;
            });
            $group.toggle(groupVis);
            if (groupVis) anyVisible = true;
        });

        $('.sc-combo-no-results').toggle(!anyVisible);
    }

    // -------------------------------------------------------------------------
    // Express delivery type change → resync session
    // -------------------------------------------------------------------------
    function syncDeliveryType() {
        var areaId  = parseInt($('#billing_sc_area_id').val(), 10) || 0;
        var cityId  = parseInt($('#billing_sc_city_id').val(), 10) || 0;
        var type    = $('#billing_sc_delivery_type').val() || 'normal';

        if (!areaId) return;

        $.post(cfg.ajaxUrl, {
            action:        'sc_set_delivery_session',
            nonce:         cfg.nonce,
            area_id:       areaId,
            city_id:       cityId,
            delivery_type: type,
        }, function (res) {
            if (res.success) {
                $(document.body).trigger('update_checkout');
            }
        });
    }

    // -------------------------------------------------------------------------
    // Show / hide the area combo based on whether country has cities
    // -------------------------------------------------------------------------
    function toggleAreaField(hasCities) {
        var $areaField = $('#billing_sc_area_field');
        var $typeField = $('#billing_sc_delivery_type_field');

        if (hasCities) {
            $areaField.show();
            if (cfg.expressEnabled) { $typeField.show(); }
        } else {
            $areaField.hide();
            $typeField.hide();
            // Clear the current selection in UI.
            $('#billing_sc_area_id').val('');
            $('#billing_sc_city_id').val('');
            $('#sc-combo-trigger .sc-combo-placeholder')
                .text(strings.selectArea || '-- Select delivery area --')
                .removeClass('has-value');
            $('.sc-combo-item').removeClass('sc-selected').attr('aria-selected', 'false');
            closeCombo();
        }
    }

    // -------------------------------------------------------------------------
    // Area combo visibility — track last known state so updated_checkout can
    // re-apply without a second AJAX call.
    // -------------------------------------------------------------------------
    var lastHasCities = null;

    function checkCountryAndToggle(country) {
        if (!country) {
            return;
        }
        $.post(cfg.ajaxUrl, {
            action:  'sc_ls_country_has_cities',
            nonce:   cfg.nonce,
            country: country,
        }, function (res) {
            var hasCities = res.success && res.data && res.data.has_cities;
            lastHasCities = hasCities;
            toggleAreaField(hasCities);
        });
    }

    // -------------------------------------------------------------------------
    // Init
    // -------------------------------------------------------------------------
    $(function () {
        refreshPriceLabels();

        // Set initial area combo visibility based on pre-selected billing country.
        var initialCountry = $('#billing_country').val();
        if (initialCountry) {
            checkCountryAndToggle(initialCountry);
        }

        // Toggle combo on trigger click.
        $(document).on('click', '#sc-combo-trigger', function (e) {
            e.stopPropagation();
            if ($('#sc-combo-panel').is(':visible')) {
                closeCombo();
            } else {
                openCombo();
            }
        });

        // Keyboard: Enter/Space opens combo.
        $(document).on('keydown', '#sc-combo-trigger', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                openCombo();
            }
        });

        // Click outside closes.
        $(document).on('click', function (e) {
            if (!$(e.target).closest('.sc-combo-wrap').length) {
                closeCombo();
            }
        });

        // Search input.
        $(document).on('input', '.sc-combo-search', function () {
            filterItems($(this).val());
        });

        // Select item.
        $(document).on('click', '.sc-combo-item', function () {
            selectArea($(this));
        });

        // Express type change.
        $(document).on('change', '#billing_sc_delivery_type', function () {
            syncDeliveryType();
        });

        // Billing country change — show/hide area combo + clear session fee.
        $(document).on('change', '#billing_country', function () {
            var country = $(this).val();
            if (!country) { return; }

            $.post(cfg.ajaxUrl, {
                action:  'sc_ls_country_has_cities',
                nonce:   cfg.nonce,
                country: country,
            }, function (res) {
                var hasCities = res.success && res.data && res.data.has_cities;
                lastHasCities = hasCities;
                toggleAreaField(hasCities);

                if (!hasCities) {
                    // Clear session so the server removes the delivery fee on the
                    // next update_checkout (which WC fires right after this change).
                    $.post(cfg.ajaxUrl, {
                        action:        'sc_set_delivery_session',
                        nonce:         cfg.nonce,
                        area_id:       0,
                        city_id:       0,
                        delivery_type: 'normal',
                    });
                }
            });
        });

        // Re-apply area combo visibility after WC replaces checkout fragments.
        // WC may replace parts of the checkout form HTML on updated_checkout,
        // which resets any JS-applied display state.
        $(document.body).on('updated_checkout', function () {
            if (lastHasCities !== null) {
                toggleAreaField(lastHasCities);
            }
        });
    });

}(jQuery));
