<?php

defined( 'ABSPATH' ) || exit;

$selected_values = array_map( 'strval', $value );

?>
<select name="<?php echo esc_attr( $option_name . '[' . $field_name . '][]' ); ?>" multiple size="<?php echo absint( $size ); ?>"<?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
    <?php foreach ( $options as $opt_value => $opt_label ) : ?>
        <option value="<?php echo esc_attr( (string) $opt_value ); ?>" <?php selected( in_array( (string) $opt_value, $selected_values, true ) ); ?>>
            <?php echo esc_html( (string) $opt_label ); ?>
        </option>
    <?php endforeach; ?>
</select>
