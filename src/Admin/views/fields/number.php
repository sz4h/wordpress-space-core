<?php

defined( 'ABSPATH' ) || exit;

?>
<input type="number"
       name="<?php echo esc_attr( $option_name . '[' . $field_name . ']' ); ?>"
       value="<?php echo esc_attr( (string) $value ); ?>"
       min="<?php echo esc_attr( (string) $min ); ?>"
       max="<?php echo esc_attr( (string) $max ); ?>"
       class="small-text"<?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
