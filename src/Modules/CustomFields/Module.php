<?php

namespace Space\Core\Modules\CustomFields;

defined( 'ABSPATH' ) || exit;

use Space\Core\Abstracts\AbstractModule;

class Module extends AbstractModule {

    private array $definitions = [];

    public function get_label(): string {
        return __( 'Custom Fields', 'space-core' );
    }

    public function get_description(): string {
        return __( 'Add custom meta fields to any post type via a table UI.', 'space-core' );
    }

    public function boot(): void {
        $this->definitions = $this->load_definitions();
        add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
        add_action( 'save_post', [ $this, 'save_meta' ], 10, 2 );
        add_action( 'wp_ajax_sc_save_custom_fields', [ $this, 'ajax_save' ] );
        add_action( 'wp_ajax_sc_delete_custom_field', [ $this, 'ajax_delete' ] );
    }

    private function load_definitions(): array {
        $raw = get_option( 'space_core_custom_fields', '[]' );
        $def = json_decode( $raw, true );
        return is_array( $def ) ? $def : [];
    }

    // ── AJAX ─────────────────────────────────────────────────────

    public function ajax_save(): void {
        check_ajax_referer( 'sc_cf_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'space-core' ) ] );
        }

        $rows = json_decode( stripslashes( $_POST['rows'] ?? '[]' ), true );
        if ( ! is_array( $rows ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid data.', 'space-core' ) ] );
        }

        $valid_types = [ 'text', 'textarea', 'select', 'checkbox', 'date', 'image', 'number', 'url' ];
        $clean       = [];

        foreach ( $rows as $row ) {
            $key  = sanitize_key( $row['key'] ?? '' );
            $type = sanitize_key( $row['type'] ?? 'text' );
            if ( empty( $key ) ) continue;
            if ( ! in_array( $type, $valid_types, true ) ) $type = 'text';

            $choices = [];
            if ( 'select' === $type && ! empty( $row['choices'] ) ) {
                $choices = array_filter( array_map( 'sanitize_text_field', explode( "\n", $row['choices'] ) ) );
            }

            $clean[] = [
                'key'       => $key,
                'label'     => sanitize_text_field( $row['label'] ?? $key ),
                'type'      => $type,
                'post_type' => sanitize_key( $row['post_type'] ?? 'post' ),
                'choices'   => array_values( $choices ),
            ];
        }

        update_option( 'space_core_custom_fields', wp_json_encode( $clean ) );
        wp_send_json_success( [ 'message' => __( 'Fields saved.', 'space-core' ), 'rows' => $clean ] );
    }

    public function ajax_delete(): void {
        check_ajax_referer( 'sc_cf_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error();
        }
        $key  = sanitize_key( $_POST['key'] ?? '' );
        $pt   = sanitize_key( $_POST['post_type'] ?? '' );
        $defs = $this->load_definitions();
        $defs = array_values( array_filter( $defs, fn( $d ) => !( $d['key'] === $key && $d['post_type'] === $pt ) ) );
        update_option( 'space_core_custom_fields', wp_json_encode( $defs ) );
        wp_send_json_success();
    }

    // ── Meta boxes ────────────────────────────────────────────────

    public function add_meta_boxes(): void {
        $groups = [];
        foreach ( $this->definitions as $field ) {
            $pt = sanitize_key( $field['post_type'] ?? 'post' );
            $groups[ $pt ][] = $field;
        }
        foreach ( $groups as $post_type => $fields ) {
            add_meta_box(
                'sc_custom_fields_' . $post_type,
                __( 'Space Core Fields', 'space-core' ),
                fn( \WP_Post $post ) => $this->render_meta_box( $post, $fields ),
                $post_type,
                'normal',
                'default'
            );
        }
    }

    private function render_meta_box( \WP_Post $post, array $fields ): void {
        wp_nonce_field( 'sc_custom_fields_save', 'sc_cf_nonce' );
        echo '<table class="form-table sc-cf-table"><tbody>';
        foreach ( $fields as $field ) {
            $key   = sanitize_key( $field['key'] ?? '' );
            $label = sanitize_text_field( $field['label'] ?? $key );
            $type  = sanitize_key( $field['type'] ?? 'text' );
            $value = get_post_meta( $post->ID, '_sc_' . $key, true );
            echo '<tr><th scope="row"><label for="sc_cf_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
            $this->render_field( $key, $type, $value, $field );
            echo '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private function render_field( string $key, string $type, mixed $value, array $field ): void {
        $name = 'sc_cf[' . esc_attr( $key ) . ']';
        $id   = 'sc_cf_' . esc_attr( $key );
        switch ( $type ) {
            case 'textarea':
                printf( '<textarea id="%s" name="%s" rows="4" class="large-text">%s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_textarea( $value ) );
                break;
            case 'select':
                $choices = $field['choices'] ?? [];
                echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
                echo '<option value=""></option>';
                foreach ( $choices as $opt ) {
                    echo '<option value="' . esc_attr( $opt ) . '" ' . selected( $value, $opt, false ) . '>' . esc_html( $opt ) . '</option>';
                }
                echo '</select>';
                break;
            case 'checkbox':
                printf( '<input type="checkbox" id="%s" name="%s" value="1" %s />', esc_attr( $id ), esc_attr( $name ), checked( $value, '1', false ) );
                break;
            case 'date':
                printf( '<input type="date" id="%s" name="%s" value="%s" />', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ) );
                break;
            case 'number':
                printf( '<input type="number" id="%s" name="%s" value="%s" class="small-text" />', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ) );
                break;
            case 'url':
                printf( '<input type="url" id="%s" name="%s" value="%s" class="regular-text" />', esc_attr( $id ), esc_attr( $name ), esc_url( $value ) );
                break;
            case 'image':
                $img_url = $value ? wp_get_attachment_image_url( (int) $value, 'thumbnail' ) : '';
                printf(
                    '<div class="sc-image-field">
                        <input type="hidden" id="%1$s" name="%2$s" value="%3$s" />
                        %4$s
                        <button type="button" class="button sc-upload-image" data-target="%1$s">%5$s</button>
                        <button type="button" class="button sc-remove-image" data-target="%1$s" %6$s>%7$s</button>
                    </div>',
                    esc_attr( $id ), esc_attr( $name ), esc_attr( $value ),
                    $img_url ? '<img src="' . esc_url( $img_url ) . '" style="max-width:100px;display:block;margin-bottom:6px;" />' : '',
                    esc_html__( 'Select Image', 'space-core' ),
                    $value ? '' : 'style="display:none"',
                    esc_html__( 'Remove', 'space-core' )
                );
                break;
            default:
                printf( '<input type="text" id="%s" name="%s" value="%s" class="regular-text" />', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ) );
        }
    }

