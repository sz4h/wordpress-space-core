<?php

defined( 'ABSPATH' ) || exit;

?>
<input type="text"
       name="<?php echo esc_attr( $option_name . '[' . $field_name . ']' ); ?>"
       value="<?php echo esc_attr( (string) $value ); ?>"
       class="sc-color-picker"<?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
