<?php

namespace Space\Core\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Helper for rendering common settings fields.
 */
class SettingsAPI {

    /**
     * Render a text input field.
     */
    public static function text( string $option_group, string $option_name, string $field_name, mixed $value, string $placeholder = '' ): void {
        ?>
        <input type="text"
               name="<?php echo esc_attr( $option_name . '[' . $field_name . ']' ); ?>"
               value="<?php echo esc_attr( $value ); ?>"
               placeholder="<?php echo esc_attr( $placeholder ); ?>"
               class="regular-text" />
        <?php
    }

    /**
     * Render a textarea field.
     */
    public static function textarea( string $option_name, string $field_name, mixed $value, int $rows = 6 ): void {
        ?>
        <textarea name="<?php echo esc_attr( $option_name . '[' . $field_name . ']' ); ?>"
                  rows="<?php echo absint( $rows ); ?>"
                  class="large-text code"><?php echo esc_textarea( $value ); ?></textarea>
        <?php
    }

    /**
     * Render a select dropdown.
     */
    public static function select( string $option_name, string $field_name, mixed $value, array $options ): void {
        ?>
        <select name="<?php echo esc_attr( $option_name . '[' . $field_name . ']' ); ?>">
            <?php foreach ( $options as $opt_value => $opt_label ) : ?>
                <option value="<?php echo esc_attr( $opt_value ); ?>" <?php selected( $value, $opt_value ); ?>>
                    <?php echo esc_html( $opt_label ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Render a checkbox toggle.
     */
    public static function checkbox( string $option_name, string $field_name, mixed $value, string $label = '' ): void {
        ?>
        <label>
            <input type="checkbox"
                   name="<?php echo esc_attr( $option_name . '[' . $field_name . ']' ); ?>"
                   value="1"
                   <?php checked( (bool) $value ); ?> />
            <?php echo esc_html( $label ); ?>
        </label>
        <?php
    }

    /**
     * Render a color picker input.
     */
    public static function color( string $option_name, string $field_name, mixed $value ): void {
        ?>
        <input type="text"
               name="<?php echo esc_attr( $option_name . '[' . $field_name . ']' ); ?>"
               value="<?php echo esc_attr( $value ); ?>"
               class="sc-color-picker" />
        <?php
    }

    /**
     * Render a number input.
     */
    public static function number( string $option_name, string $field_name, mixed $value, int $min = 0, int $max = 9999 ): void {
        ?>
        <input type="number"
               name="<?php echo esc_attr( $option_name . '[' . $field_name . ']' ); ?>"
               value="<?php echo absint( $value ); ?>"
               min="<?php echo absint( $min ); ?>"
               max="<?php echo absint( $max ); ?>"
               class="small-text" />
        <?php
    }

    /**
     * Wrap a settings form with the standard WP form + submit button.
     */
    public static function open_form( string $option_group ): void {
        echo '<form method="post" action="options.php">';
        settings_fields( $option_group );
    }

    public static function close_form( string $submit_label = '' ): void {
        submit_button( $submit_label ?: __( 'Save Settings', 'space-core' ) );
        echo '</form>';
    }
}
