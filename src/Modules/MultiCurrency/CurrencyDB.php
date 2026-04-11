<?php

namespace Space\Core\Modules\MultiCurrency;

defined( 'ABSPATH' ) || exit;

/**
 * Database layer for the Multi-Currency module.
 *
 * Table: {prefix}sc_mc_currencies
 *   id                INT UNSIGNED    PK AUTO_INCREMENT
 *   name              TEXT            JSON {"en":"...","ar":"..."}
 *   currency_code     VARCHAR(3)      ISO 4217 — unique
 *   symbol            TEXT            JSON {"en":"KD","ar":"د.ك"}
 *   rate              DECIMAL(15,6)   units of this currency per 1 unit of default
 *   rate_modifier     DECIMAL(15,6)   added to rate (may be negative)
 *   decimal_digits    TINYINT         2 or 3
 *   currency_position TINYINT         CurrencyPosition enum value
 *   is_default        TINYINT(1)
 *   is_active         TINYINT(1)
 *   sort_order        INT
 *   country_codes     TEXT            JSON ["KW","SA"] — for GeoIP auto-switch; NULL = none
 *   payment_gateways  TEXT            JSON ["stripe"] — NULL / [] = all gateways allowed
 */
class CurrencyDB {

	// -------------------------------------------------------------------------
	// Schema
	// -------------------------------------------------------------------------

	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'sc_mc_currencies';
	}

	public static function create_table(): void {
		global $wpdb;
		$table           = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
			id                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
			name              TEXT          NOT NULL,
			currency_code     VARCHAR(3)    NOT NULL,
			symbol            TEXT          NOT NULL,
			rate              DECIMAL(15,6) NOT NULL DEFAULT 1.000000,
			rate_modifier     DECIMAL(15,6) NOT NULL DEFAULT 0.000000,
			decimal_digits    TINYINT       NOT NULL DEFAULT 2,
			currency_position TINYINT       NOT NULL DEFAULT 1,
			is_default        TINYINT(1)    NOT NULL DEFAULT 0,
			is_active         TINYINT(1)    NOT NULL DEFAULT 1,
			sort_order        INT           NOT NULL DEFAULT 0,
			country_codes     TEXT          NULL,
			payment_gateways  TEXT          NULL,
			PRIMARY KEY (id),
			UNIQUE KEY currency_code (currency_code)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	// -------------------------------------------------------------------------
	// Read
	// -------------------------------------------------------------------------

	/** All currencies ordered by sort_order. */
	public static function get_all(): array {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY sort_order ASC, id ASC", ARRAY_A ) ?: [];
	}

	/** Active currencies ordered by sort_order. */
	public static function get_active(): array {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( "SELECT * FROM {$table} WHERE is_active = 1 ORDER BY sort_order ASC, id ASC", ARRAY_A ) ?: [];
	}

	/** The default currency row, or null if none set. */
	public static function get_default(): ?array {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( "SELECT * FROM {$table} WHERE is_default = 1 LIMIT 1", ARRAY_A );
		return $row ?: null;
	}

	/** Find a currency by ISO code (case-insensitive). Returns null if not found. */
	public static function get_by_code( string $code ): ?array {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE currency_code = %s LIMIT 1", strtoupper( $code ) ), ARRAY_A );
		return $row ?: null;
	}

	/** Find the first active currency whose country_codes JSON contains $country_iso2. */
	public static function get_by_country( string $country ): ?array {
		$country = strtoupper( substr( $country, 0, 2 ) );
		if ( ! $country ) {
			return null;
		}
		foreach ( self::get_active() as $row ) {
			if ( empty( $row['country_codes'] ) ) {
				continue;
			}
			$codes = json_decode( $row['country_codes'], true );
			if ( is_array( $codes ) && in_array( $country, array_map( 'strtoupper', $codes ), true ) ) {
				return $row;
			}
		}
		return null;
	}

	// -------------------------------------------------------------------------
	// Write
	// -------------------------------------------------------------------------

	/**
	 * Insert a new currency row.
	 *
	 * @param array $data Associative array of column => value.
	 * @return int|false  New row ID on success, false on failure.
	 */
	public static function insert( array $data ): int|false {
		global $wpdb;
		$row = self::prepare_row( $data );
		if ( empty( $row ) ) {
			return false;
		}
		$result = $wpdb->insert( self::table_name(), $row['values'], $row['formats'] ); // phpcs:ignore
		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update a currency row by ID.
	 *
	 * @param int   $id
	 * @param array $data Associative array of column => value.
	 * @return bool
	 */
	public static function update( int $id, array $data ): bool {
		global $wpdb;
		$row = self::prepare_row( $data );
		if ( empty( $row ) ) {
			return false;
		}
		return (bool) $wpdb->update( // phpcs:ignore
			self::table_name(),
			$row['values'],
			[ 'id' => $id ],
			$row['formats'],
			[ '%d' ]
		);
	}

	/**
	 * Delete a currency by ID. Refuses to delete the default currency.
	 *
	 * @return bool|string  true on success, error message string if blocked.
	 */
	public static function delete( int $id ): bool|string {
		global $wpdb;
		$row = self::get_by_id( $id );
		if ( ! $row ) {
			return false;
		}
		if ( $row['is_default'] ) {
			return __( 'Cannot delete the default currency.', 'space-core' );
		}
		return (bool) $wpdb->delete( self::table_name(), [ 'id' => $id ], [ '%d' ] ); // phpcs:ignore
	}

	/**
	 * Set a currency as the default (clears previous default first).
	 */
	public static function set_default( int $id ): bool {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "UPDATE {$table} SET is_default = 0" );
		return (bool) $wpdb->update( self::table_name(), [ 'is_default' => 1 ], [ 'id' => $id ], [ '%d' ], [ '%d' ] ); // phpcs:ignore
	}

	/**
	 * Bulk-update sort_order for a list of IDs.
	 *
	 * @param int[] $ids Ordered array — index 0 gets sort_order 0, etc.
	 */
	public static function reorder( array $ids ): void {
		global $wpdb;
		foreach ( $ids as $index => $id ) {
			$wpdb->update( self::table_name(), [ 'sort_order' => $index ], [ 'id' => (int) $id ], [ '%d' ], [ '%d' ] ); // phpcs:ignore
		}
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	public static function get_by_id( int $id ): ?array {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id ), ARRAY_A );
		return $row ?: null;
	}

	/**
	 * Decode a JSON name/symbol column to an array.
	 * Always returns at least ['en' => ''].
	 */
	public static function decode_json( string $json ): array {
		$arr = json_decode( $json, true );
		return is_array( $arr ) ? $arr : [ 'en' => $json ];
	}

	/**
	 * Encode a locale-keyed array to a JSON string for storage.
	 */
	public static function encode_json( array $data ): string {
		return wp_json_encode( $data ) ?: '{}';
	}

	/**
	 * Resolve a decoded name/symbol array for the current site locale.
	 * Falls back to 'en', then the first available value.
	 */
	public static function resolve_locale( array $decoded ): string {
		$lang = substr( get_locale(), 0, 2 );
		return $decoded[ $lang ] ?? $decoded['en'] ?? reset( $decoded ) ?? '';
	}

	/**
	 * Resolve the display symbol for a currency row using the current locale.
	 *
	 * @param string $symbol_json The raw JSON string from the DB symbol column.
	 */
	public static function resolve_symbol( string $symbol_json ): string {
		return self::resolve_locale( self::decode_json( $symbol_json ) );
	}

	/**
	 * Resolve the display name for a currency row using the current locale.
	 *
	 * @param string $name_json The raw JSON string from the DB name column.
	 */
	public static function resolve_name( string $name_json ): string {
		return self::resolve_locale( self::decode_json( $name_json ) );
	}

	/**
	 * Map input data array to DB column values + sprintf formats.
	 * Only includes keys that are valid column names.
	 *
	 * @return array{values: array, formats: array}
	 */
	private static function prepare_row( array $data ): array {
		$values  = [];
		$formats = [];

		$string_cols  = [ 'name', 'currency_code', 'symbol', 'country_codes', 'payment_gateways' ];
		$decimal_cols = [ 'rate', 'rate_modifier' ];
		$int_cols     = [ 'decimal_digits', 'currency_position', 'is_default', 'is_active', 'sort_order' ];

		foreach ( $string_cols as $col ) {
			if ( array_key_exists( $col, $data ) ) {
				$values[ $col ] = $data[ $col ]; // already sanitized by caller
				$formats[]      = '%s';
			}
		}
		foreach ( $decimal_cols as $col ) {
			if ( array_key_exists( $col, $data ) ) {
				$values[ $col ] = (float) $data[ $col ];
				$formats[]      = '%f';
			}
		}
		foreach ( $int_cols as $col ) {
			if ( array_key_exists( $col, $data ) ) {
				$values[ $col ] = (int) $data[ $col ];
				$formats[]      = '%d';
			}
		}

		return [ 'values' => $values, 'formats' => $formats ];
	}
}
