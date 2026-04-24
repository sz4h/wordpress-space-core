<?php

defined( 'ABSPATH' ) || exit;

$name = 'sc_cf[' . $key . ']';
$id   = 'sc_cf_' . $key;

switch ( $type ) {
    case 'textarea':
        printf( '<textarea id="%s" name="%s" rows="4" class="large-text">%s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_textarea( $value ) );
        break;
    case 'select':
        $choices = $field['choices'] ?? [];
        echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
        echo '<option value=""></option>';
        foreach ( $choices as $opt ) {
            $opt = self::normalize_choice( $opt );
            if ( [] === $opt ) {
                continue;
            }

            $option_value = (string) ( $opt['value'] ?? '' );
            $option_label = self::get_choice_label( $opt );

            echo '<option value="' . esc_attr( $option_value ) . '" ' . selected( $value, $option_value, false ) . '>' . esc_html( $option_label ) . '</option>';
        }
        echo '</select>';
        break;
    case 'checkbox':
        printf( '<input type="checkbox" id="%s" name="%s" value="1" %s />', esc_attr( $id ), esc_attr( $name ), checked( $value, '1', false ) );
        break;
    case 'date':
        printf( '<input type="date" id="%s" name="%s" value="%s" />', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ) );
        break;
    case 'number':
        printf( '<input type="number" id="%s" name="%s" value="%s" class="small-text" />', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ) );
        break;
    case 'url':
        printf( '<input type="url" id="%s" name="%s" value="%s" class="regular-text" />', esc_attr( $id ), esc_attr( $name ), esc_url( (string) $value ) );
        break;
    case 'image':
        $img_url = $value ? wp_get_attachment_image_url( (int) $value, 'thumbnail' ) : '';
        printf(
            '<div class="sc-image-field">
                <input type="hidden" id="%1$s" name="%2$s" value="%3$s" />
                %4$s
                <button type="button" class="button sc-upload-image" data-target="%1$s">%5$s</button>
                <button type="button" class="button sc-remove-image" data-target="%1$s" %6$s>%7$s</button>
            </div>',
            esc_attr( $id ),
            esc_attr( $name ),
            esc_attr( (string) $value ),
            $img_url ? '<img src="' . esc_url( $img_url ) . '" style="max-width:100px;display:block;margin-bottom:6px;" />' : '',
            esc_html__( 'Select Image', 'space-core' ),
            $value ? '' : 'style="display:none"',
            esc_html__( 'Remove', 'space-core' )
        );
        break;
    default:
        printf( '<input type="text" id="%s" name="%s" value="%s" class="regular-text" />', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ) );
}
