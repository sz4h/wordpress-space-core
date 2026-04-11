<?php

namespace Space\Core\Modules\LocalShipping;

defined( 'ABSPATH' ) || exit;

use Space\Core\Abstracts\AbstractModule;
use WC_Cart;
use WC_Order;

/**
 * Fixed Shipping by City module.
 *
 * - Admin: submenu under WooCommerce with tabs (Cities / Areas / Settings / Import)
 * - Checkout: city+area combo field, session-based delivery fee, express option
 * - Orders: saves & displays delivery meta in admin, frontend, and email
 */
class Module extends AbstractModule {

    // WC session keys.
    const SESSION_CITY_ID = 'sc_city_id';
    const SESSION_AREA_ID = 'sc_area_id';
    const SESSION_DELIVERY_TYPE = 'sc_delivery_type';

    // Order meta keys.
    const META_CITY_NAME = '_sc_city_name';
    const META_AREA_NAME = '_sc_area_name';
    const META_DELIVERY_TYPE = '_sc_delivery_type';
    const META_DELIVERY_PRICE = '_sc_delivery_price';

    public function get_label(): string {
        return __( 'Fixed Shipping by City', 'space-core' );
    }

    public function get_description(): string {
        return __( 'Manage local delivery areas, per-area pricing, express fees, and minimum orders.', 'space-core' );
    }

    public function on_activate(): void {
        AreasDB::create_tables();
    }

