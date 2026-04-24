<?php

defined( 'ABSPATH' ) || exit;
?>
<div class="sc-module-section">
    <h2><?php esc_html_e( 'Custom Fields', 'space-core' ); ?></h2>
    <p><?php esc_html_e( 'Define meta fields per post type. For Select fields, enter one option per line in both English and Arabic columns using the same order.', 'space-core' ); ?></p>
    <p>
        <?php esc_html_e( 'Note: short-code.', 'space-core' ); ?>
        <br>
        [sc_custom_field key="key" post_id="" format="value/label_value" fallback="Default"]
    </p>
    <div class="sc-table-wrap">
        <table class="widefat striped sc-ajax-table sc-responsive-table" id="sc-cf-table">
            <thead>
            <tr>
                <th><?php esc_html_e( 'Label EN', 'space-core' ); ?></th>
                <th><?php esc_html_e( 'Label AR', 'space-core' ); ?></th>
                <th><?php esc_html_e( 'Key', 'space-core' ); ?></th>
                <th><?php esc_html_e( 'Type', 'space-core' ); ?></th>
                <th><?php esc_html_e( 'Post Type', 'space-core' ); ?></th>
                <th><?php esc_html_e( 'Choices EN', 'space-core' ); ?></th>
                <th><?php esc_html_e( 'Choices AR', 'space-core' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'space-core' ); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ( $defs as $i => $field ) : ?>
                <tr class="sc-table-row" data-index="<?php echo esc_attr( (string) $i ); ?>">
                    <td data-label="<?php esc_attr_e( 'Label EN', 'space-core' ); ?>">
                        <input type="text" class="sc-field" data-field="label_en"
                               value="<?php echo esc_attr( $field['label_en'] ?? $field['label'] ?? '' ); ?>"/>
                    </td>
                    <td data-label="<?php esc_attr_e( 'Label AR', 'space-core' ); ?>">
                        <input type="text" class="sc-field" data-field="label_ar"
                               value="<?php echo esc_attr( $field['label_ar'] ?? '' ); ?>" dir="rtl"/>
                    </td>
                    <td data-label="<?php esc_attr_e( 'Key', 'space-core' ); ?>">
                        <input type="text" class="sc-field sc-slug-field" data-field="key"
                               value="<?php echo esc_attr( $field['key'] ); ?>"/>
                    </td>
                    <td data-label="<?php esc_attr_e( 'Type', 'space-core' ); ?>">
                        <select class="sc-field sc-type-select" data-field="type">
                            <?php foreach ( $types as $t_key => $t_label ) : ?>
                                <option value="<?php echo esc_attr( $t_key ); ?>" <?php selected( $field['type'], $t_key ); ?>><?php echo esc_html( $t_label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td data-label="<?php esc_attr_e( 'Post Type', 'space-core' ); ?>">
                        <select class="sc-field" data-field="post_type">
                            <?php foreach ( $post_types as $pt ) : ?>
                                <option value="<?php echo esc_attr( $pt->name ); ?>" <?php selected( $field['post_type'], $pt->name ); ?>><?php echo esc_html( $pt->labels->singular_name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td data-label="<?php esc_attr_e( 'Choices EN', 'space-core' ); ?>"
                        class="sc-choices-cell <?php echo 'select' === $field['type'] ? '' : 'sc-hidden'; ?>">
                        <textarea class="sc-field" data-field="choices_en" rows="3"
                                  placeholder="<?php esc_attr_e( "Option A\nOption B", 'space-core' ); ?>"><?php echo esc_textarea( implode( "\n", array_map( static fn( $choice ) => $choice['label_en'] ?? $choice['value'] ?? '', (array) ( $field['choices'] ?? [] ) ) ) ); ?></textarea>
                    </td>
                    <td data-label="<?php esc_attr_e( 'Choices AR', 'space-core' ); ?>"
                        class="sc-choices-cell <?php echo 'select' === $field['type'] ? '' : 'sc-hidden'; ?>">
                        <textarea class="sc-field" data-field="choices_ar" rows="3"
                                  placeholder="<?php esc_attr_e( "الخيار أ\nالخيار ب", 'space-core' ); ?>"
                                  dir="rtl"><?php echo esc_textarea( implode( "\n", array_map( static fn( $choice ) => $choice['label_ar'] ?? '', (array) ( $field['choices'] ?? [] ) ) ) ); ?></textarea>
                    </td>
                    <td class="sc-row-actions" data-label="<?php esc_attr_e( 'Actions', 'space-core' ); ?>">
                        <button type="button" class="button button-small sc-delete-row"
                                data-action="sc_delete_custom_field" data-nonce="<?php echo esc_attr( $nonce ); ?>"
                                data-key="<?php echo esc_attr( $field['key'] ); ?>"
                                data-post_type="<?php echo esc_attr( $field['post_type'] ); ?>">
                            <?php esc_html_e( 'Delete', 'space-core' ); ?>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="sc-table-footer">
            <button type="button" class="button sc-add-row" data-table="sc-cf-table" data-template="sc-cf-row-tpl">
                + <?php esc_html_e( 'Add Field', 'space-core' ); ?>
            </button>
            <button type="button" class="button button-primary sc-save-table" data-table="sc-cf-table"
                    data-action="sc_save_custom_fields" data-nonce="<?php echo esc_attr( $nonce ); ?>">
                <?php esc_html_e( 'Save All', 'space-core' ); ?>
            </button>
            <span class="sc-save-status"></span>
        </div>
    </div>
</div>

<script type="text/html" id="sc-cf-row-tpl">
    <tr class="sc-table-row sc-new-row" data-index="__INDEX__">
        <td data-label="<?php esc_attr_e( 'Label EN', 'space-core' ); ?>">
            <input type="text" class="sc-field" data-field="label_en" value="" placeholder="My Field"/>
        </td>
        <td data-label="<?php esc_attr_e( 'Label AR', 'space-core' ); ?>">
            <input type="text" class="sc-field" data-field="label_ar" value="" placeholder="حقلي" dir="rtl"/>
        </td>
        <td data-label="<?php esc_attr_e( 'Key', 'space-core' ); ?>">
            <input type="text" class="sc-field sc-slug-field" data-field="key" value="" placeholder="my_field"/>
        </td>
        <td data-label="<?php esc_attr_e( 'Type', 'space-core' ); ?>">
            <select class="sc-field sc-type-select" data-field="type">
                <?php foreach ( $types as $t_key => $t_label ) : ?>
                    <option value="<?php echo esc_attr( $t_key ); ?>"><?php echo esc_html( $t_label ); ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td data-label="<?php esc_attr_e( 'Post Type', 'space-core' ); ?>">
            <select class="sc-field" data-field="post_type">
                <?php foreach ( $post_types as $pt ) : ?>
                    <option value="<?php echo esc_attr( $pt->name ); ?>"><?php echo esc_html( $pt->labels->singular_name ); ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td data-label="<?php esc_attr_e( 'Choices EN', 'space-core' ); ?>" class="sc-choices-cell sc-hidden">
            <textarea class="sc-field" data-field="choices_en" rows="3"
                      placeholder="<?php esc_attr_e( "Option A\nOption B", 'space-core' ); ?>"></textarea>
        </td>
        <td data-label="<?php esc_attr_e( 'Choices AR', 'space-core' ); ?>" class="sc-choices-cell sc-hidden">
            <textarea class="sc-field" data-field="choices_ar" rows="3"
                      placeholder="<?php esc_attr_e( "الخيار أ\nالخيار ب", 'space-core' ); ?>" dir="rtl"></textarea>
        </td>
        <td class="sc-row-actions" data-label="<?php esc_attr_e( 'Actions', 'space-core' ); ?>">
            <button type="button"
                    class="button button-small sc-remove-new-row"><?php esc_html_e( 'Remove', 'space-core' ); ?></button>
        </td>
    </tr>
</script>
