<?php

namespace Space\Core\Modules\MultiCurrency;

defined( 'ABSPATH' ) || exit;

/**
 * Cron job: fetch exchange rates from a configured API and update the DB.
 *
 * Expected API response format (generic):
 *   { "rates": { "USD": 3.27, "EUR": 3.56, ... } }
 *
 * Only active, non-default currencies are updated.
 * The rate_modifier is never changed by the fetcher — it is solely admin-managed.
 */
class RateFetcher {

	/**
	 * Cron callback — registered via add_action('sc_fetch_currency_rates', ...).
	 */
	public static function run(): void {
		$cfg      = get_option( 'space_core_multi_currency', [] );
		$api_url  = trim( (string) ( $cfg['rate_api_url'] ?? '' ) );
		$api_key  = trim( (string) ( $cfg['rate_api_key'] ?? '' ) );
		$base     = strtoupper( trim( (string) ( $cfg['rate_api_base_currency'] ?? 'USD' ) ) );

		if ( ! $api_url ) {
			return; // Not configured.
		}

		$url      = add_query_arg( array_filter( [ 'base' => $base, 'apikey' => $api_key ] ), $api_url );
		$response = wp_remote_get( $url, [ 'timeout' => 15 ] );

		if ( is_wp_error( $response ) ) {
			error_log( '[Space Core Multi-Currency] Rate fetch error: ' . $response->get_error_message() ); // phpcs:ignore
			return;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== (int) $code ) {
			error_log( "[Space Core Multi-Currency] Rate fetch returned HTTP {$code}." ); // phpcs:ignore
			return;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || empty( $data['rates'] ) || ! is_array( $data['rates'] ) ) {
			error_log( '[Space Core Multi-Currency] Rate fetch: unexpected response format.' ); // phpcs:ignore
			return;
		}

		$api_rates = $data['rates'];

		// Fetch all active non-default currencies.
		$currencies = array_filter(
			CurrencyDB::get_active(),
			fn( $c ) => ! $c['is_default']
		);

		$default  = CurrencyDB::get_default();
		$base_cur = $default ? strtoupper( $default['currency_code'] ) : '';

		foreach ( $currencies as $currency ) {
			$code = strtoupper( $currency['currency_code'] );

			// If the API base is the store default currency, rates are direct.
			if ( $base === $base_cur ) {
				$new_rate = $api_rates[ $code ] ?? null;
			} else {
				// Cross-rate: convert via the API base.
				// rate(default→currency) = rate(base→currency) / rate(base→default)
				$rate_to_currency = $api_rates[ $code ] ?? null;
				$rate_to_default  = $api_rates[ $base_cur ] ?? null;
				if ( $rate_to_currency && $rate_to_default && $rate_to_default > 0 ) {
					$new_rate = $rate_to_currency / $rate_to_default;
				} else {
					$new_rate = null;
				}
			}

			if ( $new_rate !== null && $new_rate > 0 ) {
				CurrencyDB::update( (int) $currency['id'], [ 'rate' => round( (float) $new_rate, 6 ) ] );
			}
		}
	}
}
