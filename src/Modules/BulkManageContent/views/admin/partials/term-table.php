<?php

defined( 'ABSPATH' ) || exit;

/**
 * Variables: $terms (WP_Term[]), $fields (array), $is_multilingual (bool),
 *            $taxonomy (string), $pagination (array), $per_page (int)
 */

$total_pages = (int) ( $pagination['total_pages'] ?? 1 );
$total_items = (int) ( $pagination['total_items'] ?? 0 );
$current     = (int) ( $pagination['current'] ?? 1 );
$start       = $total_items > 0 ? ( ( $current - 1 ) * $per_page + 1 ) : 0;
$end         = min( $current * $per_page, $total_items );
?>
<!-- Controls -->
<div class="sc-bmc-table-controls">
    <label for="sc-bmc-pp-<?php echo esc_attr( $taxonomy ); ?>"><?php esc_html_e( 'Per page:', 'space-core' ); ?></label>
    <select class="sc-bmc-per-page" id="sc-bmc-pp-<?php echo esc_attr( $taxonomy ); ?>"
            data-slug="<?php echo esc_attr( $taxonomy ); ?>" data-table-type="taxonomy">
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
                data-slug="<?php echo esc_attr( $taxonomy ); ?>"
                data-table-type="taxonomy"
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
                data-slug="<?php echo esc_attr( $taxonomy ); ?>"
                data-table-type="taxonomy"
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
                <th><?php esc_html_e( 'Name EN', 'space-core' ); ?></th>
                <?php if ( $is_multilingual ) : ?>
                <th><?php esc_html_e( 'Name AR', 'space-core' ); ?></th>
                <?php endif; ?>
                <th><?php esc_html_e( 'Slug', 'space-core' ); ?></th>
                <th class="sc-bmc-col-count"><?php esc_html_e( 'Count', 'space-core' ); ?></th>
                <?php foreach ( $fields as $field ) : ?>
                <th><?php echo esc_html( $field['label_en'] ); ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $terms ) ) : ?>
            <tr>
                <td colspan="<?php echo esc_attr( (string) ( 4 + count( $fields ) + ( $is_multilingual ? 1 : 0 ) ) ); ?>" class="sc-bmc-empty">
                    <?php esc_html_e( 'No items found.', 'space-core' ); ?>
                </td>
            </tr>
            <?php else : ?>
                <?php foreach ( $terms as $term ) :
                    echo $this->view( 'admin/partials/term-row', compact( 'term', 'fields', 'is_multilingual', 'taxonomy' ) );
                endforeach; ?>
            <?php endif; ?>

            <!-- Add New row -->
            <tr class="sc-bmc-new-row" style="display:none;">
                <td class="sc-bmc-col-id">—</td>
                <td>
                    <input type="text" class="sc-bmc-new-field" name="name_en"
                           placeholder="<?php esc_attr_e( 'Name EN', 'space-core' ); ?>" />
                </td>
                <?php if ( $is_multilingual ) : ?>
                <td>
                    <input type="text" class="sc-bmc-new-field" name="name_ar"
                           dir="rtl" placeholder="<?php esc_attr_e( 'Name AR', 'space-core' ); ?>" />
                </td>
                <?php endif; ?>
                <td colspan="<?php echo esc_attr( (string) ( 1 + count( $fields ) + 1 ) ); ?>">
                    <button type="button" class="button sc-bmc-save-new"
                            data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>">
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
            data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>">
        + <?php esc_html_e( 'Add New', 'space-core' ); ?>
    </button>
</div>