    public function save_meta( int $post_id, \WP_Post $post ): void {
        if ( ! isset( $_POST['sc_cf_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sc_cf_nonce'] ) ), 'sc_custom_fields_save' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        foreach ( $this->definitions as $field ) {
            if ( sanitize_key( $field['post_type'] ?? '' ) !== $post->post_type ) continue;
            $key  = sanitize_key( $field['key'] ?? '' );
            $type = $field['type'] ?? 'text';
            if ( 'checkbox' === $type ) {
                update_post_meta( $post_id, '_sc_' . $key, isset( $_POST['sc_cf'][ $key ] ) ? '1' : '' );
            } elseif ( isset( $_POST['sc_cf'][ $key ] ) ) {
                $value = match ( $type ) {
                    'textarea' => sanitize_textarea_field( wp_unslash( $_POST['sc_cf'][ $key ] ) ),
                    'image'    => absint( $_POST['sc_cf'][ $key ] ),
                    'url'      => esc_url_raw( wp_unslash( $_POST['sc_cf'][ $key ] ) ),
                    'number'   => (float) $_POST['sc_cf'][ $key ],
                    default    => sanitize_text_field( wp_unslash( $_POST['sc_cf'][ $key ] ) ),
                };
                update_post_meta( $post_id, '_sc_' . $key, $value );
            }
        }
    }

    // ── Settings UI ───────────────────────────────────────────────

