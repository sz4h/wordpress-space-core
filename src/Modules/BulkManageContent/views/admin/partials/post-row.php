<?php

use Space\Core\Modules\BulkManageContent\MultilingualHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Variables: $post (WP_Post), $fields (array), $is_multilingual (bool), $post_type (string)
 */

$status_classes = [
        'publish' => '',
        'draft'   => 'sc-bmc-status-draft',
        'pending' => 'sc-bmc-status-pending',
        'private' => 'sc-bmc-status-private',
        'future'  => 'sc-bmc-status-future',
];
$status_class   = $status_classes[ $post->post_status ] ?? '';
$title_ar       = $is_multilingual
        ? MultilingualHelper::get_post_title_in_lang( $post->ID, 'ar', $post_type )
        : '';
?>
<tr class="sc-bmc-row" data-id="<?php echo esc_attr( (string) $post->ID ); ?>" data-type="post_type">
    <td class="sc-bmc-col-id"><?php echo esc_html( (string) $post->ID ); ?></td>
    <?php if ( $has_thumbnail ) : ?>
        <td class="sc-bmc-col-thumb">
            <img class="sc-bmc-thumb"
                 src="<?php echo esc_url( get_the_post_thumbnail_url( $post->ID, 'thumbnail' ) ); ?>"
                 data-medium="<?php echo esc_url( get_the_post_thumbnail_url( $post->ID, 'medium' ) ?: get_the_post_thumbnail_url( $post->ID, 'full' ) ); ?>"
                 alt="<?php echo esc_attr( get_the_title( $post->ID ) ); ?>"/>
        </td>
    <?php endif; ?>
    <td>
        <input type="text"
               class="sc-bmc-inline-field"
               data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>"
               data-field-key="post_title"
               value="<?php echo esc_attr( $post->post_title ); ?>"
               aria-label="<?php esc_attr_e( 'Title EN', 'space-core' ); ?>"/>
        <span class="sc-bmc-field-status"></span>
    </td>
    <?php if ( $is_multilingual ) : ?>
        <td>
            <input type="text"
                   class="sc-bmc-inline-field"
                   data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>"
                   data-field-key="post_title_ar"
                   value="<?php echo esc_attr( $title_ar ); ?>"
                   dir="rtl"
                   aria-label="<?php esc_attr_e( 'Title AR', 'space-core' ); ?>"/>
            <span class="sc-bmc-field-status"></span>
        </td>
    <?php endif; ?>
    <td>
        <select class="sc-bmc-inline-field"
                data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>"
                data-field-key="post_status">
            <option value="publish" <?php selected( $post->post_status, 'publish' ); ?>><?php esc_html_e( 'Published', 'space-core' ); ?></option>
            <option value="draft" <?php selected( $post->post_status, 'draft' ); ?>><?php esc_html_e( 'Draft', 'space-core' ); ?></option>
            <option value="pending" <?php selected( $post->post_status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'space-core' ); ?></option>
            <option value="private" <?php selected( $post->post_status, 'private' ); ?>><?php esc_html_e( 'Private', 'space-core' ); ?></option>
        </select>
        <span class="sc-bmc-field-status"></span>
    </td>
    <?php foreach ( $fields as $field ) :
        $meta_val = get_post_meta( $post->ID, $field['key'], true );
        ?>
        <td>
            <?php
            echo $this->render_bulk_field_input(
                    $field,
                    $meta_val,
                    [
                            'class'      => 'sc-bmc-inline-field',
                            'aria_label' => $field['label_en'] ?? $field['key'],
                            'attrs'      => [
                                    'data-post-id'   => (string) $post->ID,
                                    'data-field-key' => $field['key'],
                            ],
                    ]
            );
            ?>
            <span class="sc-bmc-field-status"></span>
        </td>
    <?php endforeach; ?>
    <td class="sc-bmc-col-date">
        <span title="<?php echo esc_attr( $post->post_date ); ?>">
            <?php echo esc_html( get_the_date( 'Y/m/d', $post->ID ) ); ?>
        </span>
    </td>
</tr>
