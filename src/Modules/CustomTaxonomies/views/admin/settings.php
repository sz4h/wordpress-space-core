<?php

defined( 'ABSPATH' ) || exit;
?>
<div class="sc-module-section">
    <h2><?php esc_html_e( 'Custom Taxonomies', 'space-core' ); ?></h2>
    <p><?php esc_html_e( 'Add, edit, and delete custom taxonomies. Changes take effect immediately after saving.', 'space-core' ); ?></p>

    <div class="sc-table-wrap">
        <table class="widefat striped sc-ajax-table sc-responsive-table" id="sc-tax-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Name (Singular)', 'space-core' ); ?></th>
                    <th><?php esc_html_e( 'Plural', 'space-core' ); ?></th>
                    <th><?php esc_html_e( 'Slug', 'space-core' ); ?></th>
                    <th><?php esc_html_e( 'Post Types', 'space-core' ); ?></th>
                    <th><?php esc_html_e( 'Hierarchical', 'space-core' ); ?></th>
                    <th><?php esc_html_e( 'REST', 'space-core' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'space-core' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $defs as $i => $tax ) : ?>
                <tr class="sc-table-row" data-index="<?php echo esc_attr( (string) $i ); ?>">
                    <td data-label="<?php esc_attr_e( 'Name', 'space-core' ); ?>"><input type="text" class="sc-field" data-field="name" value="<?php echo esc_attr( $tax['name'] ); ?>" placeholder="Category" /></td>
                    <td data-label="<?php esc_attr_e( 'Plural', 'space-core' ); ?>"><input type="text" class="sc-field" data-field="plural" value="<?php echo esc_attr( $tax['plural'] ); ?>" placeholder="Categories" /></td>
                    <td data-label="<?php esc_attr_e( 'Slug', 'space-core' ); ?>"><input type="text" class="sc-field sc-slug-field" data-field="slug" value="<?php echo esc_attr( $tax['slug'] ); ?>" placeholder="category" /></td>
                    <td data-label="<?php esc_attr_e( 'Post Types', 'space-core' ); ?>">
                        <select class="sc-field sc-multiselect" data-field="post_types" multiple size="3">
                            <?php foreach ( $pt_options as $pt_slug => $pt_label ) : ?>
                                <option value="<?php echo esc_attr( $pt_slug ); ?>" <?php echo in_array( $pt_slug, (array) $tax['post_types'], true ) ? 'selected' : ''; ?>><?php echo esc_html( $pt_label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td data-label="<?php esc_attr_e( 'Hierarchical', 'space-core' ); ?>"><input type="checkbox" class="sc-field sc-bool-field" data-field="hierarchical" <?php checked( $tax['hierarchical'] ?? false ); ?> /></td>
                    <td data-label="REST"><input type="checkbox" class="sc-field sc-bool-field" data-field="show_in_rest" <?php checked( $tax['show_in_rest'] ?? true ); ?> /></td>
                    <td class="sc-row-actions" data-label="<?php esc_attr_e( 'Actions', 'space-core' ); ?>">
                        <button type="button" class="button button-small sc-delete-row" data-action="sc_delete_taxonomy" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-slug="<?php echo esc_attr( $tax['slug'] ); ?>"><?php esc_html_e( 'Delete', 'space-core' ); ?></button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="sc-table-footer">
            <button type="button" class="button sc-add-row" data-table="sc-tax-table" data-template="sc-tax-row-tpl">+ <?php esc_html_e( 'Add Taxonomy', 'space-core' ); ?></button>
            <button type="button" class="button button-primary sc-save-table" data-table="sc-tax-table" data-action="sc_save_taxonomies" data-nonce="<?php echo esc_attr( $nonce ); ?>"><?php esc_html_e( 'Save All', 'space-core' ); ?></button>
            <span class="sc-save-status"></span>
        </div>
    </div>
</div>

<script type="text/html" id="sc-tax-row-tpl">
<tr class="sc-table-row sc-new-row" data-index="__INDEX__">
    <td data-label="<?php esc_attr_e( 'Name', 'space-core' ); ?>"><input type="text" class="sc-field" data-field="name" value="" placeholder="Genre" /></td>
    <td data-label="<?php esc_attr_e( 'Plural', 'space-core' ); ?>"><input type="text" class="sc-field" data-field="plural" value="" placeholder="Genres" /></td>
    <td data-label="<?php esc_attr_e( 'Slug', 'space-core' ); ?>"><input type="text" class="sc-field sc-slug-field" data-field="slug" value="" placeholder="genre" /></td>
    <td data-label="<?php esc_attr_e( 'Post Types', 'space-core' ); ?>">
        <select class="sc-field sc-multiselect" data-field="post_types" multiple size="3">
            <?php foreach ( $pt_options as $pt_slug => $pt_label ) : ?>
                <option value="<?php echo esc_attr( $pt_slug ); ?>"><?php echo esc_html( $pt_label ); ?></option>
            <?php endforeach; ?>
        </select>
    </td>
    <td data-label="<?php esc_attr_e( 'Hierarchical', 'space-core' ); ?>"><input type="checkbox" class="sc-field sc-bool-field" data-field="hierarchical" /></td>
    <td data-label="REST"><input type="checkbox" class="sc-field sc-bool-field" data-field="show_in_rest" checked /></td>
    <td class="sc-row-actions" data-label="<?php esc_attr_e( 'Actions', 'space-core' ); ?>"><button type="button" class="button button-small sc-remove-new-row"><?php esc_html_e( 'Remove', 'space-core' ); ?></button></td>
</tr>
</script>
