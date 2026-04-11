<?php defined( 'ABSPATH' ) || exit; ?>
<?php if ( ! $selected_city_id ) : ?>
    <tr class="sc-ls-empty-row"><td colspan="9"><?php esc_html_e( 'Select a country with at least one city before adding areas.', 'space-core' ); ?></td></tr>
<?php else : ?>
    <?php foreach ( $areas as $area ) { $this->render_area_table_row( $area, $cities, $selected_city_id ); } ?>
    <?php $this->render_area_template_row( $cities, $selected_city_id ); ?>
<?php endif; ?>
