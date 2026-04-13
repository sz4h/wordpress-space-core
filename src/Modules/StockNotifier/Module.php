<?php

namespace Space\Core\Modules\StockNotifier;

defined( 'ABSPATH' ) || exit;

use Space\Core\Abstracts\AbstractModule;
use WC_Product;

class Module extends AbstractModule {

	public function get_label(): string {
		return __( 'Stock Notifier', 'space-core' );
	}

	public function get_description(): string {
		return __( 'Notify customers via email, SMS, or WhatsApp when out-of-stock products become available.', 'space-core' );
	}

	public function boot(): void {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'sc_stock_notify', [ $this, 'run_notify_job' ] );
		add_shortcode( 'sc_stock_notifier', [ $this, 'render_form_shortcode' ] );
		add_action( 'wp_ajax_sc_stock_subscribe', [ $this, 'handle_subscribe_ajax' ] );
		add_action( 'wp_ajax_nopriv_sc_stock_subscribe', [ $this, 'handle_subscribe_ajax' ] );
		add_action( 'wp_ajax_sc_save_lang_templates', [ $this, 'ajax_save_lang_templates' ] );
		add_action( 'wp_ajax_sc_delete_lang_template', [ $this, 'ajax_delete_lang_template' ] );

		// Auto-injects form on the product page based on config.
		$o    = $this->get_options();
		$hook = sanitize_key( $o['form_position'] ?? 'woocommerce_after_add_to_cart_form' );
		add_action( $hook, [ $this, 'maybe_render_form' ], 20 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	private function get_options(): array {
		$defaults = [
			'collect_email'         => 1,
			'collect_phone'         => 0,
			'channels'              => [ 'email' ],
			'form_position'         => 'woocommerce_after_add_to_cart_form',
			'email_subject'         => '{product_name} is back in stock!',
			'email_body'            => "Good news!\n\n{product_name} is now back in stock and ready to order.\n\nDon't miss out — quantities may be limited.",
			'sms_username'          => '',
			'sms_password'          => '',
			'sms_customer_id'       => '',
			'sms_sender'            => '',
			'sms_body'              => '{product_name} is back in stock! Order now: {product_url}',
			'wa_evolution_url'      => '',
			'wa_evolution_key'      => '',
			'wa_evolution_instance' => '',
			'wa_body'               => '🎉 *{product_name}* is back in stock! Order now: {product_url}',
			'lang_templates'        => [],  // [ { lang, email_subject, email_body, sms_body, wa_body } ]
		];
		$saved    = get_option( 'space_core_stock_notifier', [] );

		return is_array( $saved ) ? array_merge( $defaults, $saved ) : $defaults;
	}

	public function enqueue_assets(): void {
		if ( ! is_product() ) {
			return;
		}
		wp_enqueue_style(
			'space-core-front',
			SPACE_CORE_URL . 'assets/css/front.css',
			[],
			SPACE_CORE_VERSION
		);
		wp_enqueue_script(
			'space-core-front',
			SPACE_CORE_URL . 'assets/js/front.js',
			[],
			SPACE_CORE_VERSION,
			true
		);
		wp_localize_script( 'space-core-front', 'spaceCore', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		] );
	}

	public function on_activate(): void {
		SubscriberDB::create_table();
		if ( ! wp_next_scheduled( 'sc_stock_notify' ) ) {
			wp_schedule_event( time(), 'hourly', 'sc_stock_notify' );
		}
	}

	public function register_settings(): void {
		register_setting( 'space_core_sn_group', 'space_core_stock_notifier', [
			'type'              => 'array',
			'sanitize_callback' => [ $this, 'sanitize_options' ],
			'default'           => [],
		] );
	}

	public function sanitize_options( mixed $input ): array {
		if ( ! is_array( $input ) ) {
			return [];
		}

		// Lang templates are managed via a separate AJAX handler, not the main form.
		// Preserve the currently stored value when the submission doesn't include them.
		$existing_templates = (array) ( get_option( 'space_core_stock_notifier', [] )['lang_templates'] ?? [] );

		return [
			'collect_email'         => ! empty( $input['collect_email'] ) ? 1 : 0,
			'collect_phone'         => absint( $input['collect_phone'] ?? 0 ),
			'channels'              => array_map( 'sanitize_key', (array) ( $input['channels'] ?? [ 'email' ] ) ),
			'form_position'         => sanitize_key( $input['form_position'] ?? 'woocommerce_after_add_to_cart_form' ),
			'email_subject'         => sanitize_text_field( $input['email_subject'] ?? '' ),
			'email_body'            => sanitize_textarea_field( $input['email_body'] ?? '' ),
			'sms_username'          => sanitize_text_field( $input['sms_username'] ?? '' ),
			'sms_password'          => sanitize_text_field( $input['sms_password'] ?? '' ),
			'sms_customer_id'       => sanitize_text_field( $input['sms_customer_id'] ?? '' ),
			'sms_sender'            => sanitize_text_field( $input['sms_sender'] ?? '' ),
			'sms_body'              => sanitize_textarea_field( $input['sms_body'] ?? '' ),
			'wa_evolution_url'      => esc_url_raw( $input['wa_evolution_url'] ?? '' ),
			'wa_evolution_key'      => sanitize_text_field( $input['wa_evolution_key'] ?? '' ),
			'wa_evolution_instance' => sanitize_text_field( $input['wa_evolution_instance'] ?? '' ),
			'wa_body'               => sanitize_textarea_field( $input['wa_body'] ?? '' ),
			'lang_templates'        => isset( $input['lang_templates'] )
				? $this->sanitize_lang_templates( $input['lang_templates'] )
				: $existing_templates,
		];
	}

