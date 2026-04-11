<?php defined( 'ABSPATH' ) || exit; ?>
<div class="sc-ls-name-fields">
    <label><span><?php esc_html_e( 'EN', 'space-core' ); ?></span><input type="text" class="sc-field" data-key="name_en" value="<?php echo esc_attr( $name['en'] ?? '' ); ?>"></label>
    <label><span><?php esc_html_e( 'AR', 'space-core' ); ?></span><input type="text" class="sc-field" data-key="name_ar" value="<?php echo esc_attr( $name['ar'] ?? '' ); ?>" dir="rtl"></label>
</div>
