<?php

namespace Space\Core\Modules\MultiCurrency;

defined( 'ABSPATH' ) || exit;

use Space\Core\Abstracts\AbstractModule;
use Space\Core\Modules\MultiCurrency\Seeders\GCC;
use WC_Order;
use WC_Shipping_Rate;

/**
 * Multi-Currency module.
 *
 * - DB table: {prefix}sc_mc_currencies
 * - Converts all WC prices at render time (shop, product, cart, checkout)
 * - Session/cookie/user-meta/GeoIP currency resolution
 * - Frontend shortcode [sc_currency_switcher]
 * - Cron-based rate API fetching
 * - Payment gateway filtering per currency
 * - Order meta: _sc_order_currency, _sc_order_rate, _sc_order_currency_symbol, _sc_order_base_total
 * - LocalShipping delivery fee conversion via filter sc_local_shipping_fee
 */
class Module extends AbstractModule {

	// Order meta keys.
	const META_CURRENCY = '_sc_order_currency';
	const META_RATE = '_sc_order_rate';
	const META_SYMBOL = '_sc_order_currency_symbol';
	const META_BASE_TOTAL = '_sc_order_base_total';

	// Admin nonce.
	const NONCE_ADMIN = 'sc_mc_nonce';
	const NONCE_FRONTEND = 'sc_switch_nonce';

	public function get_label(): string {
		return __( 'Multi-Currency', 'space-core' );
	}

	public function get_description(): string {
		return __( 'Display prices and accept orders in multiple currencies with GeoIP detection and exchange rate sync.', 'space-core' );
	}

	// =========================================================================
	// Lifecycle
	// =========================================================================

	public function on_activate(): void {
		CurrencyDB::create_table();
		$this->schedule_cron();
	}

	private function schedule_cron( string $period = '' ): void {
		if ( ! $period ) {
			$cfg    = $this->get_config();
			$period = $cfg['rate_cron_period'] ?? 'daily';
		}
		if ( ! wp_next_scheduled( 'sc_fetch_currency_rates' ) ) {
			wp_schedule_event( time(), $period, 'sc_fetch_currency_rates' );
		}
	}

	// =========================================================================
	// Currency init (runs on 'init' priority 1)
	// =========================================================================

	private function get_config(): array {
		$cfg = get_option( 'space_core_multi_currency', [] );

		return is_array( $cfg ) ? $cfg : [];
	}

	// =========================================================================
	// WC currency identity filters
	// =========================================================================

	public function boot(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		// Resolve and cache active currency before WC price hooks fire.
		add_action( 'init', [ $this, 'init_currency' ], 1 );

		// -----------------------------------------------------------------
		// Product price filters — cover shop, product, related, upsells, etc.
		// -----------------------------------------------------------------
		$price_hooks = [
			'woocommerce_product_get_price',
			'woocommerce_product_get_regular_price',
			'woocommerce_product_get_sale_price',
			'woocommerce_product_variation_get_price',
			'woocommerce_product_variation_get_regular_price',
			'woocommerce_product_variation_get_sale_price',
		];
		foreach ( $price_hooks as $hook ) {
			add_filter( $hook, [ PriceConverter::class, 'filter_price' ], 10, 2 );
		}

		// Price formatting (symbol, decimals, position).
		add_filter( 'woocommerce_currency', [ $this, 'filter_currency_code' ] );
		add_filter( 'woocommerce_currency_symbol', [ $this, 'filter_currency_symbol' ], 10, 2 );
		add_filter( 'wc_price_args', [ PriceConverter::class, 'filter_price_args' ] );

		// Payment gateway restriction per currency.
		add_filter( 'woocommerce_available_payment_gateways', [ $this, 'filter_gateways' ] );

		// Order: store currency meta at checkout (HPOS-compatible).
		add_action( 'woocommerce_checkout_create_order', [ $this, 'save_order_currency' ] );

		// Order display: admin panel + frontend thank-you/my-account.
		add_action( 'woocommerce_admin_order_data_after_billing_address', [ $this, 'display_order_currency_admin' ] );
		add_filter( 'woocommerce_get_order_item_totals', [ $this, 'append_currency_row_frontend' ], 10, 3 );

		// Print Orders integration.
		add_action( 'sc_print_order_after_header', [ $this, 'print_order_currency_note' ] );

		// LocalShipping delivery fee conversion.
		add_filter( 'sc_local_shipping_fee', [ $this, 'convert_delivery_fee' ] );

		// WooCommerce built-in shipping rate conversion (flat rate, local pickup, etc.).
		add_filter( 'woocommerce_package_rates', [ $this, 'convert_package_rates' ], 10, 2 );

		// GiftWrap fee conversion.
		add_filter( 'sc_gift_wrap_fee', [ $this, 'convert_gift_wrap_fee' ] );

		// Cron.
		add_action( 'sc_fetch_currency_rates', [ RateFetcher::class, 'run' ] );

		// Shortcode.
		add_shortcode( 'sc_currency_switcher', [ $this, 'shortcode_switcher' ] );

		// Frontend assets.
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );

