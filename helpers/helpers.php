<?php

/**
 * Debug helpers — dd() and dump().
 *
 * Available in all contexts (admin, frontend, CLI, cron).
 * Guarded with function_exists() so they never clash with other plugins.
 */

use Space\Core\Modules\MultiCurrency\PriceConverter;

if ( ! function_exists( 'dump' ) ) {
	/**
	 * Pretty-print one or more values.
	 */
	function dump( mixed ...$values ): void {
		foreach ( $values as $value ) {
			echo '<pre style="background:#1e1e1e;color:#d4d4d4;padding:12px 16px;margin:8px 0;border-radius:4px;font-size:13px;overflow:auto;text-align:left;">';
			var_dump( $value );
			echo '</pre>';
		}
	}
}

if ( ! function_exists( 'dd' ) ) {
	/**
	 * Dump one or more values then die.
	 */
	function dd( mixed ...$values ): never {
		dump( ...$values );
		die();
	}
}

if ( ! function_exists( 'cc_amount' ) ) {
	/**
	 * Convert amount to current currency
	 */
	function cc_amount( ?float $amount = null ): float {
		if ( ! $amount ) {
			return 0;
		}
		if ( ! class_exists( PriceConverter::class ) ) {
			return $amount;
		}

		return PriceConverter::convert( $amount );
	}
}

if ( ! function_exists( 'c2b_amount' ) ) {
	/**
	 * Convert amount to base currency
	 */
	function c2b_amount( ?float $amount = null, float $rate = 1 ): float {
		if ( ! $amount ) {
			return 0;
		}
		if ( ! class_exists( PriceConverter::class ) ) {
			return $amount;
		}
		if ( $rate === 0 ) {
			return $amount;
		}

		return PriceConverter::convert_to_base( $amount, $rate );
	}
}
