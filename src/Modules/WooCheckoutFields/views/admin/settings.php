<?php

defined( 'ABSPATH' ) || exit;
?>
<div class="sc-module-section">
    <h2><?php esc_html_e( 'WooCommerce Checkout Fields', 'space-core' ); ?></h2>
    <p><?php esc_html_e( 'Drag rows to reorder. Toggle "On" to enable/disable. Add custom fields at the bottom. Save when done.', 'space-core' ); ?></p>

    <?php foreach ( $sections as $section ) :
        $fields = $list[ $section ] ?? [];
        ?>
        <h3 style="text-transform:capitalize;"><?php echo esc_html( ucfirst( $section ) ); ?></h3>
        <div class="sc-table-wrap">
            <table class="widefat striped sc-ajax-table sc-responsive-table sc-sortable-table"
                   id="sc-wcf-<?php echo esc_attr( $section ); ?>-table"
                   data-section="<?php echo esc_attr( $section ); ?>">
                <thead>
                <tr>
                    <th class="sc-sort-handle-col"></th>
                    <th><?php esc_html_e( 'Key', 'space-core' ); ?></th>
                    <th><?php esc_html_e( 'Label', 'space-core' ); ?></th>
                    <th><?php esc_html_e( 'Type', 'space-core' ); ?></th>
                    <th><?php esc_html_e( 'Width', 'space-core' ); ?></th>
                    <th><?php esc_html_e( 'Countries', 'space-core' ); ?></th>
                    <th><?php esc_html_e( 'Required', 'space-core' ); ?></th>
                    <th><?php esc_html_e( 'Enabled', 'space-core' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'space-core' ); ?></th>
                </tr>
                </thead>
                <tbody class="sc-sortable-body">
                <?php $priority = 10; foreach ( $fields as $key => $field ) : $priority += 10; $is_ls = ! empty( $field['local_shipping'] ); ?>
                    <?php if ( $is_ls ) : ?>
                    <tr class="sc-table-row sc-ls-field" data-key="<?php echo esc_attr( $key ); ?>" data-section="<?php echo esc_attr( $section ); ?>" data-custom="0" data-local-shipping="1" data-priority="<?php echo esc_attr( (string) $priority ); ?>">
                        <td class="sc-sort-handle" data-label="⠿">⠿</td>
                        <td data-label="<?php esc_attr_e( 'Key', 'space-core' ); ?>">
                            <code><?php echo esc_html( $key ); ?></code>
                            <input type="hidden" class="sc-field" data-field="key" value="<?php echo esc_attr( $key ); ?>"/>
                            <input type="hidden" class="sc-field" data-field="local_shipping" value="1"/>
                        </td>
                        <td data-label="<?php esc_attr_e( 'Label', 'space-core' ); ?>"><?php esc_html_e( 'Delivery Area', 'space-core' ); ?></td>
                        <td data-label="<?php esc_attr_e( 'Type', 'space-core' ); ?>"><span><?php esc_html_e( 'combo', 'space-core' ); ?></span></td>
                        <td data-label="<?php esc_attr_e( 'Width', 'space-core' ); ?>"><span>wide</span></td>
                        <td data-label="<?php esc_attr_e( 'Countries', 'space-core' ); ?>"><span>—</span></td>
                        <td data-label="<?php esc_attr_e( 'Required', 'space-core' ); ?>"><input type="checkbox" disabled /></td>
                        <td data-label="<?php esc_attr_e( 'Enabled', 'space-core' ); ?>"><input type="checkbox" checked disabled /></td>
                        <td class="sc-row-actions" data-label="<?php esc_attr_e( 'Actions', 'space-core' ); ?>"><span class="sc-badge"><?php esc_html_e( 'Local Shipping', 'space-core' ); ?></span></td>
                    </tr>
                    <?php else : ?>
                    <tr class="sc-table-row <?php echo $field['custom'] ? 'sc-custom-field' : 'sc-default-field'; ?>" data-key="<?php echo esc_attr( $key ); ?>" data-section="<?php echo esc_attr( $section ); ?>" data-custom="<?php echo $field['custom'] ? '1' : '0'; ?>" data-priority="<?php echo esc_attr( (string) $priority ); ?>">
                        <td class="sc-sort-handle" data-label="⠿">⠿</td>
                        <td data-label="<?php esc_attr_e( 'Key', 'space-core' ); ?>">
                            <?php if ( $field['custom'] ) : ?>
                                <input type="text" class="sc-field sc-slug-field" data-field="key" value="<?php echo esc_attr( $key ); ?>"/>
                            <?php else : ?>
                                <code><?php echo esc_html( $key ); ?></code>
                                <input type="hidden" class="sc-field" data-field="key" value="<?php echo esc_attr( $key ); ?>"/>
                            <?php endif; ?>
                        </td>
                        <td data-label="<?php esc_attr_e( 'Label', 'space-core' ); ?>"><input type="text" class="sc-field" data-field="label" value="<?php echo esc_attr( $field['label'] ); ?>"/></td>
                        <td data-label="<?php esc_attr_e( 'Type', 'space-core' ); ?>">
                            <?php if ( $field['custom'] ) : ?>
                                <select class="sc-field" data-field="type">
                                    <?php foreach ( $types as $t_key => $t_label ) : ?>
                                        <option value="<?php echo esc_attr( $t_key ); ?>" <?php selected( $field['type'], $t_key ); ?>><?php echo esc_html( $t_label ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else : ?>
                                <span><?php echo esc_html( $field['type'] ); ?></span>
                                <input type="hidden" class="sc-field" data-field="type" value="<?php echo esc_attr( $field['type'] ); ?>"/>
                            <?php endif; ?>
                        </td>
                        <td data-label="<?php esc_attr_e( 'Width', 'space-core' ); ?>">
                            <select class="sc-field" data-field="width">
                                <option value="wide" <?php selected( $field['width'] ?? 'wide', 'wide' ); ?>><?php esc_html_e( 'Full (1 col)', 'space-core' ); ?></option>
                                <option value="first" <?php selected( $field['width'] ?? 'wide', 'first' ); ?>><?php esc_html_e( 'Left (2 col)', 'space-core' ); ?></option>
                                <option value="last" <?php selected( $field['width'] ?? 'wide', 'last' ); ?>><?php esc_html_e( 'Right (2 col)', 'space-core' ); ?></option>
                            </select>
                        </td>
                        <td data-label="<?php esc_attr_e( 'Countries', 'space-core' ); ?>"><input type="text" class="sc-field" data-field="show_countries" value="<?php echo esc_attr( $field['show_countries'] ?? '' ); ?>" placeholder="KW,SA,AE" style="width:90px;" /></td>
                        <td data-label="<?php esc_attr_e( 'Required', 'space-core' ); ?>"><input type="checkbox" class="sc-field sc-bool-field" data-field="required" <?php checked( $field['required'] ?? false ); ?> /></td>
                        <td data-label="<?php esc_attr_e( 'Enabled', 'space-core' ); ?>"><input type="checkbox" class="sc-field sc-bool-field" data-field="enabled" <?php checked( $field['enabled'] ?? true ); ?> /></td>
                        <td class="sc-row-actions" data-label="<?php esc_attr_e( 'Actions', 'space-core' ); ?>">
                            <?php if ( $field['custom'] ) : ?>
                                <button type="button" class="button button-small sc-wcf-delete-row" data-key="<?php echo esc_attr( $key ); ?>" data-section="<?php echo esc_attr( $section ); ?>"><?php esc_html_e( 'Delete', 'space-core' ); ?></button>
                            <?php else : ?>
                                <span class="sc-badge"><?php esc_html_e( 'Default', 'space-core' ); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                </tbody>
            </table>

            <div class="sc-table-footer">
                <button type="button" class="button sc-wcf-add-row" data-section="<?php echo esc_attr( $section ); ?>">+ <?php esc_html_e( 'Add Custom Field', 'space-core' ); ?></button>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="sc-table-footer sc-wcf-global-footer">
        <button type="button" class="button button-primary sc-wcf-save-all" data-action="sc_save_checkout_fields" data-nonce="<?php echo esc_attr( $nonce ); ?>"><?php esc_html_e( 'Save All Fields', 'space-core' ); ?></button>
        <button type="button" class="button sc-wcf-reset" data-action="sc_reset_checkout_fields" data-nonce="<?php echo esc_attr( $nonce ); ?>"><?php esc_html_e( 'Reset to Defaults', 'space-core' ); ?></button>
        <span class="sc-save-status"></span>
    </div>
</div>
