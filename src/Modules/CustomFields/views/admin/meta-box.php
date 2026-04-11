<?php

defined( 'ABSPATH' ) || exit;

wp_nonce_field( 'sc_custom_fields_save', 'sc_cf_nonce' );
?>
<table class="form-table sc-cf-table"><tbody>
<?php foreach ( $fields as $field ) :
    $key   = sanitize_key( $field['key'] ?? '' );
    $label = sanitize_text_field( $field['label'] ?? $key );
    $type  = sanitize_key( $field['type'] ?? 'text' );
    $value = get_post_meta( $post->ID, '_sc_' . $key, true );
    ?>
    <tr>
        <th scope="row"><label for="sc_cf_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
        <td><?php $this->render_field( $key, $type, $value, $field ); ?></td>
    </tr>
<?php endforeach; ?>
</tbody></table>
