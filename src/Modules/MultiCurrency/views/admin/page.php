<?php

defined( 'ABSPATH' ) || exit;
?>
<nav class="nav-tab-wrapper" style="margin-bottom:20px;">
    <?php foreach ( $tabs as $key => $label ) : ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $page_slug . '&tab=' . $key ) ); ?>"
           class="nav-tab<?php echo $tab === $key ? ' nav-tab-active' : ''; ?>">
            <?php echo esc_html( $label ); ?>
        </a>
    <?php endforeach; ?>
</nav>

<?php
match ( $tab ) {
    'settings' => $this->render_settings_tab(),
    default    => $this->render_currencies_tab(),
};
?>
