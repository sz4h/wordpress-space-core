<?php

defined( 'ABSPATH' ) || exit;

$type           = (string) ( $item['type'] ?? 'menu' );
$visibility     = (string) ( $item['visibility_mode'] ?? 'show' );
$selected_roles = (array) ( $item['roles'] ?? [] );
?>
<div class="sc-am-controls">
    <?php if ( 'separator' !== $type ) : ?>
        <label class="sc-am-field">
            <span><?php esc_html_e( 'Custom URL', 'space-core' ); ?></span>
            <input type="url" class="sc-am-url" value="<?php echo esc_attr( $item['url'] ?? '' ); ?>" placeholder="<?php echo esc_attr( $this->menu_slug_to_url( (string) ( $item['slug'] ?? '' ) ) ); ?>">
        </label>
        <label class="sc-am-checkbox">
            <input type="checkbox" class="sc-am-open-new" <?php checked( ! empty( $item['open_new'] ) ); ?>>
            <?php esc_html_e( 'Open in new window', 'space-core' ); ?>
        </label>
        <?php if ( 'custom' === $type ) : ?>
            <label class="sc-am-field">
                <span><?php esc_html_e( 'Dashicon', 'space-core' ); ?></span>
                <input type="text" class="sc-am-icon" value="<?php echo esc_attr( $item['icon'] ?? 'dashicons-admin-links' ); ?>" placeholder="dashicons-admin-links">
            </label>
        <?php endif; ?>
    <?php endif; ?>

    <label class="sc-am-field">
        <span><?php esc_html_e( 'Visibility', 'space-core' ); ?></span>
        <select class="sc-am-visibility-mode">
            <option value="show" <?php selected( $visibility, 'show' ); ?>><?php esc_html_e( 'Show', 'space-core' ); ?></option>
            <option value="hide_all" <?php selected( $visibility, 'hide_all' ); ?>><?php esc_html_e( 'Hide from all roles', 'space-core' ); ?></option>
            <option value="hide_roles" <?php selected( $visibility, 'hide_roles' ); ?>><?php esc_html_e( 'Hide for selected roles', 'space-core' ); ?></option>
            <option value="hide_except_roles" <?php selected( $visibility, 'hide_except_roles' ); ?>><?php esc_html_e( 'Show only selected roles', 'space-core' ); ?></option>
        </select>
    </label>

    <fieldset class="sc-am-roles">
        <legend><?php esc_html_e( 'Roles', 'space-core' ); ?></legend>
        <?php foreach ( $roles as $role => $label ) : ?>
            <label>
                <input type="checkbox" class="sc-am-role" value="<?php echo esc_attr( $role ); ?>" <?php checked( in_array( $role, $selected_roles, true ) ); ?>>
                <?php echo esc_html( $label ); ?>
            </label>
        <?php endforeach; ?>
    </fieldset>
</div>
