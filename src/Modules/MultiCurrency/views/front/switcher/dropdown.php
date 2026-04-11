<?php

defined( 'ABSPATH' ) || exit;

?>
<div class="sc-currency-switcher">
	<select class="sc-currency-select"
		data-ajax="<?php echo esc_url( $ajax_url ); ?>"
		data-nonce="<?php echo esc_attr( $nonce ); ?>">
		<?php foreach ( $options as $option ) : ?>
			<option value="<?php echo esc_attr( $option['value'] ); ?>" <?php selected( ! empty( $option['selected'] ) ); ?>>
				<?php echo esc_html( $option['label'] ); ?>
			</option>
		<?php endforeach; ?>
	</select>
</div>
