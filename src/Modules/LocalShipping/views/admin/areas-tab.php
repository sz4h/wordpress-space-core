<?php defined( 'ABSPATH' ) || exit; ?>
<?php $this->render_country_filter( $selected_country, $countries, 'areas' ); ?>
<div class="sc-ls-areas-layout">
    <aside class="sc-ls-city-menu" aria-label="<?php esc_attr_e( 'Cities', 'space-core' ); ?>">
        <h2><?php esc_html_e( 'Cities', 'space-core' ); ?></h2>
        <?php $this->render_city_menu( $cities, $selected_city_id, $selected_country ); ?>
    </aside>
    <div class="sc-ls-areas-panel">
        <h2 class="sc-ls-areas-title">
            <?php if ( $selected_city_name ) : ?>
                <?php printf( esc_html__( 'Areas in %s', 'space-core' ), esc_html( $selected_city_name ) ); ?>
            <?php else : ?>
                <?php esc_html_e( 'Areas', 'space-core' ); ?>
            <?php endif; ?>
        </h2>
        <div class="sc-table-wrap">
            <table class="widefat sc-ajax-table sc-responsive-table" id="sc-areas-table" data-action-save="sc_save_ls_area" data-action-delete="sc_delete_ls_area" data-nonce="<?php echo esc_attr( wp_create_nonce( 'sc_local_shipping_nonce' ) ); ?>" data-country="<?php echo esc_attr( $selected_country ); ?>" data-selected-city="<?php echo esc_attr( (string) $selected_city_id ); ?>">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Name (En/Ar)', 'space-core' ); ?></th>
                        <?php $this->render_area_copy_header( __( 'Price', 'space-core' ), 'delivery_price' ); ?>
                        <?php $this->render_area_copy_header( __( 'Express Fee', 'space-core' ), 'express_fee' ); ?>
                        <?php $this->render_area_copy_header( __( 'Min Order', 'space-core' ), 'minimum_order' ); ?>
                        <?php $this->render_area_copy_header( __( 'Free Min', 'space-core' ), 'free_minimum_order' ); ?>
                        <?php $this->render_area_copy_header( __( 'Active', 'space-core' ), 'is_active' ); ?>
                        <th><?php esc_html_e( 'Sort', 'space-core' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'space-core' ); ?></th>
                    </tr>
                </thead>
                <tbody><?php $this->render_area_table_rows( $city_areas, $cities, $selected_city_id ); ?></tbody>
            </table>
            <div class="sc-table-footer sc-ls-area-actions">
                <button type="button" class="button sc-add-row" data-table="sc-areas-table" <?php disabled( ! $selected_city_id ); ?>>+ <?php esc_html_e( 'Add Area', 'space-core' ); ?></button>
                <button type="button" class="button button-primary sc-ls-save-visible" data-table="sc-areas-table" <?php disabled( ! $selected_city_id ); ?>><?php esc_html_e( 'Save All', 'space-core' ); ?></button>
                <span class="sc-save-status sc-ls-save-all-status" aria-live="polite"></span>
            </div>
        </div>
    </div>
</div>