	private function sanitize_lang_templates( mixed $raw ): array {
		// Accept either a JSON string (from the textarea) or an already-decoded array.
		if ( is_string( $raw ) ) {
			$raw = json_decode( stripslashes( $raw ), true );
		}
		if ( ! is_array( $raw ) ) {
			return [];
		}

		$clean = [];
		foreach ( $raw as $row ) {
			$lang = sanitize_text_field( $row['lang'] ?? '' );
			if ( empty( $lang ) ) {
				continue;
			}
			$clean[] = [
				'lang'          => $lang,
				'email_subject' => sanitize_text_field( $row['email_subject'] ?? '' ),
				'email_body'    => sanitize_textarea_field( $row['email_body'] ?? '' ),
				'sms_body'      => sanitize_textarea_field( $row['sms_body'] ?? '' ),
				'wa_body'       => sanitize_textarea_field( $row['wa_body'] ?? '' ),
			];
		}

		return $clean;
	}

	public function run_notify_job(): void {
		$o = $this->get_options();
		( new NotifyJob( $o ) )->run();
	}

	public function maybe_render_form(): void {
		global $product;
		if ( ! $product instanceof WC_Product ) {
			$product = wc_get_product();
		}
		if ( ! $product || $product->is_in_stock() ) {
			return;
		}
		echo $this->render_form( $product->get_id() ); // phpcs:ignore
	}

	private function render_form( int $product_id ): string {
		$o     = $this->get_options();
		$nonce = wp_create_nonce( 'sc_stock_subscribe_' . $product_id );

		return $this->view( 'front/subscribe/form', [
			'product_id'    => $product_id,
			'nonce'         => $nonce,
			'collect_email' => (bool) $o['collect_email'],
			'collect_phone' => (bool) $o['collect_phone'],
		] );
	}

	public function render_form_shortcode( array $atts ): string {
		global $product;
		$product_id = absint( $atts['product_id'] ?? ( $product ? $product->get_id() : 0 ) );
		if ( ! $product_id ) {
			return '';
		}
		$p = wc_get_product( $product_id );
		if ( ! $p || $p->is_in_stock() ) {
			return '';
		}

		return $this->render_form( $product_id );
	}

	public function handle_subscribe_ajax(): void {
		$product_id = absint( $_POST['product_id'] ?? 0 );
		$nonce      = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );

		if ( ! wp_verify_nonce( $nonce, 'sc_stock_subscribe_' . $product_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'space-core' ) ] );
		}

		$o       = $this->get_options();
		$channel = 'email';
		$contact = '';

		if ( $o['collect_email'] && ! empty( $_POST['sc_contact_email'] ) ) {
			$contact = sanitize_email( wp_unslash( $_POST['sc_contact_email'] ) );
			if ( ! is_email( $contact ) ) {
				wp_send_json_error( [ 'message' => __( 'Invalid email address.', 'space-core' ) ] );
			}
		} elseif ( $o['collect_phone'] && ! empty( $_POST['sc_contact_phone'] ) ) {
			$contact = sanitize_text_field( wp_unslash( $_POST['sc_contact_phone'] ) );
			$channel = in_array( 'whatsapp', (array) $o['channels'], true ) ? 'whatsapp' : 'sms';
		}

		if ( empty( $contact ) ) {
			wp_send_json_error( [ 'message' => __( 'Please provide your contact.', 'space-core' ) ] );
		}

		$lang   = sanitize_text_field( get_locale() );
		$result = SubscriberDB::insert( $product_id, $contact, $channel, $lang );

		if ( $result ) {
			wp_send_json_success( [ 'message' => __( 'You will be notified when this product is back in stock.', 'space-core' ) ] );
		} else {
			wp_send_json_error( [ 'message' => __( 'Something went wrong. Please try again.', 'space-core' ) ] );
		}
	}

	// ── Language template AJAX ────────────────────────────────────

	public function ajax_save_lang_templates(): void {
		check_ajax_referer( 'sc_sn_lang_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'space-core' ) ] );
		}
		$rows      = json_decode( stripslashes( $_POST['rows'] ?? '[]' ), true );
		$templates = $this->sanitize_lang_templates( $rows );

		$o                   = $this->get_options();
		$o['lang_templates'] = $templates;
		update_option( 'space_core_stock_notifier', $o );

		wp_send_json_success( [ 'message' => __( 'Language templates saved.', 'space-core' ), 'rows' => $templates ] );
	}

	public function ajax_delete_lang_template(): void {
		check_ajax_referer( 'sc_sn_lang_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$lang                = sanitize_text_field( $_POST['lang'] ?? '' );
		$o                   = $this->get_options();
		$o['lang_templates'] = array_values( array_filter(
			(array) $o['lang_templates'],
			fn( $t ) => $t['lang'] !== $lang
		) );
		update_option( 'space_core_stock_notifier', $o );
		wp_send_json_success();
	}

	public function render_settings(): void {
		$o                = $this->get_options();
		$position_options = [
			'woocommerce_after_add_to_cart_form'        => __( 'After Add to Cart Form', 'space-core' ),
			'woocommerce_before_add_to_cart_form'       => __( 'Before Add to Cart Form', 'space-core' ),
			'woocommerce_after_single_product_summary'  => __( 'After Product Summary', 'space-core' ),
			'woocommerce_before_single_product_summary' => __( 'Before Product Summary', 'space-core' ),
		];
		$lang_nonce       = wp_create_nonce( 'sc_sn_lang_nonce' );
		$lang_templates   = (array) ( $o['lang_templates'] ?? [] );

		echo $this->view( 'admin/settings', [
			'options'          => $o,
			'position_options' => $position_options,
			'lang_nonce'       => $lang_nonce,
			'lang_templates'   => $lang_templates,
		] );
	}
}
