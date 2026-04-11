<?php

namespace Space\Core\Modules\MultiCurrency\Seeders;

defined( 'ABSPATH' ) || exit;

use Space\Core\Modules\MultiCurrency\CurrencyDB;
use Space\Core\Modules\MultiCurrency\CurrencyPosition;

/**
 * Seeds the GCC + global currencies.
 *
 * Rates are relative to KWD as the default currency
 * (i.e. how many of each currency equals 1 KWD).
 *
 * Runs only when the currencies table is completely empty (idempotent).
 */
class GCC {

	/**
	 * Seed all currencies. Returns the count of inserted rows.
	 */
	public static function seed(): int {
		// Only seed if the table is empty.
		if ( ! empty( CurrencyDB::get_all() ) ) {
			return 0;
		}

		$currencies = self::data();
		$inserted   = 0;

		foreach ( $currencies as $row ) {
			$id = CurrencyDB::insert( [
				'name'              => CurrencyDB::encode_json( $row['name'] ),
				'currency_code'     => $row['code'],
				'symbol'            => CurrencyDB::encode_json( $row['symbol'] ),
				'rate'              => $row['rate'],
				'rate_modifier'     => 0,
				'decimal_digits'    => $row['decimals'],
				'currency_position' => $row['position']->value,
				'is_default'        => $row['is_default'] ? 1 : 0,
				'is_active'         => 1,
				'sort_order'        => $row['sort'],
				'country_codes'     => isset( $row['countries'] ) ? CurrencyDB::encode_json( $row['countries'] ) : null,
				'payment_gateways'  => null,
			] );

			if ( $id ) {
				$inserted++;
			}
		}

		return $inserted;
	}

	// -------------------------------------------------------------------------
	// Seed data
	// -------------------------------------------------------------------------

	private static function data(): array {
		return [
			[
				'code'       => 'KWD',
				'name'       => [ 'en' => 'Kuwaiti Dinar', 'ar' => 'دينار كويتي' ],
				'symbol'     => [ 'en' => 'KD', 'ar' => 'د.ك' ],
				'rate'       => 1.0,
				'decimals'   => 3,
				'position'   => CurrencyPosition::AfterSpace,
				'is_default' => true,
				'sort'       => 0,
				'countries'  => [ 'KW' ],
			],
			[
				'code'      => 'SAR',
				'name'      => [ 'en' => 'Saudi Riyal', 'ar' => 'ريال سعودي' ],
				'symbol'    => [ 'en' => 'SR', 'ar' => 'ر.س' ],
				'rate'      => 12.05,
				'decimals'  => 2,
				'position'  => CurrencyPosition::AfterSpace,
				'is_default' => false,
				'sort'      => 1,
				'countries' => [ 'SA' ],
			],
			[
				'code'      => 'AED',
				'name'      => [ 'en' => 'UAE Dirham', 'ar' => 'درهم إماراتي' ],
				'symbol'    => [ 'en' => 'AED', 'ar' => 'د.إ' ],
				'rate'      => 12.20,
				'decimals'  => 2,
				'position'  => CurrencyPosition::AfterSpace,
				'is_default' => false,
				'sort'      => 2,
				'countries' => [ 'AE' ],
			],
			[
				'code'      => 'QAR',
				'name'      => [ 'en' => 'Qatari Riyal', 'ar' => 'ريال قطري' ],
				'symbol'    => [ 'en' => 'QR', 'ar' => 'ر.ق' ],
				'rate'      => 12.11,
				'decimals'  => 2,
				'position'  => CurrencyPosition::AfterSpace,
				'is_default' => false,
				'sort'      => 3,
				'countries' => [ 'QA' ],
			],
			[
				'code'      => 'BHD',
				'name'      => [ 'en' => 'Bahraini Dinar', 'ar' => 'دينار بحريني' ],
				'symbol'    => [ 'en' => 'BD', 'ar' => 'د.ب' ],
				'rate'      => 1.26,
				'decimals'  => 3,
				'position'  => CurrencyPosition::AfterSpace,
				'is_default' => false,
				'sort'      => 4,
				'countries' => [ 'BH' ],
			],
			[
				'code'      => 'OMR',
				'name'      => [ 'en' => 'Omani Rial', 'ar' => 'ريال عُماني' ],
				'symbol'    => [ 'en' => 'OMR', 'ar' => 'ر.ع' ],
				'rate'      => 1.28,
				'decimals'  => 3,
				'position'  => CurrencyPosition::AfterSpace,
				'is_default' => false,
				'sort'      => 5,
				'countries' => [ 'OM' ],
			],
			[
				'code'      => 'USD',
				'name'      => [ 'en' => 'US Dollar', 'ar' => 'دولار أمريكي' ],
				'symbol'    => [ 'en' => '$', 'ar' => '$' ],
				'rate'      => 3.27,
				'decimals'  => 2,
				'position'  => CurrencyPosition::Before,
				'is_default' => false,
				'sort'      => 6,
				'countries' => [ 'US' ],
			],
			[
				'code'      => 'EUR',
				'name'      => [ 'en' => 'Euro', 'ar' => 'يورو' ],
				'symbol'    => [ 'en' => '€', 'ar' => '€' ],
				'rate'      => 3.56,
				'decimals'  => 2,
				'position'  => CurrencyPosition::Before,
				'is_default' => false,
				'sort'      => 7,
			],
		];
	}
}
