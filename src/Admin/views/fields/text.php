<?php

defined( 'ABSPATH' ) || exit;

?>
<input type="text"
       name="<?php echo esc_attr( $option_name . '[' . $field_name . ']' ); ?>"
       value="<?php echo esc_attr( (string) $value ); ?>"
       placeholder="<?php echo esc_attr( $placeholder ); ?>"
       class="regular-text"<?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
