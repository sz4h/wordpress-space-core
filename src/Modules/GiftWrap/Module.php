<?php

namespace Space\Core\Modules\GiftWrap;

defined( 'ABSPATH' ) || exit;

use Space\Core\Abstracts\AbstractModule;

/**
 * Checkout Gift Wrap module.
 *
 * Adds an optional gift wrap checkbox + message textarea at checkout.
 * Labels support EN + AR. Admin configures: label, price, whether it's optional.
 * Fee is applied to cart; data persisted in order meta.
 */
class Module extends AbstractModule {

    const META_GIFT_WRAP    = '_sc_gift_wrap';
    const META_GIFT_MESSAGE = '_sc_gift_message';

    public function get_label(): string {
        return __( 'Gift Wrap', 'space-core' );
    }

    public function get_description(): string {
        return __( 'Let customers add gift wrapping with a personalised message at checkout.', 'space-core' );
    }

    private function opts(): array {
        $o = get_option( 'space_core_gift_wrap', [] );
        return is_array( $o ) ? $o : [];
    }

    /** Resolve a multilingual label stored as {en, ar} based on current locale. */
    private function resolve_label( string $en_key, string $ar_key, string $default_en, string $default_ar ): string {
        $o    = $this->opts();
        $lang = substr( get_locale(), 0, 2 );
        if ( 'ar' === $lang ) {
            $val = sanitize_text_field( $o[ $ar_key ] ?? '' );
            return $val ?: sanitize_text_field( $o[ $en_key ] ?? $default_en );
        }
        $val = sanitize_text_field( $o[ $en_key ] ?? '' );
        return $val ?: $default_en;
    }

    public function boot(): void {
        if ( ! class_exists( 'WooCommerce' ) ) return;

        add_action( 'woocommerce_review_order_before_submit', [ $this, 'render_checkout_fields' ] );
        add_action( 'woocommerce_cart_calculate_fees',         [ $this, 'apply_fee' ] );
        add_action( 'woocommerce_checkout_update_order_meta',  [ $this, 'save_meta' ] );
        add_action( 'woocommerce_checkout_process',            [ $this, 'validate' ] );
        add_action( 'wp_enqueue_scripts',                      [ $this, 'enqueue' ] );
        add_action( 'woocommerce_admin_order_data_after_billing_address', [ $this, 'display_admin' ] );
        add_action( 'woocommerce_email_order_meta',            [ $this, 'display_email' ], 10, 3 );
        add_action( 'woocommerce_order_details_after_order_table', [ $this, 'display_frontend' ] );

        add_action( 'wp_ajax_sc_toggle_gift_wrap',        [ $this, 'ajax_toggle' ] );
        add_action( 'wp_ajax_nopriv_sc_toggle_gift_wrap', [ $this, 'ajax_toggle' ] );
        add_action( 'wp_ajax_sc_save_gift_wrap',          [ $this, 'ajax_save_settings' ] );
    }

    public function enqueue(): void {
        if ( ! is_checkout() ) return;
        wp_add_inline_script( 'wc-checkout', $this->gift_wrap_inline_js(), 'after' );
    }

    private function gift_wrap_inline_js(): string {
        $ajax  = esc_url( admin_url( 'admin-ajax.php' ) );
        $nonce = wp_create_nonce( 'sc_gift_wrap_nonce' );
        return '(function($){' .
            '$(document).on("change","#sc-gift-wrap-check",function(){' .
                '$.post("' . $ajax . '",{action:"sc_toggle_gift_wrap",nonce:"' . $nonce . '",enabled:$(this).is(":checked")?1:0},' .
                    'function(){$(document.body).trigger("update_checkout");});' .
            '});' .
            '$(document).on("change","#sc-gift-wrap-check",function(){' .
                '$("#sc-gift-message-wrap").toggle($(this).is(":checked"));' .
            '}).trigger("change");' .
        '}(jQuery));';
    }

    public function render_checkout_fields(): void {
        $o           = $this->opts();
        $label       = $this->resolve_label( 'label_en', 'label_ar', __( 'Add Gift Wrap', 'space-core' ), __( 'أضف تغليف الهدايا', 'space-core' ) );
        $msg_lbl     = $this->resolve_label( 'message_label_en', 'message_label_ar', __( 'Gift Message', 'space-core' ), __( 'رسالة الهدية', 'space-core' ) );
        $price       = (float) ( $o['price'] ?? 0 );
        $optional    = empty( $o['force_wrap'] );
        $session     = WC()->session ? (bool) WC()->session->get( 'sc_gift_wrap', false ) : false;
        $price_label = $price > 0 ? ' (+' . wc_price( $price ) . ')' : '';

        wp_add_inline_script( 'wc-checkout', $this->gift_wrap_inline_js() );

        echo $this->view( 'front/checkout/fields', compact( 'label', 'msg_lbl', 'price_label', 'optional', 'session' ) );
    }

