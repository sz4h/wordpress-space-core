<?php defined( 'ABSPATH' ) || exit; ?>
<tr data-id="<?php echo esc_attr( (string) $area['id'] ); ?>" data-city-id="<?php echo esc_attr( (string) $area['city_id'] ); ?>">
    <td data-label="<?php esc_attr_e( 'Name (En/Ar)', 'space-core' ); ?>">
        <input type="hidden" name="city_id" class="sc-field" data-key="city_id" value="<?php echo esc_attr( (string) $area['city_id'] ); ?>">
        <?php $this->render_name_fields( $name ); ?>
    </td>
    <?php $this->render_area_price_cells( $area ); ?>
    <td data-label="<?php esc_attr_e( 'Actions', 'space-core' ); ?>"><?php $this->render_row_actions( true ); ?></td>
</tr>
