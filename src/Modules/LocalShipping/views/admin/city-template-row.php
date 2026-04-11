<?php defined( 'ABSPATH' ) || exit; ?>
<tr class="sc-new-row-template" style="display:none;" data-id="0">
    <td data-label="<?php esc_attr_e( 'Name (En/Ar)', 'space-core' ); ?>">
        <?php $this->render_name_fields( [] ); ?>
        <input type="hidden" class="sc-field" data-key="country_code" value="<?php echo esc_attr( $country ); ?>">
    </td>
    <td data-label="<?php esc_attr_e( 'Active', 'space-core' ); ?>"><?php $this->render_active_switch( true ); ?></td>
    <td data-label="<?php esc_attr_e( 'Sort', 'space-core' ); ?>"><input type="number" class="sc-field sc-ls-sort-field" data-key="sort_order" value="0"></td>
    <td data-label="<?php esc_attr_e( 'Actions', 'space-core' ); ?>"><?php $this->render_row_actions( false ); ?></td>
</tr>
