<?php

namespace Space\Core\Modules\MultiCurrency;

defined( 'ABSPATH' ) || exit;

/**
 * Handles price conversion and WC price-filter callbacks.
 *
 * All prices stored in WooCommerce are in the default (base) currency.
 * Conversion happens at render time only — no DB values are changed.
 *
 * Effective rate = currency.rate + currency.rate_modifier
 * Converted price = original_price × effective_rate
 */
class PriceConverter {

	/** Currently active currency row (set once per request by Module::init_currency). */
	private static ?array $active = null;

	/** Whether conversion is currently active (false for admin, REST, etc.). */
	private static bool $enabled = false;

	// -------------------------------------------------------------------------
	// Bootstrapping
	// -------------------------------------------------------------------------

	/**
	 * Cache the active currency for this request and enable conversion.
	 * Called from Module::init_currency().
	 */
	public static function set_active( ?array $currency ): void {
		self::$active  = $currency;
		self::$enabled = $currency !== null && ! $currency['is_default'];
	}

	/** Return the cached active currency row. */
	public static function get_active(): ?array {
		return self::$active;
	}

	/** True when conversion is needed (active currency differs from default). */
	public static function is_converting(): bool {
		return self::$enabled;
	}

	// -------------------------------------------------------------------------
	// Conversion math
	// -------------------------------------------------------------------------

	/**
	 * Effective exchange rate for a currency row.
	 *
	 * @param array $currency  A DB row from CurrencyDB.
	 * @return float
	 */
	public static function get_effective_rate( array $currency ): float {
		return (float) $currency['rate'] + (float) $currency['rate_modifier'];
	}

	/**
	 * Convert a base-currency price to the active currency.
	 *
	 * @param float $price  Price in the store's default currency.
	 * @return float  Converted price.
	 */
	public static function convert( float $price ): float {
		if ( ! self::$enabled || ! self::$active ) {
			return $price;
		}
		return $price * self::get_effective_rate( self::$active );
	}

	/**
	 * Convert a price using a specific currency row (not necessarily the active one).
	 */
	public static function convert_with( float $price, array $currency ): float {
		$rate = self::get_effective_rate( $currency );
		return $rate > 0 ? $price * $rate : $price;
	}

	/**
	 * Convert a price FROM a given effective rate BACK to the base (default) currency.
	 *
	 * Use this when an order was placed in a non-default currency and you need
	 * to display the equivalent base-currency amount — e.g. on print pages when
	 * "Default Currency" mode is selected.
	 *
	 * Formula: base_amount = order_amount / effective_rate
	 *
	 * @param float $price          Price in the non-default currency.
	 * @param float $effective_rate The rate that was used at order time (rate + rate_modifier).
	 * @return float  Price in the base currency.
	 */
	public static function convert_to_base( float $price, float $effective_rate ): float {
		return $effective_rate > 0 ? $price / $effective_rate : $price;
	}

	// -------------------------------------------------------------------------
	// WooCommerce filter callbacks
	// -------------------------------------------------------------------------

	/**
	 * Filter callback for woocommerce_product_get_price and variants.
	 * Hooked with priority 10, 2 args.
	 *
	 * @param string|float $price    Raw price (may be empty string for no price).
	 * @param \WC_Product  $product  The product object (unused, but required by WC).
	 * @return string|float
	 */
	public static function filter_price( $price, $product ) {
		if ( '' === $price || ! self::$enabled ) {
			return $price;
		}
		return self::convert( (float) $price );
	}

	/**
	 * Filter callback for wc_price_args.
	 * Overrides the decimal count, symbol, and price_format based on the active currency.
	 *
	 * @param array $args  Default wc_price() args.
	 * @return array
	 */
	public static function filter_price_args( array $args ): array {
		$currency = self::$active;
		if ( ! $currency ) {
			return $args;
		}

		$symbol   = CurrencyDB::resolve_symbol( $currency['symbol'] );
		$decimals = (int) $currency['decimal_digits'];
		$position = CurrencyPosition::tryFrom( (int) $currency['currency_position'] ) ?? CurrencyPosition::AfterSpace;

		// Build WC price_format string using a placeholder.
		$price_format = match ( $position ) {
			CurrencyPosition::Before      => '%1$s%2$s',
			CurrencyPosition::After       => '%2$s%1$s',
			CurrencyPosition::BeforeSpace => '%1$s&nbsp;%2$s',
			CurrencyPosition::AfterSpace  => '%2$s&nbsp;%1$s',
		};

		$args['currency_symbol'] = $symbol;
		$args['decimals']        = $decimals;
		$args['price_format']    = $price_format;

		return $args;
	}

	// -------------------------------------------------------------------------
	// Standalone formatter (for print/admin display)
	// -------------------------------------------------------------------------

	/**
	 * Format an amount using a given currency row's settings.
	 *
	 * @param float  $amount    The price in the currency's own units.
	 * @param array  $currency  A DB row from CurrencyDB.
	 * @return string  HTML-safe formatted price string.
	 */
	public static function format_price( float $amount, array $currency ): string {
		$symbol    = CurrencyDB::resolve_symbol( $currency['symbol'] );
		$decimals  = (int) $currency['decimal_digits'];
		$position  = CurrencyPosition::tryFrom( (int) $currency['currency_position'] ) ?? CurrencyPosition::AfterSpace;
		$formatted = number_format( $amount, $decimals );
		return esc_html( $position->format( $formatted, $symbol ) );
	}
}
