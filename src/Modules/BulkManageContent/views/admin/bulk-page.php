<?php

defined( 'ABSPATH' ) || exit;

/**
 * Variables: $nonce, $enabled_pts (WP_Post_Type[]), $enabled_taxs (WP_Taxonomy[]), $is_multilingual (bool)
 */
?>
<div class="wrap sc-bmc-wrap" id="sc-bmc-app">
    <h1 class="sc-bmc-page-title">
        <span class="dashicons dashicons-editor-table"></span>
        <?php esc_html_e( 'Bulk Management', 'space-core' ); ?>
    </h1>

    <?php if ( empty( $enabled_pts ) && empty( $enabled_taxs ) ) : ?>
        <div class="notice notice-warning inline" style="margin-top:16px;">
            <p>
                <?php printf(
                    /* translators: %s: link to settings page */
                    esc_html__( 'No post types or taxonomies are enabled. %s', 'space-core' ),
                    '<a href="' . esc_url( admin_url( 'admin.php?page=sc-bulk-manage-content' ) ) . '">' .
                    esc_html__( 'Configure settings', 'space-core' ) . '</a>'
                ); ?>
            </p>
        </div>
    <?php else : ?>

    <!-- Top-level tab bar -->
    <nav class="sc-bmc-top-tabs" role="tablist">
        <?php if ( ! empty( $enabled_pts ) ) : ?>
        <button class="sc-bmc-tab-btn <?php echo empty( $enabled_taxs ) ? 'active' : 'active'; ?>"
                data-panel="post-types" role="tab" aria-selected="true">
            <?php esc_html_e( 'Post Types', 'space-core' ); ?>
        </button>
        <?php endif; ?>
        <?php if ( ! empty( $enabled_taxs ) ) : ?>
        <button class="sc-bmc-tab-btn <?php echo empty( $enabled_pts ) ? 'active' : ''; ?>"
                data-panel="taxonomies" role="tab" aria-selected="<?php echo empty( $enabled_pts ) ? 'true' : 'false'; ?>">
            <?php esc_html_e( 'Taxonomies', 'space-core' ); ?>
        </button>
        <?php endif; ?>
    </nav>

    <!-- Post Types panel -->
    <?php if ( ! empty( $enabled_pts ) ) : ?>
    <div id="sc-bmc-panel-post-types" class="sc-bmc-panel active" role="tabpanel">
        <nav class="sc-bmc-sub-tabs">
            <?php $first = true; foreach ( $enabled_pts as $slug => $pt ) : ?>
            <button class="sc-bmc-sub-tab-btn <?php echo $first ? 'active' : ''; ?>"
                    data-panel-type="post_type"
                    data-slug="<?php echo esc_attr( $slug ); ?>"
                    role="tab">
                <?php echo esc_html( $pt->labels->singular_name ); ?>
            </button>
            <?php $first = false; endforeach; ?>
        </nav>

        <div class="sc-bmc-sub-panels">
            <?php foreach ( $enabled_pts as $slug => $pt ) : ?>
            <div class="sc-bmc-sub-panel"
                 id="sc-bmc-pt-<?php echo esc_attr( $slug ); ?>"
                 data-type="post_type"
                 data-slug="<?php echo esc_attr( $slug ); ?>"
                 data-loaded="0"
                 data-current-page="1"
                 data-per-page="25">
                <div class="sc-bmc-loading">
                    <span class="spinner is-active"></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Taxonomies panel -->
    <?php if ( ! empty( $enabled_taxs ) ) : ?>
    <div id="sc-bmc-panel-taxonomies" class="sc-bmc-panel <?php echo empty( $enabled_pts ) ? 'active' : ''; ?>" role="tabpanel">
        <nav class="sc-bmc-sub-tabs">
            <?php $first = true; foreach ( $enabled_taxs as $slug => $tax ) : ?>
            <button class="sc-bmc-sub-tab-btn <?php echo $first ? 'active' : ''; ?>"
                    data-panel-type="taxonomy"
                    data-slug="<?php echo esc_attr( $slug ); ?>"
                    role="tab">
                <?php echo esc_html( $tax->labels->singular_name ); ?>
            </button>
            <?php $first = false; endforeach; ?>
        </nav>

        <div class="sc-bmc-sub-panels">
            <?php foreach ( $enabled_taxs as $slug => $tax ) : ?>
            <div class="sc-bmc-sub-panel"
                 id="sc-bmc-tax-<?php echo esc_attr( $slug ); ?>"
                 data-type="taxonomy"
                 data-slug="<?php echo esc_attr( $slug ); ?>"
                 data-loaded="0"
                 data-current-page="1"
                 data-per-page="25">
                <div class="sc-bmc-loading">
                    <span class="spinner is-active"></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; // end has enabled items ?>
</div>