    public function render_settings(): void {
        $nonce = wp_create_nonce( 'sc_cf_nonce' );
        $defs  = $this->load_definitions();
        $types = [
            'text'     => __( 'Text', 'space-core' ),
            'textarea' => __( 'Textarea', 'space-core' ),
            'select'   => __( 'Select', 'space-core' ),
            'checkbox' => __( 'Checkbox', 'space-core' ),
            'date'     => __( 'Date', 'space-core' ),
            'image'    => __( 'Image', 'space-core' ),
            'number'   => __( 'Number', 'space-core' ),
            'url'      => __( 'URL', 'space-core' ),
        ];
        $post_types = get_post_types( [ 'show_ui' => true ], 'objects' );
        ?>
        <div class="sc-module-section">
            <h2><?php esc_html_e( 'Custom Fields', 'space-core' ); ?></h2>
            <p><?php esc_html_e( 'Define meta fields per post type. For Select fields, enter one choice per line in the Choices column.', 'space-core' ); ?></p>

            <div class="sc-table-wrap">
                <table class="widefat striped sc-ajax-table sc-responsive-table" id="sc-cf-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Label', 'space-core' ); ?></th>
                            <th><?php esc_html_e( 'Key', 'space-core' ); ?></th>
                            <th><?php esc_html_e( 'Type', 'space-core' ); ?></th>
                            <th><?php esc_html_e( 'Post Type', 'space-core' ); ?></th>
                            <th><?php esc_html_e( 'Choices', 'space-core' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'space-core' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $defs as $i => $field ) : ?>
                        <tr class="sc-table-row" data-index="<?php echo esc_attr( $i ); ?>">
                            <td data-label="<?php esc_attr_e( 'Label', 'space-core' ); ?>">
                                <input type="text" class="sc-field" data-field="label" value="<?php echo esc_attr( $field['label'] ); ?>" />
                            </td>
                            <td data-label="<?php esc_attr_e( 'Key', 'space-core' ); ?>">
                                <input type="text" class="sc-field sc-slug-field" data-field="key" value="<?php echo esc_attr( $field['key'] ); ?>" />
                            </td>
                            <td data-label="<?php esc_attr_e( 'Type', 'space-core' ); ?>">
                                <select class="sc-field sc-type-select" data-field="type">
                                    <?php foreach ( $types as $t_key => $t_label ) : ?>
                                        <option value="<?php echo esc_attr( $t_key ); ?>" <?php selected( $field['type'], $t_key ); ?>><?php echo esc_html( $t_label ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td data-label="<?php esc_attr_e( 'Post Type', 'space-core' ); ?>">
                                <select class="sc-field" data-field="post_type">
                                    <?php foreach ( $post_types as $pt ) : ?>
                                        <option value="<?php echo esc_attr( $pt->name ); ?>" <?php selected( $field['post_type'], $pt->name ); ?>><?php echo esc_html( $pt->labels->singular_name ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td data-label="<?php esc_attr_e( 'Choices', 'space-core' ); ?>" class="sc-choices-cell <?php echo 'select' === $field['type'] ? '' : 'sc-hidden'; ?>">
                                <textarea class="sc-field" data-field="choices" rows="3" placeholder="<?php esc_attr_e( "Option A\nOption B", 'space-core' ); ?>"><?php echo esc_textarea( implode( "\n", (array) ( $field['choices'] ?? [] ) ) ); ?></textarea>
                            </td>
                            <td class="sc-row-actions" data-label="<?php esc_attr_e( 'Actions', 'space-core' ); ?>">
                                <button type="button" class="button button-small sc-delete-row" data-action="sc_delete_custom_field" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-key="<?php echo esc_attr( $field['key'] ); ?>" data-post_type="<?php echo esc_attr( $field['post_type'] ); ?>">
                                    <?php esc_html_e( 'Delete', 'space-core' ); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="sc-table-footer">
                    <button type="button" class="button sc-add-row" data-table="sc-cf-table" data-template="sc-cf-row-tpl">
                        + <?php esc_html_e( 'Add Field', 'space-core' ); ?>
                    </button>
                    <button type="button" class="button button-primary sc-save-table" data-table="sc-cf-table" data-action="sc_save_custom_fields" data-nonce="<?php echo esc_attr( $nonce ); ?>">
                        <?php esc_html_e( 'Save All', 'space-core' ); ?>
                    </button>
                    <span class="sc-save-status"></span>
                </div>
            </div>
        </div>

        <script type="text/html" id="sc-cf-row-tpl">
        <tr class="sc-table-row sc-new-row" data-index="__INDEX__">
            <td data-label="<?php esc_attr_e( 'Label', 'space-core' ); ?>">
                <input type="text" class="sc-field" data-field="label" value="" placeholder="My Field" />
            </td>
            <td data-label="<?php esc_attr_e( 'Key', 'space-core' ); ?>">
                <input type="text" class="sc-field sc-slug-field" data-field="key" value="" placeholder="my_field" />
            </td>
            <td data-label="<?php esc_attr_e( 'Type', 'space-core' ); ?>">
                <select class="sc-field sc-type-select" data-field="type">
                    <?php foreach ( $types as $t_key => $t_label ) : ?>
                        <option value="<?php echo esc_attr( $t_key ); ?>"><?php echo esc_html( $t_label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td data-label="<?php esc_attr_e( 'Post Type', 'space-core' ); ?>">
                <select class="sc-field" data-field="post_type">
                    <?php foreach ( $post_types as $pt ) : ?>
                        <option value="<?php echo esc_attr( $pt->name ); ?>"><?php echo esc_html( $pt->labels->singular_name ); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td data-label="<?php esc_attr_e( 'Choices', 'space-core' ); ?>" class="sc-choices-cell sc-hidden">
                <textarea class="sc-field" data-field="choices" rows="3" placeholder="<?php esc_attr_e( "Option A\nOption B", 'space-core' ); ?>"></textarea>
            </td>
            <td class="sc-row-actions" data-label="<?php esc_attr_e( 'Actions', 'space-core' ); ?>">
                <button type="button" class="button button-small sc-remove-new-row"><?php esc_html_e( 'Remove', 'space-core' ); ?></button>
            </td>
        </tr>
        </script>
        <?php
    }
}
