<?php defined( 'ABSPATH' ) || exit; ?>
<tr data-id="<?php echo esc_attr( (string) $city['id'] ); ?>">
    <td data-label="<?php esc_attr_e( 'Name (En/Ar)', 'space-core' ); ?>">
        <?php $this->render_name_fields( $name ); ?>
        <input type="hidden" class="sc-field" data-key="country_code" value="<?php echo esc_attr( $row_country ); ?>">
    </td>
    <td data-label="<?php esc_attr_e( 'Active', 'space-core' ); ?>"><?php $this->render_active_switch( (int) $city['is_active'] === 1 ); ?></td>
    <td data-label="<?php esc_attr_e( 'Sort', 'space-core' ); ?>"><input type="number" class="sc-field sc-ls-sort-field" data-key="sort_order" value="<?php echo esc_attr( (string) $city['sort_order'] ); ?>"></td>
    <td data-label="<?php esc_attr_e( 'Actions', 'space-core' ); ?>"><?php $this->render_row_actions( true ); ?></td>
</tr>
