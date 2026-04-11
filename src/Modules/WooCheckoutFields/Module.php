<?php

namespace Space\Core\Modules\WooCheckoutFields;

defined( 'ABSPATH' ) || exit;

use Space\Core\Abstracts\AbstractModule;
use WC_Order;

class Module extends AbstractModule {

    public function get_label(): string {
        return __( 'Checkout Fields', 'space-core' );
    }

    public function get_description(): string {
        return __( 'Manage WooCommerce checkout fields: add, edit, reorder, enable/disable, or remove.', 'space-core' );
    }

    public function boot(): void {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        add_action( 'wp_ajax_sc_save_checkout_fields', [ $this, 'ajax_save' ] );
        add_action( 'wp_ajax_sc_reset_checkout_fields', [ $this, 'ajax_reset' ] );
        add_filter( 'woocommerce_checkout_fields', [ $this, 'modify_checkout_fields' ] );
        add_action( 'woocommerce_checkout_update_order_meta', [ $this, 'save_custom_fields' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_conditional_script' ] );

        // Display custom fields in order details (admin + frontend + emails).
        add_action( 'woocommerce_admin_order_data_after_billing_address', [ $this, 'display_in_admin_order' ] );
        add_filter( 'woocommerce_order_details_after_order_table', [ $this, 'display_in_order_details' ] );
        add_action( 'woocommerce_email_order_meta', [ $this, 'display_in_order_email' ], 10, 3 );
    }

    // ── Default WC fields catalogue ───────────────────────────────

    public function modify_checkout_fields( array $fields ): array {
        $config = $this->get_config();
        if ( empty( $config ) && ! $this->is_local_shipping_active() ) {
            return $fields;
        }

        $list = $this->build_field_list();

        // Customer's current billing country (used for show_countries conditional).
        $customer_country = '';
        if ( function_exists( 'WC' ) && WC()->customer ) {
            $customer_country = strtoupper( (string) WC()->customer->get_billing_country() );
        }

        foreach ( $list as $section => $section_fields ) {
            foreach ( $section_fields as $key => $field ) {

                // ---- Local Shipping virtual field: only apply priority. ----
                if ( ! empty( $field['local_shipping'] ) ) {
                    if ( isset( $fields[ $section ][ $key ] ) ) {
                        $fields[ $section ][ $key ]['priority'] = $field['priority'];
                    }
                    continue;
                }

                // ---- show_countries conditional display. ----
                // On form SUBMIT: remove the field entirely so WC skips validation.
                // On page RENDER / AJAX order-review: keep the field in the HTML so JS
                // can show/hide it without needing a full page reload.
                $show_countries = trim( $field['show_countries'] ?? '' );
                if ( $show_countries && $customer_country ) {
                    $allowed = array_values( array_filter(
                        array_map( 'strtoupper', array_map( 'trim', explode( ',', $show_countries ) ) )
                    ) );
                    if ( $allowed && ! in_array( $customer_country, $allowed, true ) ) {
                        if ( $this->is_checkout_submit() ) {
                            // Remove during submit so WC doesn't validate it.
                            unset( $fields[ $section ][ $key ] );
                            continue;
                        }
                        // During render: keep in HTML but force non-required so WC's
                        // client-side validation ignores it when it is hidden.
                        $field['required'] = false;
                    }
                }

                if ( ! $field['enabled'] ) {
                    unset( $fields[ $section ][ $key ] );
                    continue;
                }

                $width_class = 'form-row-' . ( $field['width'] ?? 'wide' );
                if ( $field['custom'] ) {
                    $fields[ $section ][ $key ] = [
                        'label'    => $field['label'],
                        'type'     => $field['type'],
                        'required' => $field['required'],
                        'priority' => $field['priority'],
                        'class'    => [ $width_class ],
                    ];
                } else {
                    // Merge overrides into existing WC field.
                    if ( isset( $fields[ $section ][ $key ] ) ) {
                        $fields[ $section ][ $key ]['label']    = $field['label'];
                        $fields[ $section ][ $key ]['required'] = $field['required'];
                        $fields[ $section ][ $key ]['priority'] = $field['priority'];
                        if ( ! empty( $field['width'] ) ) {
                            $existing = (array) ( $fields[ $section ][ $key ]['class'] ?? [] );
                            $existing = array_values( array_filter( $existing, fn( $c ) => ! in_array( $c, [ 'form-row-wide', 'form-row-first', 'form-row-last' ], true ) ) );
                            $existing[] = $width_class;
                            $fields[ $section ][ $key ]['class'] = $existing;
                        }
                    }
                }
            }
        }

        return $fields;
    }

    // ── Helpers ───────────────────────────────────────────────────

    /**
     * True only during the actual checkout form POST / AJAX submit.
     * Used to distinguish rendering (keep hidden fields in HTML) from
     * validation (remove fields that don't match country).
     */
    private function is_checkout_submit(): bool {
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            return ( $_REQUEST['wc-ajax'] ?? '' ) === 'checkout';
        }
        return isset( $_POST['woocommerce-process-checkout-nonce'] );
    }

    // ── Local Shipping integration ────────────────────────────────

    private function is_local_shipping_active(): bool {
        $modules = get_option( 'space_core_modules', [] );
        return ! empty( $modules['local_shipping'] );
    }

    // ── Saved config ──────────────────────────────────────────────

    private function get_config(): array {
        $raw = get_option( 'space_core_woo_checkout_fields', '' );
        if ( empty( $raw ) ) {
            return [];
        }
        $cfg = json_decode( $raw, true );

        return is_array( $cfg ) ? $cfg : [];
    }

    /**
     * Merge saved config over WC defaults to build final field list per section.
     * Returns: [ 'billing' => [ key => [ ...field_data ] ], ... ]
     */
    private function build_field_list(): array {
        $defaults = $this->wc_default_fields();
        $config   = $this->get_config();

        // Start with defaults, mark them as "default" type.
        $result = [];
        foreach ( $defaults as $section => $fields ) {
            foreach ( $fields as $key => $field ) {
                $result[ $section ][ $key ] = array_merge( $field, [
                        'key'            => $key,
                        'section'        => $section,
                        'is_default'     => true,
                        'enabled'        => true,
                        'priority'       => $field['priority'] ?? 10,
                        'width'          => 'wide',
                        'custom'         => false,
                        'show_countries' => '',
                ] );
            }
        }

        // Apply saved overrides.
        foreach ( $config as $entry ) {
            $section = $entry['section'] ?? 'billing';
            $key     = $entry['key'] ?? '';
            if ( empty( $key ) ) {
                continue;
            }

            // Local Shipping virtual field — only update its priority.
            if ( ! empty( $entry['local_shipping'] ) ) {
                if ( isset( $result[ $section ][ $key ] ) ) {
                    $result[ $section ][ $key ]['priority'] = (int) ( $entry['priority'] ?? 200 );
                }
                continue;
            }

            if ( isset( $result[ $section ][ $key ] ) ) {
                // Override existing default field.
                $result[ $section ][ $key ] = array_merge( $result[ $section ][ $key ], [
                        'label'          => $entry['label'] ?? $result[ $section ][ $key ]['label'],
                        'required'       => (bool) ( $entry['required'] ?? $result[ $section ][ $key ]['required'] ),
                        'enabled'        => (bool) ( $entry['enabled'] ?? true ),
                        'priority'       => (int) ( $entry['priority'] ?? 10 ),
                        'width'          => in_array( $entry['width'] ?? 'wide', [ 'wide', 'first', 'last' ], true ) ? $entry['width'] : 'wide',
                        'show_countries' => sanitize_text_field( $entry['show_countries'] ?? '' ),
                ] );
            } elseif ( ! empty( $entry['custom'] ) ) {
                // New custom field.
                $result[ $section ][ $key ] = [
                        'key'            => $key,
                        'section'        => $section,
                        'label'          => sanitize_text_field( $entry['label'] ?? $key ),
                        'type'           => sanitize_key( $entry['type'] ?? 'text' ),
                        'required'       => (bool) ( $entry['required'] ?? false ),
                        'enabled'        => (bool) ( $entry['enabled'] ?? true ),
                        'priority'       => (int) ( $entry['priority'] ?? 100 ),
                        'width'          => in_array( $entry['width'] ?? 'wide', [ 'wide', 'first', 'last' ], true ) ? $entry['width'] : 'wide',
                        'is_default'     => false,
                        'custom'         => true,
                        'show_countries' => sanitize_text_field( $entry['show_countries'] ?? '' ),
                ];
            }
        }

        // Inject Local Shipping's billing_sc_area as a sortable-only virtual field.
        if ( $this->is_local_shipping_active() ) {
            $ls_priority = 200;
            foreach ( $config as $entry ) {
                if ( ( $entry['key'] ?? '' ) === 'billing_sc_area'
                    && ( $entry['section'] ?? 'billing' ) === 'billing'
                    && ! empty( $entry['local_shipping'] ) ) {
                    $ls_priority = (int) ( $entry['priority'] ?? 200 );
                    break;
                }
            }
            $result['billing']['billing_sc_area'] = [
                'key'            => 'billing_sc_area',
                'section'        => 'billing',
                'label'          => __( 'Delivery Area', 'space-core' ),
                'type'           => 'combo',
                'required'       => false,
                'enabled'        => true,
                'priority'       => $ls_priority,
                'width'          => 'wide',
                'is_default'     => false,
                'custom'         => false,
                'local_shipping' => true,
                'show_countries' => '',
            ];
        }

        // Sort each section by priority.
        foreach ( $result as $section => &$fields ) {
            uasort( $fields, fn( $a, $b ) => ( $a['priority'] ?? 10 ) <=> ( $b['priority'] ?? 10 ) );
        }

        return $result;
    }

    // ── WC filter ────────────────────────────────────────────────

    /**
     * Returns the WooCommerce default field definitions so we can list them
     * even before checkout is rendered.
     */
    private function wc_default_fields(): array {
        return [
                'billing'  => [
                        'billing_first_name' => [
                                'label'    => __( 'First name', 'woocommerce' ),
                                'type'     => 'text',
                                'required' => true
                        ],
                        'billing_last_name'  => [
                                'label'    => __( 'Last name', 'woocommerce' ),
                                'type'     => 'text',
                                'required' => true
                        ],
                        'billing_company'    => [
                                'label'    => __( 'Company name', 'woocommerce' ),
                                'type'     => 'text',
                                'required' => false
                        ],
                        'billing_country'    => [
                                'label'    => __( 'Country / Region', 'woocommerce' ),
                                'type'     => 'country',
                                'required' => true
                        ],
                        'billing_address_1'  => [
                                'label'    => __( 'Street address', 'woocommerce' ),
                                'type'     => 'text',
                                'required' => true
                        ],
                        'billing_address_2'  => [
                                'label'    => __( 'Apartment, suite, etc.', 'woocommerce' ),
                                'type'     => 'text',
                                'required' => false
                        ],
                        'billing_city'       => [
                                'label'    => __( 'Town / City', 'woocommerce' ),
                                'type'     => 'text',
                                'required' => true
                        ],
                        'billing_state'      => [
                                'label'    => __( 'State / County', 'woocommerce' ),
                                'type'     => 'state',
                                'required' => true
                        ],
                        'billing_postcode'   => [
                                'label'    => __( 'Postcode / ZIP', 'woocommerce' ),
                                'type'     => 'text',
                                'required' => true
                        ],
                        'billing_phone'      => [
                                'label'    => __( 'Phone', 'woocommerce' ),
                                'type'     => 'tel',
                                'required' => true
                        ],
                        'billing_email'      => [
                                'label'    => __( 'Email address', 'woocommerce' ),
                                'type'     => 'email',
                                'required' => true
                        ],
                ],
                'shipping' => [
                        'shipping_first_name' => [
                                'label'    => __( 'First name', 'woocommerce' ),
                                'type'     => 'text',
                                'required' => false
                        ],
                        'shipping_last_name'  => [
                                'label'    => __( 'Last name', 'woocommerce' ),
                                'type'     => 'text',
                                'required' => false
                        ],
                        'shipping_company'    => [
                                'label'    => __( 'Company name', 'woocommerce' ),
                                'type'     => 'text',
                                'required' => false
                        ],
                        'shipping_country'    => [
                                'label'    => __( 'Country / Region', 'woocommerce' ),
                                'type'     => 'country',
                                'required' => false
                        ],
                        'shipping_address_1'  => [
                                'label'    => __( 'Street address', 'woocommerce' ),
                                'type'     => 'text',
                                'required' => false
                        ],
                        'shipping_address_2'  => [
                                'label'    => __( 'Apartment, suite, etc.', 'woocommerce' ),
                                'type'     => 'text',
                                'required' => false
                        ],
                        'shipping_city'       => [
                                'label'    => __( 'Town / City', 'woocommerce' ),
                                'type'     => 'text',
                                'required' => false
                        ],
                        'shipping_state'      => [
                                'label'    => __( 'State / County', 'woocommerce' ),
                                'type'     => 'state',
                                'required' => false
                        ],
                        'shipping_postcode'   => [
                                'label'    => __( 'Postcode / ZIP', 'woocommerce' ),
                                'type'     => 'text',
                                'required' => false
                        ],
                ],
                'order'    => [
                        'order_comments' => [
                                'label'    => __( 'Order notes', 'woocommerce' ),
                                'type'     => 'textarea',
                                'required' => false
                        ],
                ],
        ];
    }

    public function save_custom_fields( int $order_id ): void {
        foreach ( $this->get_config() as $entry ) {
            if ( empty( $entry['custom'] ) ) {
                continue;
            }
            $key = sanitize_key( $entry['key'] ?? '' );
            if ( $key && isset( $_POST[ $key ] ) ) {
                update_post_meta( $order_id, '_' . $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
            }
        }
    }

    // ── Order details display ─────────────────────────────────────

    /**
     * Display custom fields in the WooCommerce admin order edit page
     * (below the billing address block).
     */
    public function display_in_admin_order( WC_Order $order ): void {
        $labels = $this->custom_field_labels();
        if ( empty( $labels ) ) {
            return;
        }

        $has_values = false;
        $rows       = '';
        foreach ( $labels as $key => $label ) {
            $value = $order->get_meta( '_' . $key );
            if ( '' === $value || null === $value ) {
                continue;
            }
            $has_values = true;
            $rows       .= '<p><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $value ) . '</p>';
        }

        if ( $has_values ) {
            echo '<div class="sc-order-custom-fields"><h4>' . esc_html__( 'Additional Info', 'space-core' ) . '</h4>' . $rows . '</div>';
        }
    }

    /**
     * Returns [ key => label ] for all enabled custom fields.
     */
    private function custom_field_labels(): array {
        $labels = [];
        foreach ( $this->get_config() as $entry ) {
            if ( empty( $entry['custom'] ) || empty( $entry['enabled'] ) ) {
                continue;
            }
            $key = sanitize_key( $entry['key'] ?? '' );
            if ( $key ) {
                $labels[ $key ] = sanitize_text_field( $entry['label'] ?? $key );
            }
        }

        return $labels;
    }

    /**
     * Display custom fields on the customer-facing order details page
     * (My Account → Orders → View Order) and Thank You page.
     */
    public function display_in_order_details( WC_Order $order ): void {
        $labels = $this->custom_field_labels();
        if ( empty( $labels ) ) {
            return;
        }

        $rows = '';
        foreach ( $labels as $key => $label ) {
            $value = $order->get_meta( '_' . $key );
            if ( '' === $value || null === $value ) {
                continue;
            }
            $rows .= '<tr><th>' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
        }

        if ( $rows ) {
            echo '<h2 class="woocommerce-column__title">' . esc_html__( 'Additional Information', 'space-core' ) . '</h2>';
            echo '<table class="woocommerce-table shop_table sc-order-extra-fields"><tbody>' . $rows . '</tbody></table>';
        }
    }

    /**
     * Append custom fields to WooCommerce order notification emails.
     *
     * @param WC_Order $order
     * @param bool $sent_to_admin
     * @param bool $plain_text
     */
    public function display_in_order_email( WC_Order $order, bool $sent_to_admin, bool $plain_text ): void {
        $labels = $this->custom_field_labels();
        if ( empty( $labels ) ) {
            return;
        }

        $pairs = [];
        foreach ( $labels as $key => $label ) {
            $value = $order->get_meta( '_' . $key );
            if ( '' === $value || null === $value ) {
                continue;
            }
            $pairs[] = [ 'label' => $label, 'value' => $value ];
        }

        if ( empty( $pairs ) ) {
            return;
        }

        if ( $plain_text ) {
            echo "\n" . esc_html__( 'Additional Information', 'space-core' ) . "\n";
            echo str_repeat( '-', 30 ) . "\n";
            foreach ( $pairs as $pair ) {
                echo esc_html( $pair['label'] ) . ': ' . esc_html( $pair['value'] ) . "\n";
            }
        } else {
            echo '<h2>' . esc_html__( 'Additional Information', 'space-core' ) . '</h2>';
            echo '<table cellspacing="0" cellpadding="6" border="1" style="width:100%;border-collapse:collapse;">';
            foreach ( $pairs as $pair ) {
                echo '<tr><th style="text-align:left;padding:8px;background:#f8f8f8;">' . esc_html( $pair['label'] ) . '</th>';
                echo '<td style="padding:8px;">' . esc_html( $pair['value'] ) . '</td></tr>';
            }
            echo '</table>';
        }
    }

    // ── Frontend conditional field visibility ─────────────────────

    public function enqueue_conditional_script(): void {
        if ( ! is_checkout() ) {
            return;
        }

        $conditional = [];
        foreach ( $this->get_config() as $entry ) {
            $show_countries = trim( $entry['show_countries'] ?? '' );
            if ( ! $show_countries || empty( $entry['key'] ) ) {
                continue;
            }
            $key     = sanitize_key( $entry['key'] );
            $allowed = array_values( array_filter(
                array_map( 'strtoupper', array_map( 'trim', explode( ',', $show_countries ) ) )
            ) );
            if ( $allowed ) {
                $conditional[ $key ] = $allowed;
            }
        }

        if ( empty( $conditional ) ) {
            return;
        }

        $json   = wp_json_encode( $conditional );
        $script = sprintf(
            '(function($){
                var scWcfConditional = %s;
                function scWcfApply(country) {
                    country = (country || "").toUpperCase();
                    $.each(scWcfConditional, function(fieldKey, allowed) {
                        var $f = $("#" + fieldKey + "_field");
                        if (!$f.length) return;
                        var visible = !country || allowed.indexOf(country) !== -1;
                        // Use toggle() so jQuery sets inline display:none / removes it.
                        // WC checkout.js skips required validation on :hidden elements.
                        $f.toggle(visible);
                    });
                }
                $(function() {
                    // Apply on initial load (fields are always in HTML; hide wrong-country ones).
                    scWcfApply($("#billing_country").val());
                    // Re-apply immediately on country change.
                    $(document).on("change", "#billing_country", function() { scWcfApply($(this).val()); });
                    // Re-apply after WC replaces checkout fragments — always use fresh selector.
                    $(document.body).on("updated_checkout", function() {
                        scWcfApply($("#billing_country").val());
                    });
                });
            }(jQuery));',
            $json
        );

        wp_add_inline_script( 'wc-checkout', $script );
    }

