<?php

namespace Space\Core\Modules\MultiCurrency;

defined( 'ABSPATH' ) || exit;

/**
 * Manages the active currency for the current request.
 *
 * Resolution priority (first match wins):
 *   1. Existing WC session value (sc_currency)
 *   2. Browser cookie sc_currency
 *   3. User meta _sc_currency (logged-in users with no session/cookie yet — cross-device)
 *   4. GeoIP detection via WC_Geolocation
 *   5. Default currency from DB
 *
 * Login does NOT change the active currency. user_meta is only written when
 * the user explicitly switches while logged in, providing cross-device memory.
 */
class CurrencySession {

	/** WC session key for the selected currency code. */
	const SESSION_KEY = 'sc_currency';

	/** Browser cookie name. */
	const COOKIE_NAME = 'sc_currency';

	/** User meta key for cross-device persistence. */
	const META_KEY = '_sc_currency';

	/** Cookie lifetime in seconds (30 days). */
	const COOKIE_TTL = 30 * DAY_IN_SECONDS;

	/** Cached active currency row for current request. */
	private static ?array $active = null;

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Resolve and cache the active currency for this request.
	 * Called once from Module::init_currency() on the 'init' action.
	 *
	 * @return string  The resolved currency code (ISO 4217).
	 */
	public static function resolve(): string {
		$code = self::resolve_code();

		// Validate the resolved code against active currencies.
		$currency = $code ? CurrencyDB::get_by_code( $code ) : null;
		if ( ! $currency || ! $currency['is_active'] ) {
			$currency = CurrencyDB::get_default();
		}

		self::$active = $currency ?: null;
		return $currency['currency_code'] ?? '';
	}

	/**
	 * Return the full active currency DB row for this request.
	 * Returns null if no currencies are configured.
	 */
	public static function get_active_currency(): ?array {
		return self::$active;
	}

	/**
	 * Explicitly set the active currency (called by the AJAX switch handler).
	 *
	 * Persists to WC session, browser cookie, and user meta (if logged in).
	 *
	 * @param string $code  ISO 4217 currency code (will be upper-cased).
	 * @return bool  false if the code is not a valid active currency.
	 */
	public static function set_currency( string $code ): bool {
		$code     = strtoupper( sanitize_text_field( $code ) );
		$currency = CurrencyDB::get_by_code( $code );

		if ( ! $currency || ! $currency['is_active'] ) {
			return false;
		}

		// WC session.
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( self::SESSION_KEY, $code );
		}

		// Browser cookie — set for 30 days.
		if ( ! headers_sent() ) {
			setcookie(
				self::COOKIE_NAME,
				$code,
				[
					'expires'  => time() + self::COOKIE_TTL,
					'path'     => COOKIEPATH ?: '/',
					'domain'   => COOKIE_DOMAIN ?: '',
					'secure'   => is_ssl(),
					'httponly' => false, // JS may read it for UI purposes
					'samesite' => 'Lax',
				]
			);
			$_COOKIE[ self::COOKIE_NAME ] = $code; // update for current request
		}

		// User meta (cross-device memory, only if logged in).
		$uid = get_current_user_id();
		if ( $uid ) {
			update_user_meta( $uid, self::META_KEY, $code );
		}

		// Update cached value.
		self::$active = $currency;

		return true;
	}

	/**
	 * Detect a suitable currency from the visitor's IP via WooCommerce GeoIP.
	 *
	 * @return string|null  ISO 4217 code if matched, null otherwise.
	 */
	public static function detect_by_geoip(): ?string {
		if ( ! class_exists( 'WC_Geolocation' ) ) {
			return null;
		}

		$geo     = \WC_Geolocation::geolocate_ip();
		$country = strtoupper( $geo['country'] ?? '' );
		if ( ! $country ) {
			return null;
		}

		$currency = CurrencyDB::get_by_country( $country );
		return $currency ? $currency['currency_code'] : null;
	}

	// -------------------------------------------------------------------------
	// Internal helpers
	// -------------------------------------------------------------------------

	/**
	 * Run the priority chain and return the raw resolved code string.
	 * Does not validate against DB — caller must validate.
	 */
	private static function resolve_code(): string {
		// 1. WC session.
		if ( function_exists( 'WC' ) && WC()->session ) {
			$from_session = WC()->session->get( self::SESSION_KEY );
			if ( $from_session ) {
				return (string) $from_session;
			}
		}

		// 2. Browser cookie.
		if ( ! empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return sanitize_text_field( (string) $_COOKIE[ self::COOKIE_NAME ] );
		}

		// 3. User meta (cross-device, for logged-in users with no session/cookie yet).
		$uid = get_current_user_id();
		if ( $uid ) {
			$from_meta = get_user_meta( $uid, self::META_KEY, true );
			if ( $from_meta ) {
				return (string) $from_meta;
			}
		}

		// 4. GeoIP (first visit — no session, no cookie, no user meta).
		$from_geo = self::detect_by_geoip();
		if ( $from_geo ) {
			return $from_geo;
		}

		// 5. Default currency.
		$default = CurrencyDB::get_default();
		return $default['currency_code'] ?? '';
	}
}
