<?php defined( 'ABSPATH' ) || exit; ?>
<ul class="sc-ls-city-list">
    <?php if ( empty( $cities ) ) : ?>
        <li class="sc-ls-empty-state"><?php esc_html_e( 'No cities found for this country.', 'space-core' ); ?></li>
    <?php endif; ?>
    <?php foreach ( $cities as $city ) : $city_id = (int) $city['id']; $is_selected = $city_id === $selected_city_id; ?>
        <li>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=sc-local-shipping&tab=areas&country=' . rawurlencode( $country ) . '&city_id=' . $city_id ) ); ?>" class="sc-ls-city-link<?php echo $is_selected ? ' sc-ls-city-link-active' : ''; ?>" data-city-id="<?php echo esc_attr( (string) $city_id ); ?>" <?php echo $is_selected ? 'aria-current="page"' : ''; ?>>
                <?php echo esc_html( $this->city_display_name( $city ) ); ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>
