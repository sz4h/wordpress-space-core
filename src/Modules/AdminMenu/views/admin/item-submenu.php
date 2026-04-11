<?php

defined( 'ABSPATH' ) || exit;

$id     = (string) $item['id'];
$parent = (string) ( $item['parent'] ?? '' );
$slug   = (string) ( $item['slug'] ?? '' );
$mode   = (string) ( $item['visibility_mode'] ?? 'show' );
?>
<li class="sc-am-submenu-item" data-id="<?php echo esc_attr( $id ); ?>" data-type="submenu" data-parent="<?php echo esc_attr( $parent ); ?>" data-slug="<?php echo esc_attr( $slug ); ?>">
    <div class="sc-am-submenu-main">
        <span class="sc-am-submenu-drag dashicons dashicons-menu" aria-hidden="true"></span>
        <input type="text" class="sc-am-label" value="<?php echo esc_attr( $item['label'] ?? '' ); ?>">
        <label class="sc-am-switch sc-am-switch-small" title="<?php esc_attr_e( 'Hide item', 'space-core' ); ?>">
            <input type="checkbox" class="sc-am-hide-toggle" <?php checked( 'show' !== $mode ); ?>>
            <span class="sc-am-switch-slider"></span>
        </label>
    </div>
    <div class="sc-am-submenu-controls">
        <?php $this->render_item_controls( $item, $roles ); ?>
    </div>
</li>
