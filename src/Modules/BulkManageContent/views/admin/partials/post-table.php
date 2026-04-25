<?php

defined( 'ABSPATH' ) || exit;

/**
 * Variables: $posts (WP_Post[]), $fields (array), $is_multilingual (bool),
 *            $post_type (string), $pagination (array), $per_page (int)
 */

$total_pages = (int) ( $pagination['total_pages'] ?? 1 );
$total_items = (int) ( $pagination['total_items'] ?? 0 );
$current     = (int) ( $pagination['current'] ?? 1 );
$start       = $total_items > 0 ? ( ( $current - 1 ) * $per_page + 1 ) : 0;
$end         = min( $current * $per_page, $total_items );
?>
<!-- Controls -->
<div class="sc-bmc-table-controls">
    <label for="sc-bmc-pp-<?php echo esc_attr( $post_type ); ?>"><?php esc_html_e( 'Per page:', 'space-core' ); ?></label>
    <select class="sc-bmc-per-page" id="sc-bmc-pp-<?php echo esc_attr( $post_type ); ?>"
            data-slug="<?php echo esc_attr( $post_type ); ?>" data-table-type="post_type">
        <?php foreach ( [ 10, 25, 50, 100 ] as $n ) : ?>
            <option value="<?php echo esc_attr( (string) $n ); ?>" <?php selected( $per_page, $n ); ?>><?php echo esc_html( (string) $n ); ?></option>
        <?php endforeach; ?>
    </select>
    <span class="sc-bmc-count">
        <?php
        if ( $total_items > 0 ) {
            printf(
            /* translators: 1: start, 2: end, 3: total */
                    esc_html__( '%1$s–%2$s of %3$s', 'space-core' ),
                    esc_html( (string) $start ),
                    esc_html( (string) $end ),
                    esc_html( (string) $total_items )
            );
        } else {
            esc_html_e( '0 items', 'space-core' );
        }
        ?>
    </span>
    <div class="sc-bmc-pagination">
        <button class="button sc-bmc-prev"
                data-slug="<?php echo esc_attr( $post_type ); ?>"
                data-table-type="post_type"
                data-page="<?php echo esc_attr( (string) max( 1, $current - 1 ) ); ?>"
                <?php disabled( $current <= 1 ); ?>>
            &laquo; <?php esc_html_e( 'Prev', 'space-core' ); ?>
        </button>
        <span class="sc-bmc-page-info">
            <?php printf(
            /* translators: 1: current, 2: total */
                    esc_html__( '%1$s / %2$s', 'space-core' ),
                    esc_html( (string) $current ),
                    esc_html( (string) max( 1, $total_pages ) )
            ); ?>
        </span>
        <button class="button sc-bmc-next"
                data-slug="<?php echo esc_attr( $post_type ); ?>"
                data-table-type="post_type"
                data-page="<?php echo esc_attr( (string) min( $total_pages, $current + 1 ) ); ?>"
                <?php disabled( $current >= $total_pages ); ?>>
            <?php esc_html_e( 'Next', 'space-core' ); ?> &raquo;
        </button>
    </div>
</div>

<!-- Table -->
<div class="sc-bmc-table-scroll">
    <table class="sc-bmc-table">
        <thead>
        <tr>
            <th class="sc-bmc-col-id"><?php esc_html_e( 'ID', 'space-core' ); ?></th>
            <?php if ( $has_thumbnail ) : ?>
                <th class="sc-bmc-col-thumb"><?php esc_html_e( 'Thumbnail', 'space-core' ); ?></th>
            <?php endif; ?>
            <th><?php esc_html_e( 'Title EN', 'space-core' ); ?></th>
            <?php if ( $is_multilingual ) : ?>
                <th><?php esc_html_e( 'Title AR', 'space-core' ); ?></th>
            <?php endif; ?>
            <th><?php esc_html_e( 'Status', 'space-core' ); ?></th>
            <?php foreach ( $fields as $field ) : ?>
                <th><?php echo esc_html( $field['label_en'] ); ?></th>
            <?php endforeach; ?>
            <th class="sc-bmc-col-date"><?php esc_html_e( 'Date', 'space-core' ); ?></th>
        </tr>
        </thead>
        <tbody>
        <?php if ( empty( $posts ) ) : ?>
            <tr>
                <td colspan="<?php echo esc_attr( (string) ( 4 + count( $fields ) + ( $is_multilingual ? 1 : 0 ) ) ); ?>"
                    class="sc-bmc-empty">
                    <?php esc_html_e( 'No items found.', 'space-core' ); ?>
                </td>
            </tr>
        <?php else : ?>
            <?php foreach ( $posts as $post ) :
                echo $this->view( 'admin/partials/post-row', compact( 'post', 'has_thumbnail', 'fields', 'is_multilingual', 'post_type' ) );
            endforeach; ?>
        <?php endif; ?>

        <!-- Add New row -->
        <tr class="sc-bmc-new-row" style="display:none;">
            <td class="sc-bmc-col-id">—</td>
            <td>
                <input type="text" class="sc-bmc-new-field" name="title_en"
                       placeholder="<?php esc_attr_e( 'Title EN', 'space-core' ); ?>"/>
            </td>
            <?php if ( $is_multilingual ) : ?>
                <td>
                    <input type="text" class="sc-bmc-new-field" name="title_ar"
                           dir="rtl" placeholder="<?php esc_attr_e( 'Title AR', 'space-core' ); ?>"/>
                </td>
            <?php endif; ?>
            <td>
                <select class="sc-bmc-new-field" name="status">
                    <option value="draft"><?php esc_html_e( 'Draft', 'space-core' ); ?></option>
                    <option value="publish"><?php esc_html_e( 'Published', 'space-core' ); ?></option>
                    <option value="pending"><?php esc_html_e( 'Pending', 'space-core' ); ?></option>
                </select>
            </td>
            <?php foreach ( $fields as $field ) : ?>
                <td>
                    <?php
                    echo $this->render_bulk_field_input(
                            $field,
                            '',
                            [
                                    'name'       => $field['key'],
                                    'class'      => 'sc-bmc-new-field',
                                    'aria_label' => $field['label_en'] ?? $field['key'],
                            ]
                    );
                    ?>
                </td>
            <?php endforeach; ?>
            <td>
                <button type="button" class="button sc-bmc-save-new"
                        data-post-type="<?php echo esc_attr( $post_type ); ?>">
                    <?php esc_html_e( 'Save New', 'space-core' ); ?>
                </button>
                <button type="button" class="button sc-bmc-cancel-new">
                    <?php esc_html_e( 'Cancel', 'space-core' ); ?>
                </button>
            </td>
        </tr>
        </tbody>
    </table>
</div>

<!-- Add New button -->
<div style="margin-top:12px;">
    <button type="button" class="button sc-bmc-show-new-row"
            data-post-type="<?php echo esc_attr( $post_type ); ?>">
        + <?php esc_html_e( 'Add New', 'space-core' ); ?>
    </button>
</div>
