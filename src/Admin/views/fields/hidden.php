<?php

defined( 'ABSPATH' ) || exit;

?>
<input type="hidden"
       name="<?php echo esc_attr( $option_name . '[' . $field_name . ']' ); ?>"
       value="<?php echo esc_attr( (string) $value ); ?>"<?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
