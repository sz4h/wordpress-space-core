<?php defined( 'ABSPATH' ) || exit; ?>
<div class="form-row <?php echo esc_attr( $class_str ); ?> sc-combo-wrap"
     id="<?php echo esc_attr( $key ); ?>_field">
    <label><?php echo wp_kses_post( $args['label'] . $required_html ); ?></label>

    <input type="hidden" name="billing_sc_area_id" id="billing_sc_area_id"
           value="<?php echo esc_attr( $saved_area ?: '' ); ?>">
    <input type="hidden" name="billing_sc_city_id" id="billing_sc_city_id"
           value="<?php echo esc_attr( $saved_city_id ?: '' ); ?>">

    <div class="sc-combo-trigger" id="sc-combo-trigger" tabindex="0"
         role="combobox" aria-haspopup="listbox" aria-expanded="false" aria-controls="sc-combo-panel">
        <span class="sc-combo-placeholder<?php echo $saved_name ? ' has-value' : ''; ?>">
            <?php echo $saved_name ? esc_html( $saved_name ) : esc_html__( '-- Select delivery area --', 'space-core' ); ?>
        </span>
        <span class="sc-combo-arrow dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
    </div>

    <div class="sc-combo-panel" id="sc-combo-panel" role="listbox" style="display:none;">
        <div class="sc-combo-search-wrap">
            <span class="dashicons dashicons-search" aria-hidden="true"></span>
            <input type="text" class="sc-combo-search"
                   placeholder="<?php esc_attr_e( 'Search for an area...', 'space-core' ); ?>"
                   autocomplete="off">
        </div>
        <div class="sc-combo-list">
            <?php foreach ( $grouped as $cid => $group ) :
                $city_name = \Space\Core\Modules\LocalShipping\AreasDB::resolve_name( \Space\Core\Modules\LocalShipping\AreasDB::decode_name( $group['city']['name'] ) );
                ?>
                <div class="sc-combo-group" data-city="<?php echo esc_attr( $cid ); ?>">
                    <div class="sc-combo-group-header">
                        <span><?php echo esc_html( $city_name ); ?></span>
                    </div>
                    <?php foreach ( $group['areas'] as $area ) :
                        $area_name   = \Space\Core\Modules\LocalShipping\AreasDB::resolve_name( \Space\Core\Modules\LocalShipping\AreasDB::decode_name( $area['name'] ) );
                        $is_selected = ( $saved_area === (int) $area['id'] );
                        ?>
                        <div class="sc-combo-item<?php echo $is_selected ? ' sc-selected' : ''; ?>"
                             role="option"
                             aria-selected="<?php echo $is_selected ? 'true' : 'false'; ?>"
                             data-value="<?php echo esc_attr( $area['id'] ); ?>"
                             data-city="<?php echo esc_attr( $cid ); ?>"
                             data-price="<?php echo esc_attr( cc_amount( (float) $area['delivery_price'] ) ); ?>"
                             data-express="<?php echo esc_attr( cc_amount( (float) $area['express_fee'] ) ); ?>"
                             data-minimum="<?php echo esc_attr( cc_amount( (float) $area['minimum_order'] ) ); ?>"
                             data-freeminimum="<?php echo esc_attr( cc_amount( (float) $area['free_minimum_order'] ) ); ?>"
                             data-name="<?php echo esc_attr( $area_name ); ?>">
                            <span class="sc-item-name"><?php echo esc_html( $area_name ); ?></span>
                            <span class="sc-item-price">
                                <?php echo esc_html( $this->format_area_price_label( $area, $currency ) ); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="sc-combo-no-results" style="display:none;">
            <?php esc_html_e( 'No results match your search', 'space-core' ); ?>
        </div>
    </div>
</div>
