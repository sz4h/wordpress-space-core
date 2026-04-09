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

        // Display custom fields in order details (admin + frontend + emails).
        add_action( 'woocommerce_admin_order_data_after_billing_address', [ $this, 'display_in_admin_order' ] );
        add_filter( 'woocommerce_order_details_after_order_table', [ $this, 'display_in_order_details' ] );
        add_action( 'woocommerce_email_order_meta', [ $this, 'display_in_order_email' ], 10, 3 );
    }

    // ── Default WC fields catalogue ───────────────────────────────

    public function modify_checkout_fields( array $fields ): array {
        $config = $this->get_config();
        if ( empty( $config ) ) {
            return $fields;
        }

        $list = $this->build_field_list();

        foreach ( $list as $section => $section_fields ) {
            foreach ( $section_fields as $key => $field ) {
                if ( ! $field['enabled'] ) {
                    unset( $fields[ $section ][ $key ] );
                    continue;
                }
                if ( $field['custom'] ) {
                    $fields[ $section ][ $key ] = [
                            'label'    => $field['label'],
                            'type'     => $field['type'],
                            'required' => $field['required'],
                            'priority' => $field['priority'],
                            'class'    => [ 'form-row-wide' ],
                    ];
                } else {
                    // Merge overrides into existing WC field.
                    if ( isset( $fields[ $section ][ $key ] ) ) {
                        $fields[ $section ][ $key ]['label']    = $field['label'];
                        $fields[ $section ][ $key ]['required'] = $field['required'];
                        $fields[ $section ][ $key ]['priority'] = $field['priority'];
                    }
                }
            }
        }

        return $fields;
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
                        'key'        => $key,
                        'section'    => $section,
                        'is_default' => true,
                        'enabled'    => true,
                        'priority'   => $field['priority'] ?? 10,
                        'custom'     => false,
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

            if ( isset( $result[ $section ][ $key ] ) ) {
                // Override existing default field.
                $result[ $section ][ $key ] = array_merge( $result[ $section ][ $key ], [
                        'label'    => $entry['label'] ?? $result[ $section ][ $key ]['label'],
                        'required' => (bool) ( $entry['required'] ?? $result[ $section ][ $key ]['required'] ),
                        'enabled'  => (bool) ( $entry['enabled'] ?? true ),
                        'priority' => (int) ( $entry['priority'] ?? 10 ),
                ] );
            } elseif ( ! empty( $entry['custom'] ) ) {
                // New custom field.
                $result[ $section ][ $key ] = [
                        'key'        => $key,
                        'section'    => $section,
                        'label'      => sanitize_text_field( $entry['label'] ?? $key ),
                        'type'       => sanitize_key( $entry['type'] ?? 'text' ),
                        'required'   => (bool) ( $entry['required'] ?? false ),
                        'enabled'    => (bool) ( $entry['enabled'] ?? true ),
                        'priority'   => (int) ( $entry['priority'] ?? 100 ),
                        'is_default' => false,
                        'custom'     => true,
                ];
            }
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
            $clean[] = [
                    'key'      => $key,
                    'section'  => $section,
                    'label'    => sanitize_text_field( $row['label'] ?? $key ),
                    'type'     => sanitize_key( $row['type'] ?? 'text' ),
                    'required' => (bool) ( $row['required'] ?? false ),
                    'enabled'  => (bool) ( $row['enabled'] ?? true ),
                    'priority' => absint( $row['priority'] ?? 10 ),
                    'custom'   => (bool) ( $row['custom'] ?? false ),
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
                            <th><?php esc_html_e( 'Required', 'space-core' ); ?></th>
                            <th><?php esc_html_e( 'Enabled', 'space-core' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'space-core' ); ?></th>
                        </tr>
                        </thead>
                        <tbody class="sc-sortable-body">
                        <?php $priority = 10;
                        foreach ( $fields as $key => $field ) : $priority += 10; ?>
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
