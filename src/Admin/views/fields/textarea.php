<?php

defined( 'ABSPATH' ) || exit;

?>
<textarea name="<?php echo esc_attr( $option_name . '[' . $field_name . ']' ); ?>"
          rows="<?php echo absint( $rows ); ?>"
          class="large-text code"<?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_textarea( (string) $value ); ?></textarea>
