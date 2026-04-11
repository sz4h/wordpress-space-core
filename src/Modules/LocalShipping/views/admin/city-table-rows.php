<?php defined( 'ABSPATH' ) || exit; ?>
<?php foreach ( $cities as $city ) { $this->render_city_table_row( $city, $country ); } ?>
<?php $this->render_city_template_row( $country ); ?>