    // ── AJAX ─────────────────────────────────────────────────────

    public function ajax_save(): void {
        check_ajax_referer( 'sc_wcf_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'space-core' ) ] );
        }

        $rows = json_decode( stripslashes( $_POST['rows'] ?? '[]' ), true );
        if ( ! is_array( $rows ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid data.', 'space-core' ) ] );
        }

        $clean = [];
        foreach ( $rows as $row ) {
            $key     = sanitize_key( $row['key'] ?? '' );
            $section = in_array( $row['section'] ?? '', [
                    'billing',
                    'shipping',
                    'order'
            ], true ) ? $row['section'] : 'billing';
            if ( empty( $key ) ) {
                continue;
            }

            // Local Shipping virtual field — save only priority.
            if ( ! empty( $row['local_shipping'] ) ) {
                $clean[] = [
                    'key'            => 'billing_sc_area',
                    'section'        => 'billing',
                    'priority'       => absint( $row['priority'] ?? 200 ),
                    'local_shipping' => true,
                ];
                continue;
            }

            $width = in_array( $row['width'] ?? 'wide', [ 'wide', 'first', 'last' ], true ) ? $row['width'] : 'wide';
            $clean[] = [
                    'key'            => $key,
                    'section'        => $section,
                    'label'          => sanitize_text_field( $row['label'] ?? $key ),
                    'type'           => sanitize_key( $row['type'] ?? 'text' ),
                    'required'       => (bool) ( $row['required'] ?? false ),
                    'enabled'        => (bool) ( $row['enabled'] ?? true ),
                    'priority'       => absint( $row['priority'] ?? 10 ),
                    'width'          => $width,
                    'custom'         => (bool) ( $row['custom'] ?? false ),
                    'show_countries' => sanitize_text_field( $row['show_countries'] ?? '' ),
            ];
        }

        update_option( 'space_core_woo_checkout_fields', wp_json_encode( $clean ) );
        wp_send_json_success( [ 'message' => __( 'Checkout fields saved.', 'space-core' ) ] );
    }

    public function ajax_reset(): void {
        check_ajax_referer( 'sc_wcf_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error();
        }
        delete_option( 'space_core_woo_checkout_fields' );
        wp_send_json_success( [ 'message' => __( 'Reset to WooCommerce defaults.', 'space-core' ) ] );
    }

    // ── Settings UI ───────────────────────────────────────────────

    public function render_settings(): void {
        $nonce    = wp_create_nonce( 'sc_wcf_nonce' );
        $sections = [ 'billing', 'shipping', 'order' ];
        $list     = $this->build_field_list();
        $types    = [
                'text'     => 'Text',
                'email'    => 'Email',
                'tel'      => 'Phone',
                'textarea' => 'Textarea',
                'select'   => 'Select',
                'checkbox' => 'Checkbox',
                'hidden'   => 'Hidden',
        ];
        ?>
        <div class="sc-module-section">
            <h2><?php esc_html_e( 'WooCommerce Checkout Fields', 'space-core' ); ?></h2>
            <p><?php esc_html_e( 'Drag rows to reorder. Toggle "On" to enable/disable. Add custom fields at the bottom. Save when done.', 'space-core' ); ?></p>

            <?php foreach ( $sections as $section ) :
                $fields = $list[ $section ] ?? [];
                ?>
                <h3 style="text-transform:capitalize;"><?php echo esc_html( ucfirst( $section ) ); ?></h3>
                <div class="sc-table-wrap">
                    <table class="widefat striped sc-ajax-table sc-responsive-table sc-sortable-table"
                           id="sc-wcf-<?php echo esc_attr( $section ); ?>-table"
                           data-section="<?php echo esc_attr( $section ); ?>">
                        <thead>
                        <tr>
                            <th class="sc-sort-handle-col"></th>
                            <th><?php esc_html_e( 'Key', 'space-core' ); ?></th>
                            <th><?php esc_html_e( 'Label', 'space-core' ); ?></th>
                            <th><?php esc_html_e( 'Type', 'space-core' ); ?></th>
                            <th><?php esc_html_e( 'Width', 'space-core' ); ?></th>
                            <th><?php esc_html_e( 'Countries', 'space-core' ); ?></th>
                            <th><?php esc_html_e( 'Required', 'space-core' ); ?></th>
                            <th><?php esc_html_e( 'Enabled', 'space-core' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'space-core' ); ?></th>
                        </tr>
                        </thead>
                        <tbody class="sc-sortable-body">
                        <?php $priority = 10;
                        foreach ( $fields as $key => $field ) : $priority += 10;
                            $is_ls = ! empty( $field['local_shipping'] );
                        ?>
                            <?php if ( $is_ls ) : ?>
                            <tr class="sc-table-row sc-ls-field"
                                data-key="<?php echo esc_attr( $key ); ?>"
                                data-section="<?php echo esc_attr( $section ); ?>"
                                data-custom="0"
                                data-local-shipping="1"
                                data-priority="<?php echo esc_attr( $priority ); ?>">
                                <td class="sc-sort-handle" data-label="⠿">⠿</td>
                                <td data-label="<?php esc_attr_e( 'Key', 'space-core' ); ?>">
                                    <code><?php echo esc_html( $key ); ?></code>
                                    <input type="hidden" class="sc-field" data-field="key" value="<?php echo esc_attr( $key ); ?>"/>
                                    <input type="hidden" class="sc-field" data-field="local_shipping" value="1"/>
                                </td>
                                <td data-label="<?php esc_attr_e( 'Label', 'space-core' ); ?>">
                                    <?php esc_html_e( 'Delivery Area', 'space-core' ); ?>
                                </td>
                                <td data-label="<?php esc_attr_e( 'Type', 'space-core' ); ?>">
                                    <span><?php esc_html_e( 'combo', 'space-core' ); ?></span>
                                </td>
                                <td data-label="<?php esc_attr_e( 'Width', 'space-core' ); ?>">
                                    <span>wide</span>
                                </td>
                                <td data-label="<?php esc_attr_e( 'Countries', 'space-core' ); ?>">
                                    <span>—</span>
                                </td>
                                <td data-label="<?php esc_attr_e( 'Required', 'space-core' ); ?>">
                                    <input type="checkbox" disabled />
                                </td>
                                <td data-label="<?php esc_attr_e( 'Enabled', 'space-core' ); ?>">
                                    <input type="checkbox" checked disabled />
                                </td>
                                <td class="sc-row-actions" data-label="<?php esc_attr_e( 'Actions', 'space-core' ); ?>">
                                    <span class="sc-badge"><?php esc_html_e( 'Local Shipping', 'space-core' ); ?></span>
                                </td>
                            </tr>
                            <?php else : ?>
                            <tr class="sc-table-row <?php echo $field['custom'] ? 'sc-custom-field' : 'sc-default-field'; ?>"
                                data-key="<?php echo esc_attr( $key ); ?>"
                                data-section="<?php echo esc_attr( $section ); ?>"
                                data-custom="<?php echo $field['custom'] ? '1' : '0'; ?>"
                                data-priority="<?php echo esc_attr( $priority ); ?>">
                                <td class="sc-sort-handle" data-label="⠿">⠿</td>
                                <td data-label="<?php esc_attr_e( 'Key', 'space-core' ); ?>">
                                    <?php if ( $field['custom'] ) : ?>
                                        <input type="text" class="sc-field sc-slug-field" data-field="key"
                                               value="<?php echo esc_attr( $key ); ?>"/>
                                    <?php else : ?>
                                        <code><?php echo esc_html( $key ); ?></code>
                                        <input type="hidden" class="sc-field" data-field="key"
                                               value="<?php echo esc_attr( $key ); ?>"/>
                                    <?php endif; ?>
                                </td>
                                <td data-label="<?php esc_attr_e( 'Label', 'space-core' ); ?>">
                                    <input type="text" class="sc-field" data-field="label"
                                           value="<?php echo esc_attr( $field['label'] ); ?>"/>
                                </td>
                                <td data-label="<?php esc_attr_e( 'Type', 'space-core' ); ?>">
                                    <?php if ( $field['custom'] ) : ?>
                                        <select class="sc-field" data-field="type">
                                            <?php foreach ( $types as $t_key => $t_label ) : ?>
                                                <option value="<?php echo esc_attr( $t_key ); ?>" <?php selected( $field['type'], $t_key ); ?>><?php echo esc_html( $t_label ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else : ?>
                                        <span><?php echo esc_html( $field['type'] ); ?></span>
                                        <input type="hidden" class="sc-field" data-field="type"
                                               value="<?php echo esc_attr( $field['type'] ); ?>"/>
                                    <?php endif; ?>
                                </td>
                                <td data-label="<?php esc_attr_e( 'Width', 'space-core' ); ?>">
                                    <select class="sc-field" data-field="width">
                                        <option value="wide"  <?php selected( $field['width'] ?? 'wide', 'wide' ); ?>><?php esc_html_e( 'Full (1 col)', 'space-core' ); ?></option>
                                        <option value="first" <?php selected( $field['width'] ?? 'wide', 'first' ); ?>><?php esc_html_e( 'Left (2 col)', 'space-core' ); ?></option>
                                        <option value="last"  <?php selected( $field['width'] ?? 'wide', 'last' ); ?>><?php esc_html_e( 'Right (2 col)', 'space-core' ); ?></option>
                                    </select>
                                </td>
                                <td data-label="<?php esc_attr_e( 'Countries', 'space-core' ); ?>">
                                    <input type="text" class="sc-field" data-field="show_countries"
                                           value="<?php echo esc_attr( $field['show_countries'] ?? '' ); ?>"
                                           placeholder="KW,SA,AE" style="width:90px;" />
                                </td>
                                <td data-label="<?php esc_attr_e( 'Required', 'space-core' ); ?>">
                                    <input type="checkbox" class="sc-field sc-bool-field"
                                           data-field="required" <?php checked( $field['required'] ?? false ); ?> />
                                </td>
                                <td data-label="<?php esc_attr_e( 'Enabled', 'space-core' ); ?>">
                                    <input type="checkbox" class="sc-field sc-bool-field"
                                           data-field="enabled" <?php checked( $field['enabled'] ?? true ); ?> />
                                </td>
                                <td class="sc-row-actions" data-label="<?php esc_attr_e( 'Actions', 'space-core' ); ?>">
                                    <?php if ( $field['custom'] ) : ?>
                                        <button type="button" class="button button-small sc-wcf-delete-row"
                                                data-key="<?php echo esc_attr( $key ); ?>"
                                                data-section="<?php echo esc_attr( $section ); ?>">
                                            <?php esc_html_e( 'Delete', 'space-core' ); ?>
                                        </button>
                                    <?php else : ?>
                                        <span class="sc-badge"><?php esc_html_e( 'Default', 'space-core' ); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="sc-table-footer">
                        <button type="button" class="button sc-wcf-add-row"
                                data-section="<?php echo esc_attr( $section ); ?>">
                            + <?php esc_html_e( 'Add Custom Field', 'space-core' ); ?>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="sc-table-footer sc-wcf-global-footer">
                <button type="button" class="button button-primary sc-wcf-save-all"
                        data-action="sc_save_checkout_fields" data-nonce="<?php echo esc_attr( $nonce ); ?>">
                    <?php esc_html_e( 'Save All Fields', 'space-core' ); ?>
                </button>
                <button type="button" class="button sc-wcf-reset" data-action="sc_reset_checkout_fields"
                        data-nonce="<?php echo esc_attr( $nonce ); ?>">
                    <?php esc_html_e( 'Reset to Defaults', 'space-core' ); ?>
                </button>
                <span class="sc-save-status"></span>
            </div>
        </div>
        <?php
    }
}
