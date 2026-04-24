<?php

defined( 'ABSPATH' ) || exit;

?>
<label for="<?php echo esc_attr( $option_name ) . '-' . esc_attr( $field_name ); ?>">
    <input type="checkbox"
           id="<?php echo esc_attr( $option_name ) . '-' . esc_attr( $field_name ); ?>"
           name="<?php echo esc_attr( $option_name . '[' . $field_name . ']' ); ?>"
           value="<?php echo esc_attr( $value ); ?>"
            <?php checked( $value, $current ); ?><?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
    <?php echo esc_html( (string) $label ); ?>
</label>
