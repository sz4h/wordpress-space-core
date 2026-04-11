<?php

defined( 'ABSPATH' ) || exit;
?>
<div class="sc-module-section">
    <h2><?php esc_html_e( 'Custom Post Types', 'space-core' ); ?></h2>
    <p><?php esc_html_e( 'Add, edit, and delete custom post types. Changes take effect immediately after saving.', 'space-core' ); ?></p>

    <div class="sc-table-wrap">
        <table class="widefat striped sc-ajax-table sc-responsive-table" id="sc-cpt-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Name', 'space-core' ); ?></th>
                    <th><?php esc_html_e( 'Plural', 'space-core' ); ?></th>
                    <th><?php esc_html_e( 'Slug', 'space-core' ); ?></th>
                    <th><?php esc_html_e( 'Menu Icon', 'space-core' ); ?></th>
                    <th><?php esc_html_e( 'Supports', 'space-core' ); ?></th>
                    <th><?php esc_html_e( 'Public', 'space-core' ); ?></th>
                    <th><?php esc_html_e( 'Archive', 'space-core' ); ?></th>
                    <th>REST</th>
                    <th><?php esc_html_e( 'Actions', 'space-core' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $defs as $i => $cpt ) : ?>
                <tr class="sc-table-row" data-index="<?php echo esc_attr( (string) $i ); ?>">
                    <td data-label="<?php esc_attr_e( 'Name', 'space-core' ); ?>"><input type="text" class="sc-field" data-field="name" value="<?php echo esc_attr( $cpt['name'] ); ?>" placeholder="Project" /></td>
                    <td data-label="<?php esc_attr_e( 'Plural', 'space-core' ); ?>"><input type="text" class="sc-field" data-field="plural" value="<?php echo esc_attr( $cpt['plural'] ); ?>" placeholder="Projects" /></td>
                    <td data-label="<?php esc_attr_e( 'Slug', 'space-core' ); ?>"><input type="text" class="sc-field sc-slug-field" data-field="slug" value="<?php echo esc_attr( $cpt['slug'] ); ?>" placeholder="project" /></td>
                    <td data-label="<?php esc_attr_e( 'Menu Icon', 'space-core' ); ?>"><input type="text" class="sc-field" data-field="menu_icon" value="<?php echo esc_attr( $cpt['menu_icon'] ?? 'dashicons-admin-post' ); ?>" placeholder="dashicons-admin-post" /></td>
                    <td data-label="<?php esc_attr_e( 'Supports', 'space-core' ); ?>">
                        <select class="sc-field sc-multiselect" data-field="supports" multiple size="4">
                            <?php foreach ( $supports_all as $s_key => $s_label ) : ?>
                                <option value="<?php echo esc_attr( $s_key ); ?>" <?php echo in_array( $s_key, (array) ( $cpt['supports'] ?? [] ), true ) ? 'selected' : ''; ?>>
                                    <?php echo esc_html( $s_label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td data-label="<?php esc_attr_e( 'Public', 'space-core' ); ?>"><input type="checkbox" class="sc-field sc-bool-field" data-field="public" <?php checked( $cpt['public'] ?? true ); ?> /></td>
                    <td data-label="<?php esc_attr_e( 'Archive', 'space-core' ); ?>"><input type="checkbox" class="sc-field sc-bool-field" data-field="has_archive" <?php checked( $cpt['has_archive'] ?? false ); ?> /></td>
                    <td data-label="REST"><input type="checkbox" class="sc-field sc-bool-field" data-field="show_in_rest" <?php checked( $cpt['show_in_rest'] ?? true ); ?> /></td>
                    <td class="sc-row-actions" data-label="<?php esc_attr_e( 'Actions', 'space-core' ); ?>">
                        <button type="button" class="button button-small sc-delete-row" data-action="sc_delete_cpt" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-slug="<?php echo esc_attr( $cpt['slug'] ); ?>">
                            <?php esc_html_e( 'Delete', 'space-core' ); ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="sc-table-footer">
            <button type="button" class="button sc-add-row" data-table="sc-cpt-table" data-template="sc-cpt-row-tpl">+ <?php esc_html_e( 'Add Post Type', 'space-core' ); ?></button>
            <button type="button" class="button button-primary sc-save-table" data-table="sc-cpt-table" data-action="sc_save_cpts" data-nonce="<?php echo esc_attr( $nonce ); ?>"><?php esc_html_e( 'Save All', 'space-core' ); ?></button>
            <span class="sc-save-status"></span>
        </div>
    </div>
</div>

<script type="text/html" id="sc-cpt-row-tpl">
<tr class="sc-table-row sc-new-row" data-index="__INDEX__">
    <td data-label="<?php esc_attr_e( 'Name', 'space-core' ); ?>"><input type="text" class="sc-field" data-field="name" value="" placeholder="Project" /></td>
    <td data-label="<?php esc_attr_e( 'Plural', 'space-core' ); ?>"><input type="text" class="sc-field" data-field="plural" value="" placeholder="Projects" /></td>
    <td data-label="<?php esc_attr_e( 'Slug', 'space-core' ); ?>"><input type="text" class="sc-field sc-slug-field" data-field="slug" value="" placeholder="project" /></td>
    <td data-label="<?php esc_attr_e( 'Menu Icon', 'space-core' ); ?>"><input type="text" class="sc-field" data-field="menu_icon" value="dashicons-admin-post" placeholder="dashicons-admin-post" /></td>
    <td data-label="<?php esc_attr_e( 'Supports', 'space-core' ); ?>">
        <select class="sc-field sc-multiselect" data-field="supports" multiple size="4">
            <?php foreach ( $supports_all as $s_key => $s_label ) : ?>
                <option value="<?php echo esc_attr( $s_key ); ?>" <?php selected( in_array( $s_key, [ 'title', 'editor', 'thumbnail' ], true ) ); ?>><?php echo esc_html( $s_label ); ?></option>
            <?php endforeach; ?>
        </select>
    </td>
    <td data-label="<?php esc_attr_e( 'Public', 'space-core' ); ?>"><input type="checkbox" class="sc-field sc-bool-field" data-field="public" checked /></td>
    <td data-label="<?php esc_attr_e( 'Archive', 'space-core' ); ?>"><input type="checkbox" class="sc-field sc-bool-field" data-field="has_archive" /></td>
    <td data-label="REST"><input type="checkbox" class="sc-field sc-bool-field" data-field="show_in_rest" checked /></td>
    <td class="sc-row-actions" data-label="<?php esc_attr_e( 'Actions', 'space-core' ); ?>"><button type="button" class="button button-small sc-remove-new-row"><?php esc_html_e( 'Remove', 'space-core' ); ?></button></td>
</tr>
</script>