		// Admin assets + menu.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

		// Admin AJAX.
		add_action( 'wp_ajax_sc_mc_save_currency', [ $this, 'ajax_save_currency' ] );
		add_action( 'wp_ajax_sc_mc_delete_currency', [ $this, 'ajax_delete_currency' ] );
		add_action( 'wp_ajax_sc_mc_set_default', [ $this, 'ajax_set_default' ] );
		add_action( 'wp_ajax_sc_mc_reorder', [ $this, 'ajax_reorder' ] );
		add_action( 'wp_ajax_sc_mc_seed', [ $this, 'ajax_seed' ] );
		add_action( 'wp_ajax_sc_mc_save_settings', [ $this, 'ajax_save_settings' ] );

		// Public AJAX: currency switch.
		add_action( 'wp_ajax_sc_switch_currency', [ $this, 'ajax_switch_currency' ] );
		add_action( 'wp_ajax_nopriv_sc_switch_currency', [ $this, 'ajax_switch_currency' ] );

		// LiteSpeed Cache: vary cache by active currency so each currency gets its own cached page.
		add_filter( 'litespeed_vary', [ $this, 'litespeed_vary_currency' ] );
	}

	public function init_currency(): void {
		if ( ! function_exists( 'WC' ) ) {
			return;
		}
		$code     = CurrencySession::resolve();
		$currency = $code ? CurrencyDB::get_by_code( $code ) : CurrencyDB::get_default();
		PriceConverter::set_active( $currency ?: null );
	}

	// =========================================================================
	// Payment gateway filtering
	// =========================================================================

	public function filter_currency_code( string $code ): string {
		$currency = PriceConverter::get_active();

		return $currency ? $currency['currency_code'] : $code;
	}

	// =========================================================================
	// Order: save currency meta (HPOS-compatible)
	// =========================================================================

	public function filter_currency_symbol( string $symbol, string $currency_code ): string {
		$currency = PriceConverter::get_active();
		if ( ! $currency ) {
			return $symbol;
		}

		return CurrencyDB::resolve_symbol( $currency['symbol'] );
	}

	// =========================================================================
	// Order display
	// =========================================================================

	public function filter_gateways( array $gateways ): array {
		$currency = PriceConverter::get_active();
		if ( ! $currency || empty( $currency['payment_gateways'] ) ) {
			return $gateways;
		}
		$allowed = json_decode( $currency['payment_gateways'], true );
		if ( ! is_array( $allowed ) || empty( $allowed ) ) {
			return $gateways; // empty = all gateways allowed
		}

		return array_intersect_key( $gateways, array_flip( $allowed ) );
	}

	public function save_order_currency( WC_Order $order ): void {
		$currency = CurrencySession::get_active_currency();
		if ( ! $currency ) {
			return;
		}
		$rate = (float) $currency['rate'] + (float) $currency['rate_modifier'];

		$order->update_meta_data( self::META_CURRENCY, $currency['currency_code'] );
		$order->update_meta_data( self::META_RATE, $rate );
		$order->update_meta_data( self::META_SYMBOL, CurrencyDB::resolve_symbol( $currency['symbol'] ) );

		// Base-currency total — useful for analytics / Stats module normalization.
		$total      = (float) $order->get_total();
		$base_total = $rate > 0 ? round( $total / $rate, 3 ) : $total;
		$order->update_meta_data( self::META_BASE_TOTAL, $base_total );
	}

	// =========================================================================
	// Print Orders integration
	// =========================================================================

	public function display_order_currency_admin( WC_Order $order ): void {
		$code   = $order->get_meta( self::META_CURRENCY );
		$rate   = $order->get_meta( self::META_RATE );
		$symbol = $order->get_meta( self::META_SYMBOL );
		if ( ! $code ) {
			return;
		}
		echo '<p><strong>' . esc_html__( 'Order Currency:', 'space-core' ) . '</strong> '
		     . esc_html( $code ) . ' (' . esc_html( $symbol ) . ')'
		     . ' &mdash; <em>' . esc_html__( 'Rate:', 'space-core' ) . ' ' . esc_html( number_format( (float) $rate, 6 ) ) . '</em></p>';
	}

	// =========================================================================
	// LocalShipping delivery fee conversion
	// =========================================================================

	/**
	 * Append a currency row to the order totals table on the frontend (thank-you page, my-account).
	 *
	 * @param array $totals
	 * @param WC_Order $order
	 * @param bool $tax_display
	 */
	public function append_currency_row_frontend( array $totals, WC_Order $order, bool $tax_display ): array {
		$code = $order->get_meta( self::META_CURRENCY );
		if ( ! $code ) {
			return $totals;
		}
		// Only add if the order currency differs from default.
		$default = CurrencyDB::get_default();
		if ( $default && $default['currency_code'] === $code ) {
			return $totals;
		}
		$totals['sc_order_currency'] = [
			'label' => __( 'Currency', 'space-core' ),
			'value' => esc_html( $code ),
		];

		return $totals;
	}

	// =========================================================================
	// WooCommerce shipping rate conversion
	// =========================================================================

	/**
	 * Hooked on sc_print_order_after_header — receives a WC_Order object.
	 * Reads meta from the order itself, not from the current session.
	 */
	public function print_order_currency_note( WC_Order $order ): void {
		$code   = $order->get_meta( self::META_CURRENCY );
		$rate   = $order->get_meta( self::META_RATE );
		$symbol = $order->get_meta( self::META_SYMBOL );
		if ( ! $code ) {
			return;
		}
		$default = CurrencyDB::get_default();
		if ( $default && $default['currency_code'] === $code ) {
			return; // Default currency — nothing extra to show.
		}
		echo '<p class="sc-print-currency" style="font-size:.85em;color:#555;">'
		     . esc_html__( 'Currency:', 'space-core' ) . ' <strong>' . esc_html( $code ) . '</strong>'
		     . ' &mdash; ' . esc_html__( 'Rate:', 'space-core' ) . ' ' . esc_html( number_format( (float) $rate, 4 ) )
		     . '</p>';
	}

	// =========================================================================
	// GiftWrap fee conversion
	// =========================================================================

	/**
	 * Filter: sc_local_shipping_fee
	 * LocalShipping module must call apply_filters('sc_local_shipping_fee', $fee) before adding to cart.
	 */
	public function convert_delivery_fee( float $fee ): float {
		return PriceConverter::convert( $fee );
	}

	// =========================================================================
	// Shortcode: [sc_currency_switcher]
	// =========================================================================

	/**
	 * Filter: woocommerce_package_rates
	 * Converts built-in WC shipping method costs (flat rate, local pickup, etc.) to the active currency.
	 *
	 * @param WC_Shipping_Rate[] $rates
	 * @param array $package
	 *
	 * @return WC_Shipping_Rate[]
	 */
	public function convert_package_rates( array $rates, array $package ): array {
		foreach ( $rates as $rate ) {
			$rate->set_cost( PriceConverter::convert( (float) $rate->get_cost() ) );

			$taxes = array_map(
				fn( $tax ) => PriceConverter::convert( (float) $tax ),
				$rate->get_taxes()
			);
			$rate->set_taxes( $taxes );
		}

		return $rates;
	}

	/**
	 * Filter: sc_gift_wrap_fee
	 * GiftWrap module must call apply_filters('sc_gift_wrap_fee', $fee) before adding to cart.
	 */
	public function convert_gift_wrap_fee( float $fee ): float {
		return PriceConverter::convert( $fee );
	}

	// =========================================================================
	// LiteSpeed Cache integration
	// =========================================================================

	/**
	 * Filter: litespeed_vary
	 *
	 * Appends the active currency code to LiteSpeed Cache's vary string so that
	 * each currency gets its own separate cached page. Without this, LiteSpeed
	 * would serve the same cached HTML regardless of the sc_currency cookie,
	 * meaning a currency switch would appear to have no effect.
	 *
	 * The cookie is read directly here because this filter fires before init_currency()
	 * populates PriceConverter, and because LiteSpeed Cache may evaluate it during
	 * its own boot phase before WordPress's 'init' action runs.
	 */
	public function litespeed_vary_currency( string $vary ): string {
		$code = strtoupper( sanitize_text_field( (string) ( $_COOKIE[ CurrencySession::COOKIE_NAME ] ?? '' ) ) );
		if ( ! $code ) {
			$default  = CurrencyDB::get_default();
			$code     = $default['currency_code'] ?? 'default';
		}
		return $vary . '|sc_mc_' . strtolower( $code );
	}

	public function shortcode_switcher( array $atts ): string {
		$cfg      = $this->get_config();
		$defaults = [
			'show_flag'   => ! empty( $cfg['shortcode_show_flag'] ) ? '1' : '0',
			'show_code'   => ! empty( $cfg['shortcode_show_code'] ) ? '1' : '0',
			'show_name'   => ! empty( $cfg['shortcode_show_name'] ) ? '1' : '0',
			'show_symbol' => ! empty( $cfg['shortcode_show_symbol'] ) ? '1' : '0',
		];
		$atts     = shortcode_atts( $defaults, $atts, 'sc_currency_switcher' );

		$show_flag   = '1' === $atts['show_flag'];
		$show_code   = '1' === $atts['show_code'];
		$show_name   = '1' === $atts['show_name'];
		$show_symbol = '1' === $atts['show_symbol'];

		$currencies = CurrencyDB::get_active();
		if ( count( $currencies ) < 2 ) {
			return ''; // Nothing to switch to.
		}

		$active_code = PriceConverter::get_active()['currency_code'] ?? '';
		$nonce       = wp_create_nonce( self::NONCE_FRONTEND );
		$options     = [];

		foreach ( $currencies as $currency ) {
			$code      = $currency['currency_code'];
			$name      = CurrencyDB::resolve_name( $currency['name'] );
			$symbol    = CurrencyDB::resolve_symbol( $currency['symbol'] );
			$flag_code = $show_flag ? $this->flag_iso2( $currency['country_codes'] ) : '';
			$label     = trim( implode( ' ', array_filter( [
				$show_code ? $code : '',
				$show_name ? $name : '',
				$show_symbol ? "({$symbol})" : '',
			] ) ) ) ?: $code;

			$options[] = [
				'value'     => $code,
				'label'     => $label,
				'flag_code' => $flag_code,
				'selected'  => $code === $active_code,
			];
		}

		return $this->view( 'front/switcher/dropdown', [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => $nonce,
			'options'  => $options,
		] );
	}

	// =========================================================================
	// AJAX: frontend currency switch
	// =========================================================================

	/**
	 * Return the first ISO2 country code (lowercase) from a JSON array, or ''.
	 * Used to build CSS flag-icon class names.
	 */
	private function flag_iso2( ?string $country_codes_json ): string {
		if ( ! $country_codes_json ) {
			return '';
		}
		$codes = json_decode( $country_codes_json, true );
		if ( ! is_array( $codes ) || empty( $codes ) ) {
			return '';
		}
		$iso2 = strtolower( trim( $codes[0] ) );

		return strlen( $iso2 ) === 2 ? $iso2 : '';
	}

	// =========================================================================
	// AJAX: admin currency CRUD
	// =========================================================================

	public function ajax_switch_currency(): void {
		check_ajax_referer( self::NONCE_FRONTEND, 'nonce' );
		$code = strtoupper( sanitize_text_field( wp_unslash( $_POST['currency'] ?? '' ) ) );
		if ( ! $code || ! CurrencySession::set_currency( $code ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid currency.', 'space-core' ) ] );
		}

		// LiteSpeed Cache compatibility:
		// The litespeed_vary filter fires at init (before this handler runs), so it
		// computes the vary hash from the OLD currency cookie. That means LSCACHE_VARY_COOKIE
		// in the browser still points to the old currency's cache bucket on the next reload.
		// Purging the referring URL forces LiteSpeed to run PHP on the reload, which lets
		// litespeed_vary fire with the NEW cookie and write the correct LSCACHE_VARY_COOKIE
		// into the response. All subsequent page navigations then use the right vary bucket.
		$referer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		if ( $referer ) {
			do_action( 'litespeed_purge_url', $referer );
		}

		wp_send_json_success();
	}

	public function ajax_save_currency(): void {
		$this->verify_admin_nonce();

		$id        = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$code      = strtoupper( sanitize_text_field( wp_unslash( $_POST['currency_code'] ?? '' ) ) );
		$name_en   = sanitize_text_field( wp_unslash( $_POST['name_en'] ?? '' ) );
		$name_ar   = sanitize_text_field( wp_unslash( $_POST['name_ar'] ?? '' ) );
		$symbol_en = sanitize_text_field( wp_unslash( $_POST['symbol_en'] ?? '' ) );
		$symbol_ar = sanitize_text_field( wp_unslash( $_POST['symbol_ar'] ?? '' ) );
		$rate      = (float) ( $_POST['rate'] ?? 1 );
		$modifier  = (float) ( $_POST['rate_modifier'] ?? 0 );
		$decimals  = absint( $_POST['decimal_digits'] ?? 2 );
		$position  = absint( $_POST['currency_position'] ?? CurrencyPosition::AfterSpace->value );
		$is_active = isset( $_POST['is_active'] ) ? (int) $_POST['is_active'] : 1;
		$sort      = isset( $_POST['sort_order'] ) ? (int) $_POST['sort_order'] : 0;

		// Countries and gateways are comma-separated strings from the UI.
		$countries_raw = sanitize_text_field( wp_unslash( $_POST['country_codes'] ?? '' ) );
		$gateways_raw  = sanitize_text_field( wp_unslash( $_POST['payment_gateways'] ?? '' ) );

		$country_codes = $countries_raw
			? array_values( array_filter( array_map( 'strtoupper', array_map( 'trim', explode( ',', $countries_raw ) ) ) ) )
			: null;
		$gateways      = $gateways_raw
			? array_values( array_filter( array_map( 'trim', explode( ',', $gateways_raw ) ) ) )
			: null;

		$data = [
			'currency_code'     => $code,
			'name'              => CurrencyDB::encode_json( [ 'en' => $name_en, 'ar' => $name_ar ] ),
			'symbol'            => CurrencyDB::encode_json( [ 'en' => $symbol_en, 'ar' => $symbol_ar ] ),
			'rate'              => $rate,
			'rate_modifier'     => $modifier,
			'decimal_digits'    => max( 0, min( 4, $decimals ) ),
			'currency_position' => $position,
			'is_active'         => $is_active,
			'sort_order'        => $sort,
			'country_codes'     => $country_codes ? CurrencyDB::encode_json( $country_codes ) : null,
			'payment_gateways'  => $gateways ? CurrencyDB::encode_json( $gateways ) : null,
		];

		if ( $id ) {
			CurrencyDB::update( $id, $data );
			wp_send_json_success( [ 'id' => $id ] );
		} else {
			$new_id = CurrencyDB::insert( $data );
			if ( $new_id ) {
				wp_send_json_success( [ 'id' => $new_id ] );
			} else {
				wp_send_json_error( [ 'message' => __( 'Could not save currency.', 'space-core' ) ] );
			}
		}
	}

	private function verify_admin_nonce(): void {
		check_ajax_referer( self::NONCE_ADMIN, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'space-core' ) ], 403 );
		}
	}

	public function ajax_delete_currency(): void {
		$this->verify_admin_nonce();
		$id     = absint( $_POST['id'] ?? 0 );
		$result = CurrencyDB::delete( $id );
		if ( true === $result ) {
			wp_send_json_success();
		} else {
			$msg = is_string( $result ) ? $result : __( 'Could not delete currency.', 'space-core' );
			wp_send_json_error( [ 'message' => $msg ] );
		}
	}

	public function ajax_set_default(): void {
		$this->verify_admin_nonce();
		$id = absint( $_POST['id'] ?? 0 );
		if ( ! $id || ! CurrencyDB::set_default( $id ) ) {
			wp_send_json_error( [ 'message' => __( 'Could not set default.', 'space-core' ) ] );
		}
		wp_send_json_success();
	}

	public function ajax_reorder(): void {
		$this->verify_admin_nonce();
		$ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : [];
		if ( empty( $ids ) ) {
			wp_send_json_error();
		}
		CurrencyDB::reorder( $ids );
		wp_send_json_success();
	}

	public function ajax_seed(): void {
		$this->verify_admin_nonce();
		$count = GCC::seed();
		if ( $count > 0 ) {
			wp_send_json_success( [
				'message' => sprintf(
				/* translators: %d: number of currencies seeded */
					__( 'Seeded %d currencies successfully.', 'space-core' ),
					$count
				),
				'count'   => $count,
			] );
		} else {
			wp_send_json_error( [ 'message' => __( 'Currencies already exist — seeder skipped.', 'space-core' ) ] );
		}
	}

	// =========================================================================
	// Assets
	// =========================================================================

	public function ajax_save_settings(): void {
		$this->verify_admin_nonce();

		$raw  = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : '{}'; // phpcs:ignore
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid data.', 'space-core' ) ] );
		}

		$old_cfg    = $this->get_config();
		$old_period = $old_cfg['rate_cron_period'] ?? 'daily';
		$new_period = sanitize_key( $data['rate_cron_period'] ?? 'daily' );
		if ( ! in_array( $new_period, [ 'hourly', 'twicedaily', 'daily' ], true ) ) {
			$new_period = 'daily';
		}

		$cfg = [
			'rate_api_url'           => esc_url_raw( $data['rate_api_url'] ?? '' ),
			'rate_api_key'           => sanitize_text_field( $data['rate_api_key'] ?? '' ),
			'rate_api_base_currency' => strtoupper( sanitize_text_field( $data['rate_api_base_currency'] ?? 'USD' ) ),
			'rate_cron_period'       => $new_period,
			'shortcode_show_flag'    => ! empty( $data['shortcode_show_flag'] ) ? 1 : 0,
			'shortcode_show_code'    => ! empty( $data['shortcode_show_code'] ) ? 1 : 0,
			'shortcode_show_name'    => ! empty( $data['shortcode_show_name'] ) ? 1 : 0,
			'shortcode_show_symbol'  => ! empty( $data['shortcode_show_symbol'] ) ? 1 : 0,
		];

		update_option( 'space_core_multi_currency', $cfg );

		// Reschedule cron if period changed.
		if ( $new_period !== $old_period ) {
			wp_clear_scheduled_hook( 'sc_fetch_currency_rates' );
			$this->schedule_cron( $new_period );
		}

		wp_send_json_success( [ 'message' => __( 'Settings saved.', 'space-core' ) ] );
	}

	public function enqueue_frontend_assets(): void {
		wp_enqueue_style(
			'flag-icons',
			'https://cdn.jsdelivr.net/gh/lipis/flag-icons@7.5.0/css/flag-icons.min.css',
			[],
			null
		);
		wp_enqueue_style(
			'sc-multi-currency',
			SPACE_CORE_URL . 'assets/css/multi-currency.css',
			[ 'flag-icons' ],
			SPACE_CORE_VERSION
		);
		wp_enqueue_script(
			'sc-multi-currency',
			SPACE_CORE_URL . 'assets/js/multi-currency.js',
			[ 'jquery' ],
			SPACE_CORE_VERSION,
			true
		);
		wp_localize_script( 'sc-multi-currency', 'scMC', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( self::NONCE_FRONTEND ),
		] );
	}

	// =========================================================================
	// Admin settings UI (render_settings — auto-tab via AdminMenu)
	// =========================================================================

	public function enqueue_admin_assets( string $hook ): void {
		// Only load on the Multi-Currency settings page (auto-generated by AdminMenu).
		if ( strpos( $hook, 'sc-multi-currency' ) === false && strpos( $hook, 'sc-multi_currency' ) === false ) {
			return;
		}
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_style(
			'sc-multi-currency-admin',
			SPACE_CORE_URL . 'assets/css/multi-currency.css',
			[ 'space-core-admin' ],
			SPACE_CORE_VERSION
		);
		wp_enqueue_script(
			'sc-multi-currency-admin',
			SPACE_CORE_URL . 'assets/js/multi-currency.js',
			[ 'jquery', 'jquery-ui-sortable', 'space-core-admin' ],
			SPACE_CORE_VERSION,
			true
		);
		wp_localize_script( 'sc-multi-currency-admin', 'scMCAdmin', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( self::NONCE_ADMIN ),
			'i18n'    => [
				'confirmDelete' => __( 'Delete this currency?', 'space-core' ),
				'saved'         => __( 'Saved!', 'space-core' ),
				'error'         => __( 'Error. Please try again.', 'space-core' ),
				'seeded'        => __( 'Currencies seeded!', 'space-core' ),
			],
		] );
	}

	// -------------------------------------------------------------------------
	// Tab: Currencies
	// -------------------------------------------------------------------------

	public function render_settings(): void {
		$tab       = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'currencies'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tabs      = [
			'currencies' => __( 'Currencies', 'space-core' ),
			'settings'   => __( 'Settings', 'space-core' ),
		];
		$page_slug = 'sc-multi-currency';
		$tab_html  = 'settings' === $tab ? $this->get_settings_tab_html() : $this->get_currencies_tab_html();
		echo $this->view( 'admin/page', [
			'tab'       => $tab,
			'tabs'      => $tabs,
			'page_slug' => $page_slug,
			'tab_html'  => $tab_html,
		] );
	}

	private function get_settings_tab_html(): string {
		$cfg     = $this->get_config();
		$periods = [
			'hourly'     => __( 'Hourly', 'space-core' ),
			'twicedaily' => __( 'Twice Daily', 'space-core' ),
			'daily'      => __( 'Daily', 'space-core' ),
		];

		return $this->view( 'admin/settings/tab', [
			'config'  => $cfg,
			'periods' => $periods,
		] );
	}

	// -------------------------------------------------------------------------
	// Tab: Settings
	// -------------------------------------------------------------------------

	private function get_currencies_tab_html(): string {
		$currencies = CurrencyDB::get_all();
		$positions  = CurrencyPosition::options();

		return $this->view( 'admin/currencies/tab', [
			'currencies' => $currencies,
			'positions'  => $positions,
		] );
	}

	/**
	 * Convert the country_codes JSON to a single flag emoji.
	 * Only renders if exactly one country code is present.
	 */
	private function country_flag( ?string $country_codes_json ): string {
		if ( ! $country_codes_json ) {
			return '';
		}
		$codes = json_decode( $country_codes_json, true );
		if ( ! is_array( $codes ) || count( $codes ) !== 1 ) {
			return '';
		}
		$iso2 = strtoupper( trim( $codes[0] ) );
		if ( strlen( $iso2 ) !== 2 ) {
			return '';
		}
		// Convert ISO2 to flag emoji via Unicode regional indicator symbols (U+1F1E6–U+1F1FF).
		$offset = 0x1F1E6 - ord( 'A' );

		return mb_chr( $offset + ord( $iso2[0] ) ) . mb_chr( $offset + ord( $iso2[1] ) );
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	private function render_currencies_tab(): void {
		echo $this->get_currencies_tab_html();
	}

	private function render_settings_tab(): void {
		echo $this->get_settings_tab_html();
	}
}
