<?php

defined( 'ABSPATH' ) || exit;

?>
<label>
    <input type="checkbox"
           name="<?php echo esc_attr( $option_name . '[' . $field_name . ']' ); ?>"
           value="1"
           <?php checked( (bool) $value ); ?><?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
    <?php echo esc_html( (string) $label ); ?>
</label>
