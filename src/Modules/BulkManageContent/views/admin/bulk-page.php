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

    <?php
    $import_status = isset( $_GET['sc_import'] ) ? sanitize_key( wp_unslash( $_GET['sc_import'] ) ) : '';
    if ( $import_status ) :
        $updated = isset( $_GET['sc_import_updated'] ) ? absint( $_GET['sc_import_updated'] ) : 0;
        $skipped   = isset( $_GET['sc_import_skipped'] ) ? absint( $_GET['sc_import_skipped'] ) : 0;
        $is_ok     = ( 'ok' === $import_status );
        $messages  = [
                'ok'          => sprintf( esc_html__( 'Import complete: %1$d updated, %2$d skipped.', 'space-core' ), $updated, $skipped ),
                'no_file'     => esc_html__( 'No CSV file uploaded.', 'space-core' ),
                'open_failed' => esc_html__( 'Could not open uploaded file.', 'space-core' ),
                'empty'       => esc_html__( 'Uploaded file is empty.', 'space-core' ),
                'bad_header'  => esc_html__( 'CSV must include "id" and "title_en" columns.', 'space-core' ),
        ];
        ?>
        <div class="notice <?php echo $is_ok ? 'notice-success' : 'notice-error'; ?>" style="margin:10px 0;">
            <p><?php echo $messages[ $import_status ] ?? esc_html__( 'Unknown import result.', 'space-core' ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( post_type_exists( 'product' ) ) : ?>
        <div class="sc-bmc-tools"
             style="margin:14px 0 18px;padding:12px 14px;background:#fff8e1;border-left:4px solid #ffb300;">
            <h3 style="margin:0 0 6px;"><?php esc_html_e( 'Temporary Tools — Product Title Sync', 'space-core' ); ?></h3>
            <p class="description" style="margin:0 0 10px;">
                <?php esc_html_e( 'Export products that have a price set AND a title starting with "#". Import updates the English title by ID using the same CSV columns: id, title_en, title_ar (title_ar is exported but not used on import).', 'space-core' ); ?>
            </p>
            <div style="display:flex;gap:18px;flex-wrap:wrap;align-items:flex-start;">
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'sc_bmc_export_products' ); ?>
                    <input type="hidden" name="action" value="sc_bmc_export_products"/>
                    <button type="submit" class="button button-primary">
                        <span class="dashicons dashicons-download" style="vertical-align:middle;"></span>
                        <?php esc_html_e( 'Export filtered products (CSV)', 'space-core' ); ?>
                    </button>
                </form>
                <form method="post" enctype="multipart/form-data"
                      action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                      style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                    <?php wp_nonce_field( 'sc_bmc_import_products' ); ?>
                    <input type="hidden" name="action" value="sc_bmc_import_products"/>
                    <input type="file" name="csv" accept=".csv,text/csv" required/>
                    <button type="submit" class="button">
                        <span class="dashicons dashicons-upload" style="vertical-align:middle;"></span>
                        <?php esc_html_e( 'Import CSV', 'space-core' ); ?>
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>

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
                        data-panel="taxonomies" role="tab"
                        aria-selected="<?php echo empty( $enabled_pts ) ? 'true' : 'false'; ?>">
                    <?php esc_html_e( 'Taxonomies', 'space-core' ); ?>
                </button>
            <?php endif; ?>
        </nav>

        <!-- Post Types panel -->
        <?php if ( ! empty( $enabled_pts ) ) : ?>
            <div id="sc-bmc-panel-post-types" class="sc-bmc-panel active" role="tabpanel">
                <nav class="sc-bmc-sub-tabs">
                    <?php $first = true;
                    foreach ( $enabled_pts as $slug => $pt ) : ?>
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
            <div id="sc-bmc-panel-taxonomies" class="sc-bmc-panel <?php echo empty( $enabled_pts ) ? 'active' : ''; ?>"
                 role="tabpanel">
                <nav class="sc-bmc-sub-tabs">
                    <?php $first = true;
                    foreach ( $enabled_taxs as $slug => $tax ) : ?>
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