    public function ajax_toggle(): void {
        check_ajax_referer( 'sc_gift_wrap_nonce', 'nonce' );
        $enabled = ! empty( $_POST['enabled'] );
        if ( WC()->session ) {
            WC()->session->set( 'sc_gift_wrap', $enabled );
        }
        wp_send_json_success();
    }

    public function apply_fee( \WC_Cart $cart ): void {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $wrap = isset( $_POST['sc_gift_wrap'] ) ? (bool) $_POST['sc_gift_wrap'] : (bool) ( WC()->session ? WC()->session->get( 'sc_gift_wrap', false ) : false );
        if ( ! $wrap ) return;

        $o     = $this->opts();
        $price = (float) ( $o['price'] ?? 0 );
        if ( $price > 0 ) {
            $label = $this->resolve_label( 'label_en', 'label_ar', __( 'Gift Wrap', 'space-core' ), __( 'تغليف الهدايا', 'space-core' ) );
            $cart->add_fee( $label, $price, false );
        }
    }

    public function validate(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( ! empty( $_POST['sc_gift_wrap'] ) && WC()->session ) {
            WC()->session->set( 'sc_gift_wrap', true );
            WC()->session->set( 'sc_gift_message', sanitize_textarea_field( wp_unslash( $_POST['sc_gift_message'] ?? '' ) ) );
        }
    }

    public function save_meta( int $order_id ): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $wrap    = ! empty( $_POST['sc_gift_wrap'] );
        $message = sanitize_textarea_field( wp_unslash( $_POST['sc_gift_message'] ?? '' ) );
        update_post_meta( $order_id, self::META_GIFT_WRAP,    $wrap ? 'yes' : 'no' );
        update_post_meta( $order_id, self::META_GIFT_MESSAGE, $message );
        if ( WC()->session ) {
            WC()->session->__unset( 'sc_gift_wrap' );
            WC()->session->__unset( 'sc_gift_message' );
        }
    }

    private function get_order_gift_data( \WC_Order $order ): ?array {
        $wrap = get_post_meta( $order->get_id(), self::META_GIFT_WRAP, true );
        if ( 'yes' !== $wrap ) return null;
        return [ 'message' => get_post_meta( $order->get_id(), self::META_GIFT_MESSAGE, true ) ];
    }

    public function display_admin( \WC_Order $order ): void {
        $data = $this->get_order_gift_data( $order );
        if ( ! $data ) return;
        echo '<p><strong>' . esc_html__( 'Gift Wrap:', 'space-core' ) . '</strong> ✓</p>';
        if ( $data['message'] ) {
            echo '<p><strong>' . esc_html__( 'Gift Message:', 'space-core' ) . '</strong><br>' . esc_html( $data['message'] ) . '</p>';
        }
    }

    public function display_frontend( \WC_Order $order ): void {
        $data = $this->get_order_gift_data( $order );
        if ( ! $data ) return;
        echo $this->view( 'front/order/details', [ 'message' => $data['message'] ] );
    }

    public function display_email( \WC_Order $order ): void {
        $data = $this->get_order_gift_data( $order );
        if ( ! $data ) return;
        echo '<p><strong>' . esc_html__( 'Gift Wrap:', 'space-core' ) . '</strong> ✓</p>';
        if ( $data['message'] ) {
            echo '<p><strong>' . esc_html__( 'Gift Message:', 'space-core' ) . '</strong> ' . esc_html( $data['message'] ) . '</p>';
        }
    }

    public function ajax_save_settings(): void {
        check_ajax_referer( 'space_core_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [], 403 );

        $raw  = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : '{}'; // phpcs:ignore
        $data = json_decode( $raw, true );
        if ( ! is_array( $data ) ) wp_send_json_error();

        update_option( 'space_core_gift_wrap', [
            'label_en'         => sanitize_text_field( $data['label_en']         ?? '' ),
            'label_ar'         => sanitize_text_field( $data['label_ar']         ?? '' ),
            'message_label_en' => sanitize_text_field( $data['message_label_en'] ?? '' ),
            'message_label_ar' => sanitize_text_field( $data['message_label_ar'] ?? '' ),
            'price'            => (float) ( $data['price'] ?? 0 ),
            'force_wrap'       => ! empty( $data['force_wrap'] ) ? 1 : 0,
        ] );

        wp_send_json_success( [ 'message' => __( 'Saved!', 'space-core' ) ] );
    }

    public function render_settings(): void {
        $o     = $this->opts();
        $nonce = wp_create_nonce( 'space_core_admin' );
        echo $this->view( 'admin/settings', [
            'options' => $o,
            'nonce'   => $nonce,
        ] );
    }
}
