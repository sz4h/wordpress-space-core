<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap sc-local-shipping-wrap">
    <h1><?php esc_html_e( 'Fixed Shipping by City', 'space-core' ); ?></h1>
    <nav class="nav-tab-wrapper woo-nav-tab-wrapper">
        <?php foreach ( $tabs as $key => $label ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=sc-local-shipping&tab=' . $key ) ); ?>" class="nav-tab<?php echo $tab === $key ? ' nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="sc-tab-content" style="margin-top:20px;">
        <?php match ( $tab ) {
            'cities' => $this->render_cities_tab(),
            'areas' => $this->render_areas_tab(),
            'settings' => $this->render_settings_tab(),
            'import' => $this->render_import_tab(),
            default => $this->render_cities_tab(),
        }; ?>
    </div>
</div>
