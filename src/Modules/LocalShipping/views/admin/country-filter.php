<?php defined( 'ABSPATH' ) || exit; ?>
<div class="sc-ls-country-filter">
    <label for="<?php echo esc_attr( $field_id ); ?>"><?php esc_html_e( 'Country', 'space-core' ); ?></label>
    <select id="<?php echo esc_attr( $field_id ); ?>" class="sc-ls-country-select" data-target="<?php echo esc_attr( $target ); ?>">
        <?php echo $this->admin_country_options_html( $countries, $selected_country ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </select>
</div>
