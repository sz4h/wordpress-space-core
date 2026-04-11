<?php

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap sc-am-wrap">
    <div class="sc-am-toolbar">
        <h1><?php esc_html_e( 'Admin Menu Organizer', 'space-core' ); ?></h1>
        <div class="sc-am-toolbar-actions">
            <span id="sc-am-status" class="sc-am-status" aria-live="polite"></span>
            <button type="button" id="sc-am-save" class="button button-primary"><?php esc_html_e( 'Save Changes', 'space-core' ); ?></button>
        </div>
    </div>

    <?php if ( empty( $items ) ) : ?>
        <div class="notice notice-info inline"><p><?php esc_html_e( 'Menu snapshot not yet captured. Reload this page.', 'space-core' ); ?></p></div>
    <?php else : ?>
        <div class="sc-am-stage">
            <ul id="sc-am-menu-list" class="sc-am-list">
                <?php foreach ( $items as $item ) : $this->render_top_level_item( $item, $roles ); endforeach; ?>
            </ul>
        </div>

        <div class="sc-am-bottom-actions">
            <button type="button" id="sc-am-add-separator" class="button button-primary"><?php esc_html_e( 'Add Separator', 'space-core' ); ?></button>
            <button type="button" id="sc-am-add-custom" class="button"><?php esc_html_e( 'Add Custom Link', 'space-core' ); ?></button>
            <button type="button" id="sc-am-reset" class="button button-link-delete"><?php esc_html_e( 'Reset Menu', 'space-core' ); ?></button>
        </div>
    <?php endif; ?>
</div>
