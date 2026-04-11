<?php

defined( 'ABSPATH' ) || exit;

$id           = (string) $item['id'];
$type         = (string) ( $item['type'] ?? 'menu' );
$slug         = (string) ( $item['slug'] ?? '' );
$label        = (string) ( $item['label'] ?? '' );
$mode         = (string) ( $item['visibility_mode'] ?? 'show' );
$has_children = ! empty( $item['subs'] );
$parent       = (string) ( $item['parent'] ?? '' );
?>
<li class="sc-am-item sc-am-item-<?php echo esc_attr( $type ); ?>" data-id="<?php echo esc_attr( $id ); ?>" data-type="<?php echo esc_attr( $type ); ?>" data-parent="<?php echo esc_attr( $parent ); ?>" data-slug="<?php echo esc_attr( $slug ); ?>">
    <div class="sc-am-item-main">
        <span class="sc-am-drag dashicons dashicons-menu" aria-hidden="true"></span>
        <div class="sc-am-title">
            <input type="text" class="sc-am-label<?php echo 'separator' === $type ? ' sc-am-label-readonly' : ''; ?>" value="<?php echo esc_attr( $label ); ?>" <?php echo 'separator' === $type ? 'readonly="readonly"' : ''; ?>>
            <?php if ( $has_children ) : ?>
                <span class="sc-am-meta">[ <?php esc_html_e( 'Submenu', 'space-core' ); ?> ]</span>
            <?php elseif ( 'promoted_submenu' === $type ) : ?>
                <span class="sc-am-meta sc-am-meta-moved">[ <?php esc_html_e( 'Moved out', 'space-core' ); ?> ]</span>
            <?php elseif ( 'custom' === $type ) : ?>
                <span class="sc-am-meta">[ <?php esc_html_e( 'Custom URL', 'space-core' ); ?> ]</span>
            <?php endif; ?>
        </div>
        <label class="sc-am-switch" title="<?php esc_attr_e( 'Hide item', 'space-core' ); ?>">
            <input type="checkbox" class="sc-am-hide-toggle" <?php checked( 'show' !== $mode ); ?>>
            <span class="sc-am-switch-slider"></span>
        </label>
        <button type="button" class="button-link sc-am-expand" aria-expanded="false">
            <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
            <span class="screen-reader-text"><?php esc_html_e( 'Toggle settings', 'space-core' ); ?></span>
        </button>
    </div>
    <div class="sc-am-details" hidden>
        <?php $this->render_item_controls( $item, $roles ); ?>
        <?php if ( $has_children ) : ?>
            <div class="sc-am-submenus">
                <h2><?php esc_html_e( 'Submenu Links', 'space-core' ); ?></h2>
                <ul class="sc-am-submenu-list" data-parent="<?php echo esc_attr( $slug ); ?>">
                    <?php foreach ( $item['subs'] as $sub ) : $this->render_submenu_item( $sub, $roles ); endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</li>
