<?php

defined( 'ABSPATH' ) || exit;

use Space\Core\Admin\SettingsAPI;

?>

<?php SettingsAPI::open_form( 'space_core_admin_bar_group' ); ?>
<h2><?php esc_html_e( 'Admin Bar Manager', 'space-core' ); ?></h2>

<table class="form-table" role="presentation">
    <tr>
        <th><?php esc_html_e( 'Hide Bar for Roles', 'space-core' ); ?></th>
        <td>
            <?php SettingsAPI::pillMultiSelect( 'space_core_admin_bar', 'hide_for_roles', $opts['hide_for_roles'], ( array_map( fn( $i ) => esc_html( translate_user_role( $i ) ), $roles ) ) ); ?>
            <p class="description" style="margin-top:8px;">
                <?php esc_html_e( 'The toolbar is always shown to users with the "Administrator" capability (manage_options), regardless of this setting.', 'space-core' ); ?>
            </p>
        </td>
    </tr>
</table>

<h2><?php esc_html_e( 'Disable Toolbar Nodes', 'space-core' ); ?></h2>

<?php if ( empty( $nodes ) ) : ?>
    <p class="description">
        <?php esc_html_e( 'No nodes discovered yet. Visit any page on the frontend or in the admin while logged in — the toolbar nodes will be detected automatically and appear here.', 'space-core' ); ?>
    </p>
<?php else :
    $top_level = [];
    $children = [];
    foreach ( $nodes as $node ) {
        if ( false === $node['parent'] || '' === $node['parent'] || 'top-secondary' === $node['parent'] ) {
            $top_level[ $node['id'] ] = $node;
        } else {
            $children[ $node['parent'] ][] = $node;
        }
    }
    ?>
    <p class="description" style="margin-bottom:12px;">
        <?php esc_html_e( 'Check nodes to remove from the toolbar. Removing a parent also removes its children.', 'space-core' ); ?>
    </p>
    <div id="sc-admin-bar-nodes" style="column-count:2;column-gap:24px;max-width:900px;">
        <?php foreach ( $top_level as $id => $node ) :
            $checked = in_array( $id, $opts['disabled_nodes'], true );
            $has_kids = ! empty( $children[ $id ] );
            ?>
            <div style="break-inside:avoid;margin-bottom:8px;">
                <?php /*SettingsAPI::checkbox( 'space_core_admin_bar', 'disabled_nodes', $opts['hide_for_roles'], $id , $node['title'] );*/ ?>
                <label style="display:flex;align-items:center;gap:6px;font-weight:600;cursor:pointer;">
                    <input type="checkbox"
                           name="space_core_admin_bar[disabled_nodes][]"
                           value="<?php echo esc_attr( $id ); ?>"
                            <?php checked( $checked ); ?>>
                    <?php echo esc_html( $node['title'] ); ?>
                    <code style="font-weight:normal;font-size:11px;color:#888;"><?php echo esc_html( $id ); ?></code>
                </label>
                <?php if ( $has_kids ) : ?>
                    <div style="padding-left:20px;margin-top:4px;">
                        <?php foreach ( $children[ $id ] as $child ) :
                            $child_checked = in_array( $child['id'], $opts['disabled_nodes'], true );
                            ?>
                            <label style="display:flex;align-items:center;gap:6px;margin-bottom:3px;cursor:pointer;">
                                <input type="checkbox"
                                       name="space_core_admin_bar[disabled_nodes][]"
                                       value="<?php echo esc_attr( $child['id'] ); ?>"
                                        <?php checked( $child_checked ); ?>>
                                <?php echo esc_html( $child['title'] ); ?>
                                <code style="font-size:11px;color:#888;"><?php echo esc_html( $child['id'] ); ?></code>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php SettingsAPI::close_form(); ?>
