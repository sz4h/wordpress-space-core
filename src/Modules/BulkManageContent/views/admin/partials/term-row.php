<?php

defined( 'ABSPATH' ) || exit;

/**
 * Variables: $term (WP_Term), $fields (array), $is_multilingual (bool), $taxonomy (string)
 */

$name_ar = $is_multilingual
    ? \Space\Core\Modules\BulkManageContent\MultilingualHelper::get_term_name_in_lang( $term->term_id, 'ar', $taxonomy )
    : '';
?>
<tr class="sc-bmc-row" data-id="<?php echo esc_attr( (string) $term->term_id ); ?>" data-type="taxonomy" data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>">
    <td class="sc-bmc-col-id"><?php echo esc_html( (string) $term->term_id ); ?></td>
    <td>
        <input type="text"
               class="sc-bmc-inline-field"
               data-term-id="<?php echo esc_attr( (string) $term->term_id ); ?>"
               data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>"
               data-field-key="name"
               value="<?php echo esc_attr( $term->name ); ?>"
               aria-label="<?php esc_attr_e( 'Name EN', 'space-core' ); ?>" />
        <span class="sc-bmc-field-status"></span>
    </td>
    <?php if ( $is_multilingual ) : ?>
    <td>
        <input type="text"
               class="sc-bmc-inline-field"
               data-term-id="<?php echo esc_attr( (string) $term->term_id ); ?>"
               data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>"
               data-field-key="name_ar"
               value="<?php echo esc_attr( $name_ar ); ?>"
               dir="rtl"
               aria-label="<?php esc_attr_e( 'Name AR', 'space-core' ); ?>" />
        <span class="sc-bmc-field-status"></span>
    </td>
    <?php endif; ?>
    <td>
        <input type="text"
               class="sc-bmc-inline-field"
               data-term-id="<?php echo esc_attr( (string) $term->term_id ); ?>"
               data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>"
               data-field-key="slug"
               value="<?php echo esc_attr( $term->slug ); ?>"
               aria-label="<?php esc_attr_e( 'Slug', 'space-core' ); ?>" />
        <span class="sc-bmc-field-status"></span>
    </td>
    <td class="sc-bmc-col-count"><?php echo esc_html( (string) $term->count ); ?></td>
    <?php foreach ( $fields as $field ) :
        $meta_val = get_term_meta( $term->term_id, $field['key'], true );
    ?>
    <td>
        <input type="text"
               class="sc-bmc-inline-field"
               data-term-id="<?php echo esc_attr( (string) $term->term_id ); ?>"
               data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>"
               data-field-key="<?php echo esc_attr( $field['key'] ); ?>"
               value="<?php echo esc_attr( (string) $meta_val ); ?>"
               aria-label="<?php echo esc_attr( $field['label_en'] ); ?>" />
        <span class="sc-bmc-field-status"></span>
    </td>
    <?php endforeach; ?>
</tr>
