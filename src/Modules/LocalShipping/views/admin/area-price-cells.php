<?php defined( 'ABSPATH' ) || exit; ?>
<?php $fields = [ 'delivery_price' => __( 'Price', 'space-core' ), 'express_fee' => __( 'Express Fee', 'space-core' ), 'minimum_order' => __( 'Min Order', 'space-core' ), 'free_minimum_order' => __( 'Free Min', 'space-core' ) ]; ?>
<?php foreach ( $fields as $key => $label ) : ?>
    <td data-label="<?php echo esc_attr( $label ); ?>">
        <input type="number" step="0.001" min="0" class="sc-field sc-ls-price-field" data-key="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( (string) ( $area[ $key ] ?? '0' ) ); ?>">
    </td>
<?php endforeach; ?>
<td data-label="<?php esc_attr_e( 'Active', 'space-core' ); ?>"><?php $this->render_active_switch( (int) ( $area['is_active'] ?? 1 ) === 1 ); ?></td>
<td data-label="<?php esc_attr_e( 'Sort', 'space-core' ); ?>"><input type="number" class="sc-field sc-ls-sort-field" data-key="sort_order" value="<?php echo esc_attr( (string) ( $area['sort_order'] ?? '0' ) ); ?>"></td>
