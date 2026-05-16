<?php

defined( 'ABSPATH' ) || exit;

/**
 * Variables: $nonce, $config, $post_types (WP_Post_Type[]), $taxonomies (WP_Taxonomy[]),
 *            $custom_fields_by_post_type (array<string, array>)
 */
$pt_config  = $config['post_types'] ?? [];
$tax_config = $config['taxonomies'] ?? [];
?>
<div class="sc-module-section sc-bmc-settings">
    <h2><?php esc_html_e( 'Bulk Manage Content', 'space-core' ); ?></h2>
    <p><?php esc_html_e( 'Enable post types and taxonomies to appear in the Bulk Management page. Post types can use Custom Fields module fields and extra manual meta fields.', 'space-core' ); ?></p>

    <div class="sc-bmc-settings-columns">

        <!-- ── Post Types ─────────────────────────────────────── -->
        <div class="sc-bmc-settings-col">
            <h3><?php esc_html_e( 'Post Types', 'space-core' ); ?></h3>

            <?php foreach ( $post_types as $pt_slug => $pt ) :
                $pt_cfg     = $pt_config[ $pt_slug ] ?? [];
                $is_enabled = ! empty( $pt_cfg['enabled'] );
                $fields     = $pt_cfg['fields'] ?? [];
                $available  = $custom_fields_by_post_type[ $pt_slug ] ?? [];
                $selected   = ! empty( $pt_cfg['field_keys'] )
                    ? (array) $pt_cfg['field_keys']
                    : array_values( array_intersect( array_column( $fields, 'key' ), array_column( $available, 'key' ) ) );
                $manual_fields = $pt_cfg['manual_fields'] ?? array_values(
                    array_filter(
                        $fields,
                        static fn( array $field ): bool => ! in_array( $field['key'] ?? '', array_column( $available, 'key' ), true )
                    )
                );
            ?>
            <div class="sc-bmc-object-block" data-object-type="post_type" data-object-slug="<?php echo esc_attr( $pt_slug ); ?>">
                <div class="sc-bmc-object-header">
                    <label class="sc-toggle-wrap">
                        <input type="checkbox"
                               class="sc-bmc-toggle"
                               data-object-type="post_type"
                               data-slug="<?php echo esc_attr( $pt_slug ); ?>"
                               <?php checked( $is_enabled ); ?> />
                        <span class="sc-toggle-slider"></span>
                    </label>
                    <strong><?php echo esc_html( $pt->labels->singular_name ); ?></strong>
                    <code class="sc-bmc-slug"><?php echo esc_html( $pt_slug ); ?></code>
                </div>
                <div class="sc-bmc-fields-wrap <?php echo $is_enabled ? '' : 'sc-hidden'; ?>">
                    <div class="sc-bmc-exclude-filter" style="margin-bottom:14px;padding:8px 10px;background:#f6f7f7;border-left:3px solid #2271b1;">
                        <label style="display:block;font-weight:600;margin-bottom:4px;">
                            <?php esc_html_e( 'Exclude when meta key is filled', 'space-core' ); ?>
                        </label>
                        <input type="text"
                               class="sc-bmc-exclude-meta-key"
                               value="<?php echo esc_attr( $pt_cfg['exclude_meta_key'] ?? '' ); ?>"
                               placeholder="<?php esc_attr_e( 'e.g. _price, is_archived', 'space-core' ); ?>"
                               style="width:220px;" />
                        <p class="description" style="margin:4px 0 0;">
                            <?php esc_html_e( 'Posts whose value for this meta key is non-empty will be hidden from the bulk table. Leave empty to disable.', 'space-core' ); ?>
                        </p>
                    </div>

                    <?php if ( empty( $available ) ) : ?>
                        <p class="description"><?php esc_html_e( 'No custom fields found for this post type in the Custom Fields module.', 'space-core' ); ?></p>
                    <?php else : ?>
                        <p><strong><?php esc_html_e( 'Custom Fields Module', 'space-core' ); ?></strong></p>
                        <table class="widefat striped sc-bmc-custom-field-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Use', 'space-core' ); ?></th>
                                    <th><?php esc_html_e( 'Key', 'space-core' ); ?></th>
                                    <th><?php esc_html_e( 'Label EN', 'space-core' ); ?></th>
                                    <th><?php esc_html_e( 'Label AR', 'space-core' ); ?></th>
                                    <th><?php esc_html_e( 'Type', 'space-core' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ( $available as $field ) : ?>
                                <tr>
                                    <td>
                                        <input type="checkbox"
                                               class="sc-bmc-custom-field-checkbox"
                                               value="<?php echo esc_attr( $field['key'] ); ?>"
                                               <?php checked( in_array( $field['key'], $selected, true ) ); ?> />
                                    </td>
                                    <td><code><?php echo esc_html( $field['key'] ); ?></code></td>
                                    <td><?php echo esc_html( $field['label_en'] ?? $field['label'] ?? $field['key'] ); ?></td>
                                    <td dir="rtl"><?php echo esc_html( $field['label_ar'] ?? '' ); ?></td>
                                    <td><?php echo esc_html( ucfirst( $field['type'] ?? 'text' ) ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <div class="sc-bmc-manual-fields-section" style="margin-top:14px;">
                        <p><strong><?php esc_html_e( 'Manual Meta Fields', 'space-core' ); ?></strong></p>
                        <table class="widefat striped sc-bmc-fields-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Key', 'space-core' ); ?></th>
                                    <th><?php esc_html_e( 'Label EN', 'space-core' ); ?></th>
                                    <th><?php esc_html_e( 'Label AR', 'space-core' ); ?></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ( $manual_fields as $field ) : ?>
                                <tr class="sc-bmc-field-row">
                                    <td><input type="text" class="sc-bmc-field-key" value="<?php echo esc_attr( $field['key'] ); ?>" placeholder="my_field" style="width:110px;" /></td>
                                    <td><input type="text" class="sc-bmc-field-label-en" value="<?php echo esc_attr( $field['label_en'] ?? '' ); ?>" placeholder="Label" style="width:110px;" /></td>
                                    <td><input type="text" class="sc-bmc-field-label-ar" value="<?php echo esc_attr( $field['label_ar'] ?? '' ); ?>" dir="rtl" placeholder="تسمية" style="width:110px;" /></td>
                                    <td><button type="button" class="button button-small sc-bmc-remove-new-field"><?php esc_html_e( 'Remove', 'space-core' ); ?></button></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <button type="button" class="button sc-bmc-add-field" style="margin-top:6px;">
                            + <?php esc_html_e( 'Add Manual Field', 'space-core' ); ?>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ── Taxonomies ─────────────────────────────────────── -->
        <div class="sc-bmc-settings-col">
            <h3><?php esc_html_e( 'Taxonomies', 'space-core' ); ?></h3>

            <?php foreach ( $taxonomies as $tax_slug => $tax ) :
                $tax_cfg    = $tax_config[ $tax_slug ] ?? [];
                $is_enabled = ! empty( $tax_cfg['enabled'] );
                $fields     = $tax_cfg['fields'] ?? [];
            ?>
            <div class="sc-bmc-object-block" data-object-type="taxonomy" data-object-slug="<?php echo esc_attr( $tax_slug ); ?>">
                <div class="sc-bmc-object-header">
                    <label class="sc-toggle-wrap">
                        <input type="checkbox"
                               class="sc-bmc-toggle"
                               data-object-type="taxonomy"
                               data-slug="<?php echo esc_attr( $tax_slug ); ?>"
                               <?php checked( $is_enabled ); ?> />
                        <span class="sc-toggle-slider"></span>
                    </label>
                    <strong><?php echo esc_html( $tax->labels->singular_name ); ?></strong>
                    <code class="sc-bmc-slug"><?php echo esc_html( $tax_slug ); ?></code>
                </div>
                <div class="sc-bmc-fields-wrap <?php echo $is_enabled ? '' : 'sc-hidden'; ?>">
                    <table class="widefat striped sc-bmc-fields-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Key', 'space-core' ); ?></th>
                                <th><?php esc_html_e( 'Label EN', 'space-core' ); ?></th>
                                <th><?php esc_html_e( 'Label AR', 'space-core' ); ?></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $fields as $field ) : ?>
                            <tr class="sc-bmc-field-row">
                                <td><input type="text" class="sc-bmc-field-key" value="<?php echo esc_attr( $field['key'] ); ?>" placeholder="my_field" style="width:110px;" /></td>
                                <td><input type="text" class="sc-bmc-field-label-en" value="<?php echo esc_attr( $field['label_en'] ); ?>" placeholder="Label" style="width:110px;" /></td>
                                <td><input type="text" class="sc-bmc-field-label-ar" value="<?php echo esc_attr( $field['label_ar'] ); ?>" dir="rtl" placeholder="تسمية" style="width:110px;" /></td>
                                <td>
                                    <button type="button" class="button button-small sc-bmc-delete-field"
                                            data-object-type="taxonomy"
                                            data-object-slug="<?php echo esc_attr( $tax_slug ); ?>"
                                            data-field-key="<?php echo esc_attr( $field['key'] ); ?>"
                                            data-nonce="<?php echo esc_attr( $nonce ); ?>">
                                        <?php esc_html_e( 'Delete', 'space-core' ); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <button type="button" class="button sc-bmc-add-field" style="margin-top:6px;">
                        + <?php esc_html_e( 'Add Field', 'space-core' ); ?>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

    </div><!-- .sc-bmc-settings-columns -->

    <div class="sc-bmc-settings-footer" style="margin-top:20px;">
        <button type="button" class="button button-primary sc-bmc-save-all"
                data-nonce="<?php echo esc_attr( $nonce ); ?>">
            <?php esc_html_e( 'Save Settings', 'space-core' ); ?>
        </button>
        <span class="sc-save-status"></span>
    </div>
</div>

<style>
.sc-bmc-settings-columns { display: flex; gap: 32px; flex-wrap: wrap; }
.sc-bmc-settings-col { flex: 1; min-width: 300px; }
.sc-bmc-object-block { border: 1px solid #e0e0e0; border-radius: 4px; margin-bottom: 12px; overflow: hidden; }
.sc-bmc-object-header { display: flex; align-items: center; gap: 10px; padding: 10px 14px; background: #f9f9f9; }
.sc-bmc-slug { color: #888; font-size: .8rem; }
.sc-bmc-fields-wrap { padding: 10px 14px 14px; }
.sc-toggle-wrap { display: inline-flex; align-items: center; cursor: pointer; }
.sc-toggle-wrap input[type="checkbox"] { display: none; }
.sc-toggle-wrap .sc-toggle-slider {
    width: 36px; height: 20px; background: #ccc; border-radius: 10px;
    position: relative; transition: background .2s;
}
.sc-toggle-wrap .sc-toggle-slider::after {
    content: ''; position: absolute; width: 16px; height: 16px; border-radius: 50%;
    background: #fff; top: 2px; left: 2px; transition: left .2s;
}
.sc-toggle-wrap input:checked + .sc-toggle-slider { background: #2271b1; }
.sc-toggle-wrap input:checked + .sc-toggle-slider::after { left: 18px; }
</style>

<script>
(function ($) {

    // Toggle show/hide fields section.
    $(document).on('change', '.sc-bmc-toggle', function () {
        var $block = $(this).closest('.sc-bmc-object-block');
        var $wrap  = $block.find('.sc-bmc-fields-wrap');
        $wrap.toggleClass('sc-hidden', !this.checked);
    });

    // Add field row.
    $(document).on('click', '.sc-bmc-add-field', function () {
        var $tbody = $(this).prev('.sc-bmc-fields-table').find('tbody');
        $tbody.append(
            '<tr class="sc-bmc-field-row">' +
            '<td><input type="text" class="sc-bmc-field-key" placeholder="my_field" style="width:110px;" /></td>' +
            '<td><input type="text" class="sc-bmc-field-label-en" placeholder="Label" style="width:110px;" /></td>' +
            '<td><input type="text" class="sc-bmc-field-label-ar" dir="rtl" placeholder="تسمية" style="width:110px;" /></td>' +
            '<td><button type="button" class="button button-small sc-bmc-remove-new-field"><?php esc_html_e( 'Remove', 'space-core' ); ?></button></td>' +
            '</tr>'
        );
    });

    // Remove unsaved field row.
    $(document).on('click', '.sc-bmc-remove-new-field', function () {
        $(this).closest('tr').remove();
    });

    // Delete saved field via AJAX.
    $(document).on('click', '.sc-bmc-delete-field', function () {
        if ( !confirm('<?php echo esc_js( __( 'Delete this field?', 'space-core' ) ); ?>') ) return;
        var $btn = $(this);
        $.post(spaceCore.ajaxUrl, {
            action:      'sc_delete_bulk_field',
            nonce:       $btn.data('nonce'),
            object_type: $btn.data('object-type'),
            object_slug: $btn.data('object-slug'),
            field_key:   $btn.data('field-key'),
        }, function (res) {
            if (res.success) $btn.closest('tr').fadeOut(200, function () { $(this).remove(); });
        });
    });

    // Auto-slug from label_en.
    $(document).on('input', '.sc-bmc-field-label-en', function () {
        var $row = $(this).closest('tr');
        var $key = $row.find('.sc-bmc-field-key');
        if ($key.val() === '') {
            $key.val($(this).val().toLowerCase().replace(/[^\w\s]/g,'').replace(/\s+/g,'_'));
        }
    });

    // Collect full config and save.
    $(document).on('click', '.sc-bmc-save-all', function () {
        var $btn    = $(this);
        var $status = $btn.siblings('.sc-save-status');
        var data    = { post_types: {}, taxonomies: {} };

        $('.sc-bmc-object-block').each(function () {
            var $block      = $(this);
            var objectType  = $block.data('object-type');
            var slug        = $block.data('object-slug');
            var enabled     = $block.find('.sc-bmc-toggle').is(':checked');
            var fields      = [];
            var fieldKeys   = [];

            if ('post_type' === objectType) {
                $block.find('.sc-bmc-custom-field-checkbox:checked').each(function () {
                    fieldKeys.push($(this).val());
                });

                $block.find('.sc-bmc-manual-fields-section .sc-bmc-field-row').each(function () {
                    var key = $(this).find('.sc-bmc-field-key').val().trim();
                    if (!key) return;
                    fields.push({
                        key:      key,
                        label_en: $(this).find('.sc-bmc-field-label-en').val().trim(),
                        label_ar: $(this).find('.sc-bmc-field-label-ar').val().trim(),
                    });
                });
            } else {
                $block.find('.sc-bmc-field-row').each(function () {
                    var key = $(this).find('.sc-bmc-field-key').val().trim();
                    if (!key) return;
                    fields.push({
                        key:      key,
                        label_en: $(this).find('.sc-bmc-field-label-en').val().trim(),
                        label_ar: $(this).find('.sc-bmc-field-label-ar').val().trim(),
                    });
                });
            }

            if ('post_type' === objectType) {
                data.post_types[slug] = {
                    enabled: enabled,
                    field_keys: fieldKeys,
                    manual_fields: fields,
                    exclude_meta_key: ($block.find('.sc-bmc-exclude-meta-key').val() || '').trim()
                };
            } else {
                data.taxonomies[slug] = { enabled: enabled, fields: fields };
            }
        });

        var nonce = $btn.data('nonce');
        $btn.prop('disabled', true);
        $status.text('<?php echo esc_js( __( 'Saving…', 'space-core' ) ); ?>').css('color','#888');

        $.post(spaceCore.ajaxUrl, {
            action: 'sc_save_bulk_settings',
            nonce:  nonce,
            data:   JSON.stringify(data),
        }, function (res) {
            var msg = (res && res.data && res.data.message) ? res.data.message
                      : (res && res.success ? '<?php echo esc_js( __( 'Saved.', 'space-core' ) ); ?>'
                                           : '<?php echo esc_js( __( 'Error.', 'space-core' ) ); ?>');
            $status.text(msg).css('color', (res && res.success) ? '#2e7d32' : '#c62828');
            setTimeout(function () { $status.text(''); }, 3000);
        }).fail(function () {
            $status.text('<?php echo esc_js( __( 'Server error.', 'space-core' ) ); ?>').css('color','#c62828');
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });
})(jQuery);
</script>
