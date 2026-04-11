<?php defined( 'ABSPATH' ) || exit; ?>
<?php $this->render_country_filter( $selected_country, $countries, 'cities' ); ?>
<div class="sc-table-wrap">
    <table class="widefat sc-ajax-table sc-responsive-table" id="sc-cities-table" data-action-save="sc_save_ls_city" data-action-delete="sc_delete_ls_city" data-nonce="<?php echo esc_attr( wp_create_nonce( 'sc_local_shipping_nonce' ) ); ?>" data-country="<?php echo esc_attr( $selected_country ); ?>">
        <thead><tr><th><?php esc_html_e( 'Name (En/Ar)', 'space-core' ); ?></th><th><?php esc_html_e( 'Active', 'space-core' ); ?></th><th><?php esc_html_e( 'Sort', 'space-core' ); ?></th><th><?php esc_html_e( 'Actions', 'space-core' ); ?></th></tr></thead>
        <tbody><?php $this->render_city_table_rows( $cities, $selected_country ); ?></tbody>
    </table>
    <p><button type="button" class="button sc-add-row" data-table="sc-cities-table">+ <?php esc_html_e( 'Add City', 'space-core' ); ?></button></p>
</div>
