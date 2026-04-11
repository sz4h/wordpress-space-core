<?php

defined( 'ABSPATH' ) || exit;

?>
<select name="<?php echo esc_attr( $option_name . '[' . $field_name . ']' ); ?>"<?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
    <?php foreach ( $options as $opt_value => $opt_label ) : ?>
        <option value="<?php echo esc_attr( (string) $opt_value ); ?>" <?php selected( $value, $opt_value ); ?>>
            <?php echo esc_html( (string) $opt_label ); ?>
        </option>
    <?php endforeach; ?>
</select>
