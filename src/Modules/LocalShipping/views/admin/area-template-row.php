<?php defined( 'ABSPATH' ) || exit; ?>
<tr class="sc-new-row-template" style="display:none;" data-id="0" data-city-id="<?php echo esc_attr( (string) $selected_city_id ); ?>">
    <td data-label="<?php esc_attr_e( 'Name (En/Ar)', 'space-core' ); ?>">
        <input type="hidden" class="sc-field" data-key="city_id" name="city_id" value="<?php echo esc_attr( (string) $selected_city_id ); ?>">
        <?php $this->render_name_fields( [] ); ?>
    </td>
    <?php $this->render_area_price_cells( [] ); ?>
    <td data-label="<?php esc_attr_e( 'Actions', 'space-core' ); ?>"><?php $this->render_row_actions( false ); ?></td>
</tr>