    public function boot(): void {
        // Auto-migrate schema (adds country_code column to sc_ls_cities for older installs).
        $schema_version = get_option( 'space_core_ls_schema_version', '0' );
        if ( version_compare( $schema_version, '1.1', '<' ) ) {
            AreasDB::create_tables();
            update_option( 'space_core_ls_schema_version', '1.1', false );
        }

        // Admin submenu (always, even without module tab).
        add_action( 'admin_menu', [ $this, 'register_submenu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

        // AJAX — admin.
        add_action( 'wp_ajax_sc_save_ls_city', [ $this, 'ajax_save_city' ] );
        add_action( 'wp_ajax_sc_delete_ls_city', [ $this, 'ajax_delete_city' ] );
        add_action( 'wp_ajax_sc_save_ls_area', [ $this, 'ajax_save_area' ] );
        add_action( 'wp_ajax_sc_delete_ls_area', [ $this, 'ajax_delete_area' ] );
        add_action( 'wp_ajax_sc_get_ls_cities', [ $this, 'ajax_get_cities' ] );
        add_action( 'wp_ajax_sc_get_ls_areas', [ $this, 'ajax_get_areas' ] );
        add_action( 'wp_ajax_sc_save_ls_settings', [ $this, 'ajax_save_settings' ] );
        add_action( 'wp_ajax_sc_run_ls_seeder', [ $this, 'ajax_run_seeder' ] );

        // Frontend checkout.
        add_filter( 'woocommerce_billing_fields', [ $this, 'add_billing_fields' ], 20 );
        add_filter( 'woocommerce_form_field_sc_area_combo', [ $this, 'render_combo_field' ], 10, 4 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
        add_action( 'woocommerce_checkout_process', [ $this, 'validate_fields' ] );
        add_action( 'woocommerce_checkout_update_order_meta', [ $this, 'save_order_meta' ] );
        add_action( 'woocommerce_cart_calculate_fees', [ $this, 'apply_delivery_fee' ] );
        add_action( 'woocommerce_cart_emptied', [ $this, 'clear_session' ] );

        // AJAX — frontend (session update).
        add_action( 'wp_ajax_sc_set_delivery_session', [ $this, 'ajax_set_session' ] );
        add_action( 'wp_ajax_nopriv_sc_set_delivery_session', [ $this, 'ajax_set_session' ] );

        // Display in order views.
        add_action( 'woocommerce_order_details_after_order_table', [ $this, 'display_order_delivery' ], 5 );
        add_action( 'woocommerce_email_after_order_table', [ $this, 'display_email_delivery' ], 5, 2 );
        add_action( 'woocommerce_admin_order_data_after_billing_address', [ $this, 'display_admin_delivery' ] );
    }

    // =========================================================================
    // Admin — submenu + tabs
    // =========================================================================

    public function register_submenu(): void {
        add_submenu_page(
                'woocommerce',
                __( 'Fixed Shipping by City', 'space-core' ),
                __( 'Fixed Shipping by City', 'space-core' ),
                'manage_woocommerce',
                'sc-local-shipping',
                [ $this, 'render_page' ]
        );
    }

    public function enqueue_admin_assets( string $hook ): void {
        if ( 'woocommerce_page_sc-local-shipping' !== $hook ) {
            return;
        }
        wp_enqueue_style(
                'space-core-material-symbols',
                'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200',
                [],
                null
        );
        wp_enqueue_style( 'space-core-admin', SPACE_CORE_URL . 'assets/css/admin.css', [
                'dashicons',
                'space-core-material-symbols'
        ], SPACE_CORE_VERSION );
        wp_enqueue_script( 'space-core-admin', SPACE_CORE_URL . 'assets/js/admin.js', [ 'jquery' ], SPACE_CORE_VERSION, true );
        wp_localize_script( 'space-core-admin', 'scAdmin', [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'sc_local_shipping_nonce' ),
        ] );
    }

    public function render_page(): void {
        $tab  = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'cities';
        $tabs = [
                'cities'   => __( 'Cities', 'space-core' ),
                'areas'    => __( 'Areas', 'space-core' ),
                'settings' => __( 'Settings', 'space-core' ),
                'import'   => __( 'Import', 'space-core' ),
        ];
        ?>
        <div class="wrap sc-local-shipping-wrap">
            <h1><?php esc_html_e( 'Fixed Shipping by City', 'space-core' ); ?></h1>

            <nav class="nav-tab-wrapper woo-nav-tab-wrapper">
                <?php foreach ( $tabs as $key => $label ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=sc-local-shipping&tab=' . $key ) ); ?>"
                       class="nav-tab<?php echo $tab === $key ? ' nav-tab-active' : ''; ?>">
                        <?php echo esc_html( $label ); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="sc-tab-content" style="margin-top:20px;">
                <?php
                match ( $tab ) {
                    'cities' => $this->render_cities_tab(),
                    'areas' => $this->render_areas_tab(),
                    'settings' => $this->render_settings_tab(),
                    'import' => $this->render_import_tab(),
                    default => $this->render_cities_tab(),
                };
                ?>
            </div>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Tab: Cities
    // -------------------------------------------------------------------------

    private function render_cities_tab(): void {
        $countries        = $this->admin_countries();
        $selected_country = $this->selected_admin_country( $countries );
        $cities           = $this->get_admin_cities_for_country( $selected_country );
        ?>
        <?php $this->render_country_filter( $selected_country, $countries, 'cities' ); ?>
        <div class="sc-table-wrap">
            <table class="widefat sc-ajax-table sc-responsive-table" id="sc-cities-table"
                   data-action-save="sc_save_ls_city"
                   data-action-delete="sc_delete_ls_city"
                   data-nonce="<?php echo esc_attr( wp_create_nonce( 'sc_local_shipping_nonce' ) ); ?>"
                   data-country="<?php echo esc_attr( $selected_country ); ?>">
                <thead>
                <tr>
                    <th><?php esc_html_e( 'Name (En/Ar)', 'space-core' ); ?></th>
                    <th><?php esc_html_e( 'Active', 'space-core' ); ?></th>
                    <th><?php esc_html_e( 'Sort', 'space-core' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'space-core' ); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php $this->render_city_table_rows( $cities, $selected_country ); ?>
                </tbody>
            </table>

            <p>
                <button type="button" class="button sc-add-row" data-table="sc-cities-table">
                    + <?php esc_html_e( 'Add City', 'space-core' ); ?>
                </button>
            </p>
        </div>
        <?php
    }

    private function admin_countries(): array {
        return function_exists( 'WC' ) && WC()->countries ? WC()->countries->get_countries() : [];
    }

    private function selected_admin_country( array $countries ): string {
        $raw = '';
        if ( isset( $_REQUEST['country'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
            $raw = (string) wp_unslash( $_REQUEST['country'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
        }

        $country = $this->normalize_admin_country( $raw, $countries );
        if ( $country ) {
            return $country;
        }

        $base_country = function_exists( 'WC' ) && WC()->countries ? WC()->countries->get_base_country() : '';
        $country      = $this->normalize_admin_country( $base_country, $countries );
        if ( $country ) {
            return $country;
        }

        return (string) array_key_first( $countries );
    }

    private function normalize_admin_country( string $country, array $countries ): string {
        $country = strtoupper( substr( sanitize_key( $country ), 0, 2 ) );
        if ( '' === $country ) {
            return '';
        }
        if ( empty( $countries ) ) {
            return $country;
        }

        return isset( $countries[ $country ] ) ? $country : '';
    }

    private function get_admin_cities_for_country( string $country ): array {
        return $country ? AreasDB::get_cities_by_country( $country, false ) : AreasDB::get_cities();
    }

    private function render_country_filter( string $selected_country, array $countries, string $target ): void {
        $field_id = 'sc-ls-' . $target . '-country';
        ?>
        <div class="sc-ls-country-filter">
            <label for="<?php echo esc_attr( $field_id ); ?>"><?php esc_html_e( 'Country', 'space-core' ); ?></label>
            <select id="<?php echo esc_attr( $field_id ); ?>" class="sc-ls-country-select"
                    data-target="<?php echo esc_attr( $target ); ?>">
                <?php echo $this->admin_country_options_html( $countries, $selected_country ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            </select>
        </div>
        <?php
    }

    private function admin_country_options_html( array $countries, string $selected_country ): string {
        if ( empty( $countries ) ) {
            return '<option value="">' . esc_html__( '— Any —', 'space-core' ) . '</option>';
        }

        $html = '';
        foreach ( $countries as $code => $label ) {
            $html .= '<option value="' . esc_attr( $code ) . '"' . selected( $selected_country, $code, false ) . '>' . esc_html( $label ) . '</option>';
        }

        return $html;
    }

    private function render_city_table_rows( array $cities, string $country ): void {
        foreach ( $cities as $city ) {
            $this->render_city_table_row( $city, $country );
        }

        $this->render_city_template_row( $country );
    }

    private function render_city_table_row( array $city, string $country ): void {
        $name        = AreasDB::decode_name( $city['name'] );
        $row_country = $country ?: (string) ( $city['country_code'] ?? '' );
        ?>
        <tr data-id="<?php echo esc_attr( $city['id'] ); ?>">
            <td data-label="<?php esc_attr_e( 'Name (En/Ar)', 'space-core' ); ?>">
                <?php $this->render_name_fields( $name ); ?>
                <input type="hidden" class="sc-field" data-key="country_code"
                       value="<?php echo esc_attr( $row_country ); ?>">
            </td>
            <td data-label="<?php esc_attr_e( 'Active', 'space-core' ); ?>">
                <?php $this->render_active_switch( (int) $city['is_active'] === 1 ); ?>
            </td>
            <td data-label="<?php esc_attr_e( 'Sort', 'space-core' ); ?>">
                <input type="number" class="sc-field sc-ls-sort-field" data-key="sort_order"
                       value="<?php echo esc_attr( $city['sort_order'] ); ?>">
            </td>
            <td data-label="<?php esc_attr_e( 'Actions', 'space-core' ); ?>">
                <?php $this->render_row_actions( true ); ?>
            </td>
        </tr>
        <?php
    }

    private function render_name_fields( array $name ): void {
        ?>
        <div class="sc-ls-name-fields">
            <label>
                <span><?php esc_html_e( 'EN', 'space-core' ); ?></span>
                <input type="text" class="sc-field" data-key="name_en"
                       value="<?php echo esc_attr( $name['en'] ?? '' ); ?>">
            </label>
            <label>
                <span><?php esc_html_e( 'AR', 'space-core' ); ?></span>
                <input type="text" class="sc-field" data-key="name_ar"
                       value="<?php echo esc_attr( $name['ar'] ?? '' ); ?>" dir="rtl">
            </label>
        </div>
        <?php
    }

    private function render_active_switch( bool $checked ): void {
        ?>
        <label class="sc-ls-switch">
            <input type="checkbox" class="sc-field" data-key="is_active" <?php checked( $checked ); ?>>
            <span class="sc-ls-switch-slider" aria-hidden="true"></span>
            <span class="screen-reader-text"><?php esc_html_e( 'Active', 'space-core' ); ?></span>
        </label>
        <?php
    }

    private function render_row_actions( bool $saved ): void {
        ?>
        <div class="sc-ls-row-actions">
            <button type="button" class="button-link sc-ls-icon-action sc-save-row"
                    data-icon="check"
                    aria-label="<?php esc_attr_e( 'Save', 'space-core' ); ?>">
                <span class="sc-ls-material-icon" aria-hidden="true">check</span>
            </button>
            <button type="button"
                    class="button-link sc-ls-icon-action <?php echo $saved ? 'sc-delete-row' : 'sc-remove-new-row'; ?>"
                    data-icon="close"
                    aria-label="<?php echo esc_attr( $saved ? __( 'Delete', 'space-core' ) : __( 'Remove', 'space-core' ) ); ?>">
                <span class="sc-ls-material-icon" aria-hidden="true">close</span>
            </button>
        </div>
        <?php
    }

    private function render_city_template_row( string $country ): void {
        ?>
        <tr class="sc-new-row-template" style="display:none;" data-id="0">
            <td data-label="<?php esc_attr_e( 'Name (En/Ar)', 'space-core' ); ?>">
                <?php $this->render_name_fields( [] ); ?>
                <input type="hidden" class="sc-field" data-key="country_code"
                       value="<?php echo esc_attr( $country ); ?>">
            </td>
            <td data-label="<?php esc_attr_e( 'Active', 'space-core' ); ?>">
                <?php $this->render_active_switch( true ); ?>
            </td>
            <td data-label="<?php esc_attr_e( 'Sort', 'space-core' ); ?>">
                <input type="number" class="sc-field sc-ls-sort-field" data-key="sort_order" value="0">
            </td>
            <td data-label="<?php esc_attr_e( 'Actions', 'space-core' ); ?>">
                <?php $this->render_row_actions( false ); ?>
            </td>
        </tr>
        <?php
    }

    private function render_areas_tab(): void {
        $countries         = $this->admin_countries();
        $selected_country  = $this->selected_admin_country( $countries );
        $cities            = $this->get_admin_cities_for_country( $selected_country );
        $city_ids          = array_map( 'absint', wp_list_pluck( $cities, 'id' ) );
        $requested_city_id = isset( $_GET['city_id'] ) ? absint( $_GET['city_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $selected_city_id  = in_array( $requested_city_id, $city_ids, true ) ? $requested_city_id : (int) ( $city_ids[0] ?? 0 );
        $selected_city     = null;

        foreach ( $cities as $city ) {
            if ( (int) $city['id'] === $selected_city_id ) {
                $selected_city = $city;
                break;
            }
        }

        $selected_city_name = $selected_city ? AreasDB::resolve_name( AreasDB::decode_name( $selected_city['name'] ) ) : '';
        if ( $selected_city && '' === $selected_city_name ) {
            $selected_city_name = $this->city_display_name( $selected_city );
        }
        $city_areas = $selected_city_id ? AreasDB::get_areas( $selected_city_id ) : [];
        ?>
        <?php $this->render_country_filter( $selected_country, $countries, 'areas' ); ?>
        <div class="sc-ls-areas-layout">
            <aside class="sc-ls-city-menu" aria-label="<?php esc_attr_e( 'Cities', 'space-core' ); ?>">
                <h2><?php esc_html_e( 'Cities', 'space-core' ); ?></h2>
                <?php $this->render_city_menu( $cities, $selected_city_id, $selected_country ); ?>
            </aside>

            <div class="sc-ls-areas-panel">
                <h2 class="sc-ls-areas-title">
                    <?php if ( $selected_city_name ) : ?>
                        <?php
                        printf(
                        /* translators: %s: city name */
                                esc_html__( 'Areas in %s', 'space-core' ),
                                esc_html( $selected_city_name )
                        );
                        ?>
                    <?php else : ?>
                        <?php esc_html_e( 'Areas', 'space-core' ); ?>
                    <?php endif; ?>
                </h2>

                <div class="sc-table-wrap">
                    <table class="widefat sc-ajax-table sc-responsive-table" id="sc-areas-table"
                           data-action-save="sc_save_ls_area"
                           data-action-delete="sc_delete_ls_area"
                           data-nonce="<?php echo esc_attr( wp_create_nonce( 'sc_local_shipping_nonce' ) ); ?>"
                           data-country="<?php echo esc_attr( $selected_country ); ?>"
                           data-selected-city="<?php echo esc_attr( $selected_city_id ); ?>">
                        <thead>
                        <tr>
                            <th><?php esc_html_e( 'Name (En/Ar)', 'space-core' ); ?></th>
                            <?php $this->render_area_copy_header( __( 'Price', 'space-core' ), 'delivery_price' ); ?>
                            <?php $this->render_area_copy_header( __( 'Express Fee', 'space-core' ), 'express_fee' ); ?>
                            <?php $this->render_area_copy_header( __( 'Min Order', 'space-core' ), 'minimum_order' ); ?>
                            <?php $this->render_area_copy_header( __( 'Free Min', 'space-core' ), 'free_minimum_order' ); ?>
                            <?php $this->render_area_copy_header( __( 'Active', 'space-core' ), 'is_active' ); ?>
                            <th><?php esc_html_e( 'Sort', 'space-core' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'space-core' ); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php $this->render_area_table_rows( $city_areas, $cities, $selected_city_id ); ?>
                        </tbody>
                    </table>

                    <div class="sc-table-footer sc-ls-area-actions">
                        <button type="button" class="button sc-add-row"
                                data-table="sc-areas-table" <?php disabled( ! $selected_city_id ); ?>>
                            + <?php esc_html_e( 'Add Area', 'space-core' ); ?>
                        </button>
                        <button type="button" class="button button-primary sc-ls-save-visible"
                                data-table="sc-areas-table" <?php disabled( ! $selected_city_id ); ?>>
                            <?php esc_html_e( 'Save All', 'space-core' ); ?>
                        </button>
                        <span class="sc-save-status sc-ls-save-all-status" aria-live="polite"></span>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private function city_display_name( array $city ): string {
        $name = AreasDB::resolve_name( AreasDB::decode_name( $city['name'] ) );
        if ( '' !== $name ) {
            return $name;
        }

        return sprintf(
        /* translators: %d: city id */
                __( 'City #%d', 'space-core' ),
                (int) $city['id']
        );
    }

    private function render_city_menu( array $cities, int $selected_city_id, string $country ): void {
        ?>
        <ul class="sc-ls-city-list">
            <?php if ( empty( $cities ) ) : ?>
                <li class="sc-ls-empty-state"><?php esc_html_e( 'No cities found for this country.', 'space-core' ); ?></li>
            <?php endif; ?>
            <?php foreach ( $cities as $city ) :
                $city_id = (int) $city['id'];
                $is_selected = $city_id === $selected_city_id;
                ?>
                <li>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=sc-local-shipping&tab=areas&country=' . rawurlencode( $country ) . '&city_id=' . $city_id ) ); ?>"
                       class="sc-ls-city-link<?php echo $is_selected ? ' sc-ls-city-link-active' : ''; ?>"
                       data-city-id="<?php echo esc_attr( $city_id ); ?>"
                            <?php echo $is_selected ? 'aria-current="page"' : ''; ?>>
                        <?php echo esc_html( $this->city_display_name( $city ) ); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
    }

    // -------------------------------------------------------------------------
    // Tab: Areas
    // -------------------------------------------------------------------------

    private function render_area_copy_header( string $label, string $field_key ): void {
        $aria_label = sprintf(
        /* translators: %s: copied field label */
                __( 'Copy first row %s to all visible rows', 'space-core' ),
                $label
        );
        ?>
        <th>
            <span class="sc-ls-copy-heading">
                <span><?php echo esc_html( $label ); ?></span>
                <button type="button" class="button-link sc-ls-copy-column"
                        data-key="<?php echo esc_attr( $field_key ); ?>"
                        aria-label="<?php echo esc_attr( $aria_label ); ?>">
                    <span class="sc-ls-material-icon" aria-hidden="true">save</span>
                </button>
            </span>
        </th>
        <?php
    }

    private function render_area_table_rows( array $areas, array $cities, int $selected_city_id ): void {
        if ( ! $selected_city_id ) {
            ?>
            <tr class="sc-ls-empty-row">
                <td colspan="9"><?php esc_html_e( 'Select a country with at least one city before adding areas.', 'space-core' ); ?></td>
            </tr>
            <?php
            return;
        }

        foreach ( $areas as $area ) {
            $this->render_area_table_row( $area, $cities, $selected_city_id );
        }

        $this->render_area_template_row( $cities, $selected_city_id );
    }

    private function render_area_table_row( array $area, array $cities, int $selected_city_id ): void {
        $name = AreasDB::decode_name( $area['name'] );
        ?>
        <tr data-id="<?php echo esc_attr( $area['id'] ); ?>" data-city-id="<?php echo esc_attr( $area['city_id'] ); ?>">
            <td data-label="<?php esc_attr_e( 'Name (En/Ar)', 'space-core' ); ?>">
                <input type="hidden" name="city_id" class="sc-field" data-key='city_id'
                       value="<?php echo esc_attr( $area['city_id'] ); ?>">
                <?php $this->render_name_fields( $name ); ?>
            </td>
            <?php $this->render_area_price_cells( $area ); ?>
            <td data-label="<?php esc_attr_e( 'Actions', 'space-core' ); ?>">
                <?php $this->render_row_actions( true ); ?>
            </td>
        </tr>
        <?php
    }

    private function render_area_price_cells( array $area ): void {
        $fields = [
                'delivery_price'     => __( 'Price', 'space-core' ),
                'express_fee'        => __( 'Express Fee', 'space-core' ),
                'minimum_order'      => __( 'Min Order', 'space-core' ),
                'free_minimum_order' => __( 'Free Min', 'space-core' ),
        ];
        foreach ( $fields as $key => $label ) : ?>
            <td data-label="<?php echo esc_attr( $label ); ?>">
                <input type="number" step="0.001" min="0" class="sc-field sc-ls-price-field"
                       data-key="<?php echo esc_attr( $key ); ?>"
                       value="<?php echo esc_attr( $area[ $key ] ?? '0' ); ?>">
            </td>
        <?php endforeach;
        ?>
        <td data-label="<?php esc_attr_e( 'Active', 'space-core' ); ?>">
            <?php $this->render_active_switch( (int) ( $area['is_active'] ?? 1 ) === 1 ); ?>
        </td>
        <td data-label="<?php esc_attr_e( 'Sort', 'space-core' ); ?>">
            <input type="number" class="sc-field sc-ls-sort-field" data-key="sort_order"
                   value="<?php echo esc_attr( $area['sort_order'] ?? '0' ); ?>">
        </td>
        <?php
    }

    private function render_area_template_row( array $cities, int $selected_city_id ): void {
        ?>
        <tr class="sc-new-row-template" style="display:none;" data-id="0"
            data-city-id="<?php echo esc_attr( $selected_city_id ); ?>">
            <td data-label="<?php esc_attr_e( 'Name (En/Ar)', 'space-core' ); ?>">
                <input type='hidden' class='sc-field' data-key='city_id' name='city_id'
                       value="<?php echo esc_attr( $selected_city_id ); ?>">
                <?php $this->render_name_fields( [] ); ?>
            </td>
            <?php $this->render_area_price_cells( [] ); ?>
            <td data-label="<?php esc_attr_e( 'Actions', 'space-core' ); ?>">
                <?php $this->render_row_actions( false ); ?>
            </td>
        </tr>
        <?php
    }

    private function render_settings_tab(): void {
        $opts            = get_option( 'space_core_local_shipping', [] );
        $express_enabled = ! empty( $opts['express_enabled'] );
        ?>
        <form method="post" id="sc-ls-settings-form">
            <?php wp_nonce_field( 'sc_local_shipping_nonce', 'sc_ls_settings_nonce' ); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Enable Express Delivery', 'space-core' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="express_enabled" value="1"
                                    <?php checked( $express_enabled ); ?>>
                            <?php esc_html_e( 'Show an Express Delivery option at checkout with an additional fee per area.', 'space-core' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="button" id="sc-ls-save-settings" class="button button-primary">
                    <?php esc_html_e( 'Save Settings', 'space-core' ); ?>
                </button>
                <span class="sc-save-status" style="margin-left:10px;"></span>
            </p>
        </form>
        <script>
            jQuery(function ($) {
                $('#sc-ls-save-settings').on('click', function () {
                    var $btn = $(this);
                    var $status = $('.sc-save-status');
                    $btn.prop('disabled', true);
                    $.post(scAdmin.ajaxUrl, {
                        action: 'sc_save_ls_settings',
                        nonce: scAdmin.nonce,
                        express_enabled: $('input[name="express_enabled"]').is(':checked') ? 1 : 0,
                    }, function (res) {
                        $btn.prop('disabled', false);
                        $status.text(res.success ? '<?php esc_html_e( 'Saved!', 'space-core' ); ?>' : '<?php esc_html_e( 'Error.', 'space-core' ); ?>');
                        setTimeout(function () {
                            $status.text('');
                        }, 2000);
                    });
                });
            });
        </script>
        <?php
    }

    private function render_import_tab(): void {
        $existing_cities = count( AreasDB::get_cities() );
        $nonce           = wp_create_nonce( 'sc_local_shipping_nonce' );

        $countries = [
                'KW' => __( 'Kuwait', 'space-core' ),
        ];
        ?>
        <div class="sc-seeder-wrap" style="max-width:600px;">
            <h2><?php esc_html_e( 'Seed Delivery Data', 'space-core' ); ?></h2>
            <p><?php esc_html_e( 'Quickly populate cities and areas with pre-built data for a country. This will add new entries without removing existing ones.', 'space-core' ); ?></p>

            <?php if ( $existing_cities > 0 ) : ?>
                <div class="notice notice-warning inline" style="margin:0 0 16px;">
                    <p>
                        <?php
                        printf(
                        /* translators: %d: number of existing cities */
                                esc_html__( 'You already have %d city(ies) in the database. Running the seeder will add new entries on top — it will not overwrite existing data.', 'space-core' ),
                                $existing_cities
                        );
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <table class="form-table" style="max-width:500px;">
                <tr>
                    <th scope="row">
                        <label for="sc-seeder-country"><?php esc_html_e( 'Country', 'space-core' ); ?></label>
                    </th>
                    <td>
                        <select id="sc-seeder-country" style="min-width:220px;">
                            <?php foreach ( $countries as $code => $label ) : ?>
                                <option value="<?php echo esc_attr( $code ); ?>">
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>

            <p>
                <button type="button" id="sc-run-seeder" class="button button-primary"
                        style="height:36px;line-height:34px;">
                    <?php esc_html_e( 'Run Seeder', 'space-core' ); ?>
                </button>
                <span id="sc-seeder-status" style="margin-left:12px;font-weight:600;"></span>
            </p>

            <div id="sc-seeder-result"
                 style="display:none;margin-top:12px;padding:12px 16px;background:#f0f8f0;border:1px solid #b7dfb7;border-radius:4px;"></div>
        </div>

        <script>
            jQuery(function ($) {
                $('#sc-run-seeder').on('click', function () {
                    var $btn = $(this);
                    var $status = $('#sc-seeder-status');
                    var $result = $('#sc-seeder-result');
                    var country = $('#sc-seeder-country').val();

                    if (!confirm('<?php echo esc_js( __( 'Run the seeder for the selected country? This will add new cities and areas.', 'space-core' ) ); ?>')) {
                        return;
                    }

                    $btn.prop('disabled', true).text('<?php echo esc_js( __( 'Running…', 'space-core' ) ); ?>');
                    $status.text('').css('color', '#888');
                    $result.hide();

                    $.post(scAdmin.ajaxUrl, {
                        action: 'sc_run_ls_seeder',
                        nonce: '<?php echo esc_js( $nonce ); ?>',
                        country: country,
                    }, function (res) {
                        $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Run Seeder', 'space-core' ) ); ?>');
                        if (res.success) {
                            $status.text('<?php echo esc_js( __( 'Done!', 'space-core' ) ); ?>').css('color', '#2e7d32');
                            $result.html(
                                '<strong>' + res.data.message + '</strong>' +
                                '<br><?php echo esc_js( __( 'Go to the', 'space-core' ) ); ?> ' +
                                '<a href="<?php echo esc_url( admin_url( 'admin.php?page=sc-local-shipping&tab=cities' ) ); ?>"><?php echo esc_js( __( 'Cities tab', 'space-core' ) ); ?></a> ' +
                                '<?php echo esc_js( __( 'or', 'space-core' ) ); ?> ' +
                                '<a href="<?php echo esc_url( admin_url( 'admin.php?page=sc-local-shipping&tab=areas' ) ); ?>"><?php echo esc_js( __( 'Areas tab', 'space-core' ) ); ?></a> ' +
                                '<?php echo esc_js( __( 'to review and adjust prices.', 'space-core' ) ); ?>'
                            ).show();
                        } else {
                            $status.text((res.data && res.data.message) || '<?php echo esc_js( __( 'Error.', 'space-core' ) ); ?>').css('color', '#c62828');
                        }
                    }).fail(function () {
                        $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Run Seeder', 'space-core' ) ); ?>');
                        $status.text('<?php echo esc_js( __( 'Server error.', 'space-core' ) ); ?>').css('color', '#c62828');
                    });
                });
            });
        </script>
        <?php
    }

    // -------------------------------------------------------------------------
    // Tab: Settings
    // -------------------------------------------------------------------------

    public function ajax_get_cities(): void {
        $this->verify_nonce();

        $countries        = $this->admin_countries();
        $selected_country = $this->selected_admin_country( $countries );
        $cities           = $this->get_admin_cities_for_country( $selected_country );
        $first_city       = $cities[0] ?? null;
        $first_city_id    = $first_city ? (int) $first_city['id'] : 0;

        wp_send_json_success( [
                'country'         => $selected_country,
                'rows'            => $this->capture_html( fn() => $this->render_city_table_rows( $cities, $selected_country ) ),
                'city_menu'       => $this->capture_html( fn() => $this->render_city_menu( $cities, $first_city_id, $selected_country ) ),
                'first_city_id'   => $first_city_id,
                'first_city_name' => $first_city ? $this->city_display_name( $first_city ) : '',
        ] );
    }

    // -------------------------------------------------------------------------
    // Tab: Import / Seeder
    // -------------------------------------------------------------------------

    private function verify_nonce(): void {
        if ( ! check_ajax_referer( 'sc_local_shipping_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Security check failed.', 'space-core' ) ], 403 );
        }
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'space-core' ) ], 403 );
        }
    }

    // =========================================================================
    // AJAX — admin
    // =========================================================================

    private function capture_html( callable $callback ): string {
        ob_start();
        $callback();

        return (string) ob_get_clean();
    }

    public function ajax_get_areas(): void {
        $this->verify_nonce();

        $countries        = $this->admin_countries();
        $selected_country = $this->selected_admin_country( $countries );
        $cities           = $this->get_admin_cities_for_country( $selected_country );
        $city_ids         = array_map( 'absint', wp_list_pluck( $cities, 'id' ) );
        $requested_city   = isset( $_POST['city_id'] ) ? absint( $_POST['city_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $selected_city_id = in_array( $requested_city, $city_ids, true ) ? $requested_city : (int) ( $city_ids[0] ?? 0 );
        $selected_city    = null;

        foreach ( $cities as $city ) {
            if ( (int) $city['id'] === $selected_city_id ) {
                $selected_city = $city;
                break;
            }
        }

        $areas     = $selected_city_id ? AreasDB::get_areas( $selected_city_id ) : [];
        $city_name = $selected_city ? $this->city_display_name( $selected_city ) : '';

        wp_send_json_success( [
                'country'          => $selected_country,
                'selected_city_id' => $selected_city_id,
                'selected_city'    => $city_name,
                'title'            => $city_name ? sprintf(
                /* translators: %s: city name */
                        __( 'Areas in %s', 'space-core' ),
                        $city_name
                ) : __( 'Areas', 'space-core' ),
                'rows'             => $this->capture_html( fn() => $this->render_area_table_rows( $areas, $cities, $selected_city_id ) ),
        ] );
    }

    public function ajax_save_city(): void {
        $this->verify_nonce();

        $id      = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        $name_en = sanitize_text_field( wp_unslash( $_POST['name_en'] ?? '' ) );
        $name_ar = sanitize_text_field( wp_unslash( $_POST['name_ar'] ?? '' ) );
        $country = sanitize_text_field( wp_unslash( $_POST['country_code'] ?? '' ) );
        $active  = isset( $_POST['is_active'] ) ? (int) $_POST['is_active'] : 0;
        $sort    = isset( $_POST['sort_order'] ) ? absint( $_POST['sort_order'] ) : 0;

        $data = [
                'name'         => [ 'en' => $name_en, 'ar' => $name_ar ],
                'country_code' => $country,
                'is_active'    => $active,
                'sort_order'   => $sort,
        ];

        if ( $id ) {
            AreasDB::update_city( $id, $data );
            wp_send_json_success( [ 'id' => $id ] );
        } else {
            $new_id = AreasDB::insert_city( $data );
            if ( $new_id ) {
                wp_send_json_success( [ 'id' => $new_id ] );
            } else {
                wp_send_json_error( [ 'message' => __( 'Could not save city.', 'space-core' ) ] );
            }
        }
    }

    public function ajax_delete_city(): void {
        $this->verify_nonce();
        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        if ( ! $id || ! AreasDB::delete_city( $id ) ) {
            wp_send_json_error( [ 'message' => __( 'Could not delete city.', 'space-core' ) ] );
        }
        wp_send_json_success();
    }

    public function ajax_save_area(): void {
        $this->verify_nonce();

        $id      = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        $city_id = isset( $_POST['city_id'] ) ? absint( $_POST['city_id'] ) : 0;
        $name_en = sanitize_text_field( wp_unslash( $_POST['name_en'] ?? '' ) );
        $name_ar = sanitize_text_field( wp_unslash( $_POST['name_ar'] ?? '' ) );
        $active  = isset( $_POST['is_active'] ) ? (int) $_POST['is_active'] : 0;
        $sort    = isset( $_POST['sort_order'] ) ? absint( $_POST['sort_order'] ) : 0;

        $data = [
                'city_id'            => $city_id,
                'name'               => [ 'en' => $name_en, 'ar' => $name_ar ],
                'delivery_price'     => (float) ( $_POST['delivery_price'] ?? 0 ),
                'express_fee'        => (float) ( $_POST['express_fee'] ?? 0 ),
                'minimum_order'      => (float) ( $_POST['minimum_order'] ?? 0 ),
                'free_minimum_order' => (float) ( $_POST['free_minimum_order'] ?? 0 ),
                'is_active'          => $active,
                'sort_order'         => $sort,
        ];

        if ( $id ) {
            AreasDB::update_area( $id, $data );
            wp_send_json_success( [ 'id' => $id ] );
        } else {
            $new_id = AreasDB::insert_area( $data );
            if ( $new_id ) {
                wp_send_json_success( [ 'id' => $new_id ] );
            } else {
                wp_send_json_error( [ 'message' => __( 'Could not save area.', 'space-core' ) ] );
            }
        }
    }

    public function ajax_delete_area(): void {
        $this->verify_nonce();
        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        if ( ! $id || ! AreasDB::delete_area( $id ) ) {
            wp_send_json_error( [ 'message' => __( 'Could not delete area.', 'space-core' ) ] );
        }
        wp_send_json_success();
    }

    public function ajax_save_settings(): void {
        $this->verify_nonce();
        $express                 = isset( $_POST['express_enabled'] ) ? (int) $_POST['express_enabled'] : 0;
        $opts                    = get_option( 'space_core_local_shipping', [] );
        $opts['express_enabled'] = $express;
        update_option( 'space_core_local_shipping', $opts );
        wp_send_json_success();
    }

    public function ajax_run_seeder(): void {
        $this->verify_nonce();

        $country = isset( $_POST['country'] ) ? sanitize_key( wp_unslash( $_POST['country'] ) ) : '';

        $seeder_map = [
                'KW' => Seeders\Kuwait::class,
        ];

        if ( ! isset( $seeder_map[ strtoupper( $country ) ] ) ) {
            wp_send_json_error( [ 'message' => __( 'Unsupported country.', 'space-core' ) ] );
        }

        $seeder = $seeder_map[ strtoupper( $country ) ];
        $counts = $seeder::seed();

        wp_send_json_success( [
                'message' => sprintf(
                /* translators: 1: cities count 2: areas count */
                        __( 'Seeded %1$d cities and %2$d areas successfully.', 'space-core' ),
                        $counts['cities'],
                        $counts['areas']
                ),
                'counts'  => $counts,
        ] );
    }

    public function add_billing_fields( array $fields ): array {
        $opts            = get_option( 'space_core_local_shipping', [] );
        $express_enabled = ! empty( $opts['express_enabled'] );

        // Detect current customer country. Only show the combo if that
        // country has cities configured — otherwise fall back to WC's
        // default country/state dropdowns for international checkout.
        $country = '';
        if ( function_exists( 'WC' ) && WC()->customer ) {
            $country = (string) WC()->customer->get_billing_country();
        }
        if ( $country === '' ) {
            $country = WC()->countries ? WC()->countries->get_base_country() : '';
        }

        if ( ! $country || ! AreasDB::country_has_cities( $country ) ) {
            // No cities for this country: let WooCommerce render its
            // standard billing_country / billing_state / billing_city fields.
            return $fields;
        }

        $fields['billing_sc_area'] = [
                'type'     => 'sc_area_combo',
                'label'    => __( 'Delivery Area', 'space-core' ),
                'required' => true,
                'class'    => [ 'form-row-wide', 'sc-area-field' ],
                'priority' => 45,
        ];

        if ( $express_enabled ) {
            $saved_type                         = WC()->session ? (string) WC()->session->get( self::SESSION_DELIVERY_TYPE, 'normal' ) : 'normal';
            $fields['billing_sc_delivery_type'] = [
                    'label'    => __( 'Delivery Type', 'space-core' ),
                    'type'     => 'select',
                    'required' => false,
                    'class'    => [ 'form-row-wide' ],
                    'options'  => [
                            'normal'  => __( 'Standard Delivery', 'space-core' ),
                            'express' => __( 'Express Delivery (extra fee)', 'space-core' ),
                    ],
                    'default'  => $saved_type,
                    'priority' => 46,
            ];
        }

        return $fields;
    }

    // =========================================================================
    // Checkout — billing fields
    // =========================================================================

    public function render_combo_field( mixed $field, string $key, array $args, mixed $value ): string {
        $session    = WC()->session;
        $saved_area = $session ? (int) $session->get( self::SESSION_AREA_ID, 0 ) : 0;

        $grouped       = AreasDB::get_all_areas_grouped();
        $saved_name    = '';
        $saved_city_id = 0;
        $currency      = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '';

        // Resolve saved area display name.
        foreach ( $grouped as $cid => $group ) {
            foreach ( $group['areas'] as $area ) {
                if ( (int) $area['id'] === $saved_area ) {
                    $saved_name    = AreasDB::resolve_name( AreasDB::decode_name( $area['name'] ) );
                    $saved_city_id = $cid;
                    break 2;
                }
            }
        }

        $required_html = ! empty( $args['required'] )
                ? ' <abbr class="required" title="' . esc_attr_x( 'required', 'required field', 'woocommerce' ) . '">*</abbr>'
                : '';
        $class_str     = implode( ' ', array_map( 'sanitize_html_class', (array) ( $args['class'] ?? [] ) ) );

        ob_start();
        ?>
        <div class="form-row <?php echo esc_attr( $class_str ); ?> sc-combo-wrap"
             id="<?php echo esc_attr( $key ); ?>_field">
            <label><?php echo wp_kses_post( $args['label'] . $required_html ); ?></label>

            <input type="hidden" name="billing_sc_area_id" id="billing_sc_area_id"
                   value="<?php echo esc_attr( $saved_area ?: '' ); ?>">
            <input type="hidden" name="billing_sc_city_id" id="billing_sc_city_id"
                   value="<?php echo esc_attr( $saved_city_id ?: '' ); ?>">

            <div class="sc-combo-trigger" id="sc-combo-trigger" tabindex="0"
                 role="combobox" aria-haspopup="listbox" aria-expanded="false" aria-controls="sc-combo-panel">
                <span class="sc-combo-placeholder<?php echo $saved_name ? ' has-value' : ''; ?>">
                    <?php echo $saved_name ? esc_html( $saved_name ) : esc_html__( '-- Select delivery area --', 'space-core' ); ?>
                </span>
                <span class="sc-combo-arrow dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
            </div>

            <div class="sc-combo-panel" id="sc-combo-panel" role="listbox" style="display:none;">
                <div class="sc-combo-search-wrap">
                    <span class="dashicons dashicons-search" aria-hidden="true"></span>
                    <input type="text" class="sc-combo-search"
                           placeholder="<?php esc_attr_e( 'Search for an area...', 'space-core' ); ?>"
                           autocomplete="off">
                </div>
                <div class="sc-combo-list">
                    <?php foreach ( $grouped as $cid => $group ) :
                        $city_name = AreasDB::resolve_name( AreasDB::decode_name( $group['city']['name'] ) );
                        ?>
                        <div class="sc-combo-group" data-city="<?php echo esc_attr( $cid ); ?>">
                            <div class="sc-combo-group-header">
                                <span><?php echo esc_html( $city_name ); ?></span>
                            </div>
                            <?php foreach ( $group['areas'] as $area ) :
                                $area_name = AreasDB::resolve_name( AreasDB::decode_name( $area['name'] ) );
                                $is_selected = ( $saved_area === (int) $area['id'] );
                                ?>
                                <div class="sc-combo-item<?php echo $is_selected ? ' sc-selected' : ''; ?>"
                                     role="option"
                                     aria-selected="<?php echo $is_selected ? 'true' : 'false'; ?>"
                                     data-value="<?php echo esc_attr( $area['id'] ); ?>"
                                     data-city="<?php echo esc_attr( $cid ); ?>"
                                     data-price="<?php echo esc_attr( $area['delivery_price'] ); ?>"
                                     data-express="<?php echo esc_attr( $area['express_fee'] ); ?>"
                                     data-minimum="<?php echo esc_attr( $area['minimum_order'] ); ?>"
                                     data-freeminimum="<?php echo esc_attr( $area['free_minimum_order'] ); ?>"
                                     data-name="<?php echo esc_attr( $area_name ); ?>">
                                    <span class="sc-item-name"><?php echo esc_html( $area_name ); ?></span>
                                    <span class="sc-item-price">
                                        <?php echo esc_html( $this->format_area_price_label( $area, $currency ) ); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="sc-combo-no-results" style="display:none;">
                    <?php esc_html_e( 'No results match your search', 'space-core' ); ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // =========================================================================
    // Checkout — combo field renderer
    // =========================================================================

    /**
     * Static price label shown in the combo list (PHP-side, before JS overrides based on cart total).
     */
    private function format_area_price_label( array $area, string $currency ): string {
        $price    = (float) $area['delivery_price'];
        $free_min = (float) $area['free_minimum_order'];

        if ( $price <= 0 && $free_min <= 0 ) {
            return __( 'Free', 'space-core' );
        }

        if ( $free_min > 0 ) {
            return sprintf(
            /* translators: %s: formatted minimum order amount */
                    __( 'Free on orders over %s', 'space-core' ),
                    number_format( $free_min, 3 ) . $currency
            );
        }

        return number_format( $price, 3 ) . $currency;
    }

    public function enqueue_frontend_assets(): void {
        if ( ! is_checkout() ) {
            return;
        }

        $opts            = get_option( 'space_core_local_shipping', [] );
        $express_enabled = ! empty( $opts['express_enabled'] );
        $currency        = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '';
        $cart_subtotal   = WC()->cart ? (float) WC()->cart->get_subtotal() : 0.0;

        wp_enqueue_style(
                'sc-local-shipping',
                SPACE_CORE_URL . 'assets/css/local-shipping.css',
                [ 'dashicons' ],
                SPACE_CORE_VERSION
        );

        wp_enqueue_script(
                'sc-local-shipping',
                SPACE_CORE_URL . 'assets/js/local-shipping.js',
                [ 'jquery', 'wc-checkout' ],
                SPACE_CORE_VERSION,
                true
        );

        wp_localize_script( 'sc-local-shipping', 'scLocalShipping', [
                'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
                'nonce'          => wp_create_nonce( 'sc_checkout_nonce' ),
                'expressEnabled' => $express_enabled,
                'cartSubtotal'   => $cart_subtotal,
                'currencySymbol' => $currency,
                'savedAreaId'    => WC()->session ? (int) WC()->session->get( self::SESSION_AREA_ID, 0 ) : 0,
                'savedType'      => WC()->session ? (string) WC()->session->get( self::SESSION_DELIVERY_TYPE, 'normal' ) : 'normal',
                'strings'        => [
                        'selectArea' => __( '-- Select delivery area --', 'space-core' ),
                        'noResults'  => __( 'No results match your search', 'space-core' ),
                        'free'       => __( 'Free', 'space-core' ),
                    /* translators: %s: formatted minimum order price */
                        'freeOver'   => __( 'Free on orders over %s', 'space-core' ),
                    /* translators: %s: formatted minimum order price */
                        'minOrder'   => __( 'Min. order: %s', 'space-core' ),
                        'required'   => __( 'Delivery area is a required field.', 'space-core' ),
                        'standard'   => __( 'Standard Delivery', 'space-core' ),
                        'express'    => __( 'Express Delivery', 'space-core' ),
                ],
        ] );
    }

    // =========================================================================
    // Frontend assets
    // =========================================================================

    public function validate_fields(): void {
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $area_id       = isset( $_POST['billing_sc_area_id'] ) ? absint( $_POST['billing_sc_area_id'] ) : 0;
        $delivery_type = isset( $_POST['billing_sc_delivery_type'] ) ? sanitize_key( wp_unslash( $_POST['billing_sc_delivery_type'] ) ) : 'normal';
        // phpcs:enable

        if ( ! $area_id ) {
            wc_add_notice(
                    '<strong>' . esc_html__( 'Delivery Area', 'space-core' ) . '</strong> ' .
                    esc_html__( 'is a required field.', 'space-core' ),
                    'error'
            );

            return;
        }

        $area = AreasDB::get_area( $area_id );

        if ( ! $area ) {
            wc_add_notice( esc_html__( 'The selected delivery area was not found.', 'space-core' ), 'error' );

            return;
        }

        if ( ! (int) $area['is_active'] ) {
            wc_add_notice(
                    sprintf(
                            esc_html__( 'Area %s is currently unavailable for delivery.', 'space-core' ),
                            '<strong>' . esc_html( AreasDB::resolve_name( AreasDB::decode_name( $area['name'] ) ) ) . '</strong>'
                    ),
                    'error'
            );

            return;
        }

        $min_order = (float) $area['minimum_order'];
        if ( $min_order > 0 ) {
            $subtotal = WC()->cart ? (float) WC()->cart->get_subtotal() : 0.0;
            if ( $subtotal < $min_order ) {
                wc_add_notice(
                        sprintf(
                                esc_html__( 'A minimum order of %2$s is required to deliver to %1$s.', 'space-core' ),
                                '<strong>' . esc_html( AreasDB::resolve_name( AreasDB::decode_name( $area['name'] ) ) ) . '</strong>',
                                '<strong>' . wc_price( $min_order ) . '</strong>'
                        ),
                        'error'
                );

                return;
            }
        }

        // Fallback express → normal if no express fee.
        if ( 'express' === $delivery_type && (float) $area['express_fee'] <= 0 ) {
            $_POST['billing_sc_delivery_type'] = 'normal'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        }
    }

    // =========================================================================
    // Checkout — validation, fee, session, meta
    // =========================================================================

    public function apply_delivery_fee( WC_Cart $cart ): void {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }
        if ( ! WC()->session ) {
            return;
        }

        $area_id       = (int) WC()->session->get( self::SESSION_AREA_ID, 0 );
        $delivery_type = (string) WC()->session->get( self::SESSION_DELIVERY_TYPE, 'normal' );
        $fee_data      = $this->calculate_fee( $area_id, $delivery_type );

        if ( $fee_data && $fee_data['amount'] > 0 ) {
            $converted_amount = (float) apply_filters( 'sc_local_shipping_fee', (float) $fee_data['amount'] );
            $cart->add_fee( $fee_data['label'], $converted_amount, false );
        }
    }

    private function calculate_fee( int $area_id, string $delivery_type ): ?array {
        if ( ! $area_id ) {
            return null;
        }
        $area = AreasDB::get_area( $area_id );
        if ( ! $area || ! (int) $area['is_active'] ) {
            return null;
        }

        $price       = (float) $area['delivery_price'];
        $express_fee = (float) $area['express_fee'];
        $free_min    = (float) $area['free_minimum_order'];
        $area_name   = AreasDB::resolve_name( AreasDB::decode_name( $area['name'] ) );

        // Free delivery threshold.
        if ( $free_min > 0 && WC()->cart ) {
            $subtotal = (float) WC()->cart->get_subtotal();
            if ( $subtotal >= $free_min ) {
                $price       = 0.0;
                $express_fee = 0.0;
            }
        }

        if ( 'express' === $delivery_type && $express_fee > 0 ) {
            return [
                /* translators: %s: area name */
                    'label'  => sprintf( __( 'Express Delivery — %s', 'space-core' ), $area_name ),
                    'amount' => round( $price + $express_fee, 3 ),
            ];
        }

        return [
            /* translators: %s: area name */
                'label'  => sprintf( __( 'Delivery — %s', 'space-core' ), $area_name ),
                'amount' => $price,
        ];
    }

    public function ajax_set_session(): void {
        if ( ! check_ajax_referer( 'sc_checkout_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Security check failed.', 'space-core' ) ], 403 );
        }

        $area_id       = isset( $_POST['area_id'] ) ? absint( $_POST['area_id'] ) : 0;
        $city_id       = isset( $_POST['city_id'] ) ? absint( $_POST['city_id'] ) : 0;
        $delivery_type = isset( $_POST['delivery_type'] ) ? sanitize_key( wp_unslash( $_POST['delivery_type'] ) ) : 'normal';

        if ( ! in_array( $delivery_type, [ 'normal', 'express' ], true ) ) {
            $delivery_type = 'normal';
        }

        if ( WC()->session ) {
            WC()->session->set( self::SESSION_CITY_ID, $city_id );
            WC()->session->set( self::SESSION_AREA_ID, $area_id );
            WC()->session->set( self::SESSION_DELIVERY_TYPE, $delivery_type );
        }

        $fee_data = $this->calculate_fee( $area_id, $delivery_type );

        wp_send_json_success( [
                'area_id'       => $area_id,
                'delivery_type' => $delivery_type,
                'fee_amount'    => $fee_data ? $fee_data['amount'] : 0,
                'fee_label'     => $fee_data ? $fee_data['label'] : '',
        ] );
    }

    public function save_order_meta( int $order_id ): void {
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $area_id       = isset( $_POST['billing_sc_area_id'] ) ? absint( $_POST['billing_sc_area_id'] ) : 0;
        $delivery_type = isset( $_POST['billing_sc_delivery_type'] ) ? sanitize_key( wp_unslash( $_POST['billing_sc_delivery_type'] ) ) : 'normal';
        // phpcs:enable

        if ( $area_id ) {
            $area = AreasDB::get_area( $area_id );
            if ( $area ) {
                $area_name = AreasDB::resolve_name( AreasDB::decode_name( $area['name'] ) );
                $city      = AreasDB::get_city( (int) $area['city_id'] );
                $city_name = $city ? AreasDB::resolve_name( AreasDB::decode_name( $city['name'] ) ) : '';
                $fee_data  = $this->calculate_fee( $area_id, $delivery_type );

                update_post_meta( $order_id, self::META_CITY_NAME, $city_name );
                update_post_meta( $order_id, self::META_AREA_NAME, $area_name );
                update_post_meta( $order_id, self::META_DELIVERY_TYPE, $delivery_type );
                update_post_meta( $order_id, self::META_DELIVERY_PRICE, $fee_data ? $fee_data['amount'] : 0 );
            }
        }

        $this->clear_session();
    }

    public function clear_session(): void {
        if ( WC()->session ) {
            WC()->session->__unset( self::SESSION_CITY_ID );
            WC()->session->__unset( self::SESSION_AREA_ID );
            WC()->session->__unset( self::SESSION_DELIVERY_TYPE );
        }
    }

    public function display_order_delivery( WC_Order $order ): void {
        $data = $this->get_order_delivery_data( $order );
        if ( ! $data ) {
            return;
        }
        $type_label = 'express' === $data['type']
                ? __( 'Express Delivery', 'space-core' )
                : __( 'Standard Delivery', 'space-core' );
        $currency   = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '';
        ?>
        <section class="sc-order-delivery woocommerce-order-details" style="margin-bottom:24px;">
            <h2 class="woocommerce-order-details__title" style="font-size:18px;margin-bottom:12px;">
                <?php esc_html_e( 'Delivery Details', 'space-core' ); ?>
            </h2>
            <table class="woocommerce-table shop_table" style="width:100%;">
                <tbody>
                <?php if ( $data['city'] ) : ?>
                    <tr>
                        <th style="padding:8px 12px;"><?php esc_html_e( 'City', 'space-core' ); ?></th>
                        <td style="padding:8px 12px;"><?php echo esc_html( $data['city'] ); ?></td>
                    </tr>
                <?php endif; ?>
                <?php if ( $data['area'] ) : ?>
                    <tr>
                        <th style="padding:8px 12px;"><?php esc_html_e( 'Area', 'space-core' ); ?></th>
                        <td style="padding:8px 12px;"><?php echo esc_html( $data['area'] ); ?></td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <th style="padding:8px 12px;"><?php esc_html_e( 'Delivery Type', 'space-core' ); ?></th>
                    <td style="padding:8px 12px;"><?php echo esc_html( $type_label ); ?></td>
                </tr>
                <?php if ( $data['price'] ) : ?>
                    <tr>
                        <th style="padding:8px 12px;"><?php esc_html_e( 'Delivery Fee', 'space-core' ); ?></th>
                        <td style="padding:8px 12px;font-weight:600;">
                            <?php echo esc_html( number_format( (float) $data['price'], 3 ) . $currency ); ?>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </section>
        <?php
    }

    // =========================================================================
    // Display in order views
    // =========================================================================

    private function get_order_delivery_data( WC_Order $order ): ?array {
        $city  = get_post_meta( $order->get_id(), self::META_CITY_NAME, true );
        $area  = get_post_meta( $order->get_id(), self::META_AREA_NAME, true );
        $type  = get_post_meta( $order->get_id(), self::META_DELIVERY_TYPE, true );
        $price = get_post_meta( $order->get_id(), self::META_DELIVERY_PRICE, true );

        if ( ! $city && ! $area ) {
            return null;
        }

        return compact( 'city', 'area', 'type', 'price' );
    }

    public function display_email_delivery( WC_Order $order, bool $sent_to_admin ): void {
        $data = $this->get_order_delivery_data( $order );
        if ( ! $data ) {
            return;
        }
        $type_label = 'express' === $data['type']
                ? __( 'Express Delivery', 'space-core' )
                : __( 'Standard Delivery', 'space-core' );
        $currency   = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '';
        ?>
        <div style="margin-bottom:24px;font-family:Arial,sans-serif;">
            <h2 style="font-size:18px;color:#333;border-bottom:2px solid #e5e5e5;padding-bottom:8px;">
                <?php esc_html_e( 'Delivery Details', 'space-core' ); ?>
            </h2>
            <table style="width:100%;border-collapse:collapse;">
                <?php if ( $data['city'] ) : ?>
                    <tr>
                        <td style="padding:8px 12px;border-bottom:1px solid #f0f0f0;font-weight:bold;width:40%;">
                            <?php esc_html_e( 'City', 'space-core' ); ?>
                        </td>
                        <td style="padding:8px 12px;border-bottom:1px solid #f0f0f0;">
                            <?php echo esc_html( $data['city'] ); ?>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php if ( $data['area'] ) : ?>
                    <tr>
                        <td style="padding:8px 12px;border-bottom:1px solid #f0f0f0;font-weight:bold;width:40%;">
                            <?php esc_html_e( 'Area', 'space-core' ); ?>
                        </td>
                        <td style="padding:8px 12px;border-bottom:1px solid #f0f0f0;">
                            <?php echo esc_html( $data['area'] ); ?>
                        </td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <td style="padding:8px 12px;border-bottom:1px solid #f0f0f0;font-weight:bold;width:40%;">
                        <?php esc_html_e( 'Delivery Type', 'space-core' ); ?>
                    </td>
                    <td style="padding:8px 12px;border-bottom:1px solid #f0f0f0;">
                        <?php echo esc_html( $type_label ); ?>
                    </td>
                </tr>
                <?php if ( $data['price'] ) : ?>
                    <tr>
                        <td style="padding:8px 12px;font-weight:bold;width:40%;">
                            <?php esc_html_e( 'Delivery Fee', 'space-core' ); ?>
                        </td>
                        <td style="padding:8px 12px;font-weight:600;">
                            <?php echo esc_html( number_format( (float) $data['price'], 3 ) . $currency ); ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>
        <?php
    }

    public function display_admin_delivery( WC_Order $order ): void {
        $data = $this->get_order_delivery_data( $order );
        if ( ! $data ) {
            return;
        }
        $type_label = 'express' === $data['type']
                ? __( 'Express Delivery', 'space-core' )
                : __( 'Standard Delivery', 'space-core' );
        $currency   = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '';
        ?>
        <div class="sc-admin-delivery"
             style="margin-top:12px;padding:10px;background:#f9f9f9;border:1px solid #e5e5e5;border-radius:4px;">
            <strong><?php esc_html_e( 'Delivery Details', 'space-core' ); ?></strong><br>
            <?php if ( $data['city'] ) : ?>
                <span><?php esc_html_e( 'City:', 'space-core' ); ?> <strong><?php echo esc_html( $data['city'] ); ?></strong></span>
                <br>
            <?php endif; ?>
            <?php if ( $data['area'] ) : ?>
                <span><?php esc_html_e( 'Area:', 'space-core' ); ?> <strong><?php echo esc_html( $data['area'] ); ?></strong></span>
                <br>
            <?php endif; ?>
            <span><?php esc_html_e( 'Type:', 'space-core' ); ?> <strong><?php echo esc_html( $type_label ); ?></strong></span><br>
            <?php if ( $data['price'] ) : ?>
                <span><?php esc_html_e( 'Fee:', 'space-core' ); ?> <strong><?php echo esc_html( number_format( (float) $data['price'], 3 ) . $currency ); ?></strong></span>
            <?php endif; ?>
        </div>
        <?php
    }

    public function render_settings(): void {
        echo '<p>' . esc_html__( 'Manage Fixed Shipping by City from WooCommerce → Fixed Shipping by City.', 'space-core' ) . '</p>';
        echo '<a href="' . esc_url( admin_url( 'admin.php?page=sc-local-shipping' ) ) . '" class="button">'
             . esc_html__( 'Go to Fixed Shipping by City Settings', 'space-core' ) . '</a>';
    }

    // =========================================================================
    // Unused — settings rendered as submenu page, not in main Space Core tabs
    // =========================================================================

    private function city_options_html( array $cities, int $selected_city_id ): string {
        if ( empty( $cities ) ) {
            return '<option value="">' . esc_html__( '— No cities —', 'space-core' ) . '</option>';
        }

        $html = '';
        foreach ( $cities as $city ) {
            $city_id = (int) $city['id'];
            $html    .= '<option value="' . esc_attr( $city_id ) . '"' . selected( $selected_city_id, $city_id, false ) . '>' . esc_html( $this->city_display_name( $city ) ) . '</option>';
        }

        return $html;
    }
}
