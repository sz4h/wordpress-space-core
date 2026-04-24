<?php

defined( 'ABSPATH' ) || exit;


?>
<div class='sc-ab-pill-wrap'>
    <?php foreach ( $options as $key => $name ) :
        $is_checked = in_array( $key, $value, true );
        ?>
        <label class="sc-ab-pill<?php echo $is_checked ? ' is-checked' : ''; ?>">
            <input type="checkbox"
                   name="<?php echo esc_attr( $option_name . '[' . $field_name . '][]' ); ?>"
                   value="<?php echo esc_attr( $key ); ?>"
                    <?php checked( $is_checked ); ?>>
            <svg class="sc-ab-check" viewBox="0 0 12 12" xmlns="http://www.w3.org/2000/svg"
                 aria-hidden="true">
                <path d="M1 6l3.5 3.5L11 2" stroke="currentColor" stroke-width="2" fill="none"
                      stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <?php echo $name; ?>
        </label>
    <?php endforeach; ?>
</div>