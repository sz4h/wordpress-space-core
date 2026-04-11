<?php

namespace Space\Core\Modules\MultiCurrency;

defined( 'ABSPATH' ) || exit;

/**
 * Backed enum for currency symbol position.
 *
 * Stored as TINYINT in the database. Use ::from(int) to hydrate from DB,
 * ->value to persist back, and ->format() to render a price string.
 */
enum CurrencyPosition: int {

	/** Symbol directly before amount — e.g. "KD100" */
	case Before = 1;

	/** Symbol directly after amount — e.g. "100KD" */
	case After = 2;

	/** Symbol before amount with a space — e.g. "KD 100" */
	case BeforeSpace = 3;

	/** Symbol after amount with a space — e.g. "100 KD" */
	case AfterSpace = 4;

	/**
	 * Format an already-number_format()-ted amount string with the symbol.
	 *
	 * @param string $formatted  The pre-formatted number string (e.g. "1,200.500").
	 * @param string $symbol     The currency symbol resolved for the current locale.
	 * @return string
	 */
	public function format( string $formatted, string $symbol ): string {
		return match ( $this ) {
			self::Before      => $symbol . $formatted,
			self::After       => $formatted . $symbol,
			self::BeforeSpace => $symbol . "\u{00A0}" . $formatted,
			self::AfterSpace  => $formatted . "\u{00A0}" . $symbol,
		};
	}

	/**
	 * Human-readable label for admin UI selects.
	 */
	public function label(): string {
		return match ( $this ) {
			self::Before      => __( 'Before (KD100)', 'space-core' ),
			self::After       => __( 'After (100KD)', 'space-core' ),
			self::BeforeSpace => __( 'Before with space (KD 100)', 'space-core' ),
			self::AfterSpace  => __( 'After with space (100 KD)', 'space-core' ),
		};
	}

	/** Returns all cases as value => label for admin <select> rendering. */
	public static function options(): array {
		$out = [];
		foreach ( self::cases() as $case ) {
			$out[ $case->value ] = $case->label();
		}
		return $out;
	}
}
