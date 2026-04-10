<?php

namespace Space\Core\Modules\LocalShipping;

defined( 'ABSPATH' ) || exit;

/**
 * Database layer for the Local Shipping module.
 *
 * Tables:
 *   {prefix}sc_ls_cities  — id, name (JSON), is_active, sort_order
 *   {prefix}sc_ls_areas   — id, city_id, name (JSON), delivery_price,
 *                           express_fee, minimum_order, free_minimum_order,
 *                           is_active, sort_order
 */
class AreasDB {

    // -------------------------------------------------------------------------
    // Schema
    // -------------------------------------------------------------------------

    public static function create_tables(): void {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $cities_table = $wpdb->prefix . 'sc_ls_cities';
        $areas_table  = $wpdb->prefix . 'sc_ls_areas';

        $sql_cities = "CREATE TABLE IF NOT EXISTS {$cities_table} (
            id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name         TEXT         NOT NULL,
            country_code VARCHAR(2)   NOT NULL DEFAULT '',
            is_active    TINYINT(1)   NOT NULL DEFAULT 1,
            sort_order   INT          NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY country_code (country_code)
        ) {$charset_collate};";

        $sql_areas = "CREATE TABLE IF NOT EXISTS {$areas_table} (
            id                 INT UNSIGNED    NOT NULL AUTO_INCREMENT,
            city_id            INT UNSIGNED    NOT NULL,
            name               TEXT            NOT NULL,
            delivery_price     DECIMAL(10,3)   NOT NULL DEFAULT 0,
            express_fee        DECIMAL(10,3)   NOT NULL DEFAULT 0,
            minimum_order      DECIMAL(10,3)   NOT NULL DEFAULT 0,
            free_minimum_order DECIMAL(10,3)   NOT NULL DEFAULT 0,
            is_active          TINYINT(1)      NOT NULL DEFAULT 1,
            sort_order         INT             NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY city_id (city_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_cities );
        dbDelta( $sql_areas );
    }

    public static function drop_tables(): void {
        global $wpdb;
        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
        $wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'sc_ls_areas' );
        $wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'sc_ls_cities' );
        // phpcs:enable
    }

    // -------------------------------------------------------------------------
    // Helper: encode / decode multilingual name
    // -------------------------------------------------------------------------

    /**
     * Encode a name array (e.g. ['en'=>'Salmiya','ar'=>'السالمية']) to JSON string.
     */
    public static function encode_name( array $name ): string {
        return wp_json_encode( $name ) ?: '{}';
    }

    /**
     * Decode a JSON name string to array. Always returns at least ['en'=>''].
     */
    public static function decode_name( string $json ): array {
        $arr = json_decode( $json, true );
        return is_array( $arr ) ? $arr : [ 'en' => $json ];
    }

    /**
     * Resolve the display name for a decoded name array using the current locale.
     * Falls back to 'en', then the first available value.
     */
    public static function resolve_name( array $decoded ): string {
        $lang = substr( get_locale(), 0, 2 ); // e.g. 'ar' from 'ar_KW'
        return $decoded[ $lang ] ?? $decoded['en'] ?? reset( $decoded ) ?? '';
    }

    // -------------------------------------------------------------------------
    // Cities
    // -------------------------------------------------------------------------

    public static function get_cities( bool $active_only = false ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'sc_ls_cities';
        $where = $active_only ? 'WHERE is_active = 1' : '';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results( "SELECT * FROM {$table} {$where} ORDER BY sort_order ASC, id ASC", ARRAY_A ) ?: [];
    }

    /**
     * Get active cities filtered by country ISO2 code.
     * Cities with empty country_code are treated as matching any country (legacy).
     */
    public static function get_cities_by_country( string $country, bool $active_only = true ): array {
        global $wpdb;
        $table   = $wpdb->prefix . 'sc_ls_cities';
        $country = strtoupper( substr( $country, 0, 2 ) );
        $active  = $active_only ? ' AND is_active = 1' : '';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE (country_code = %s OR country_code = '') {$active} ORDER BY sort_order ASC, id ASC",
            $country
        ), ARRAY_A ) ?: [];
    }

    /**
     * True if any active city is configured for this country.
     */
    public static function country_has_cities( string $country ): bool {
        global $wpdb;
        $table   = $wpdb->prefix . 'sc_ls_cities';
        $country = strtoupper( substr( $country, 0, 2 ) );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE is_active = 1 AND (country_code = %s OR country_code = '')",
            $country
        ) );
        return $count > 0;
    }

    public static function get_city( int $id ): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'sc_ls_cities';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
        return $row ?: null;
    }

    /**
     * @param array{name: array, is_active?: int, sort_order?: int} $data
     */
    public static function insert_city( array $data ): int|false {
        global $wpdb;
        $result = $wpdb->insert(
            $wpdb->prefix . 'sc_ls_cities',
            [
                'name'         => self::encode_name( $data['name'] ),
                'country_code' => strtoupper( substr( (string) ( $data['country_code'] ?? '' ), 0, 2 ) ),
                'is_active'    => (int) ( $data['is_active'] ?? 1 ),
                'sort_order'   => (int) ( $data['sort_order'] ?? 0 ),
            ],
            [ '%s', '%s', '%d', '%d' ]
        );
        return $result ? (int) $wpdb->insert_id : false;
    }

    public static function update_city( int $id, array $data ): bool {
        global $wpdb;
        $fields = [];
        $formats = [];
        if ( isset( $data['name'] ) ) {
            $fields['name']  = self::encode_name( $data['name'] );
            $formats[]       = '%s';
        }
        if ( isset( $data['country_code'] ) ) {
            $fields['country_code'] = strtoupper( substr( (string) $data['country_code'], 0, 2 ) );
            $formats[]              = '%s';
        }
        if ( isset( $data['is_active'] ) ) {
            $fields['is_active'] = (int) $data['is_active'];
            $formats[]           = '%d';
        }
        if ( isset( $data['sort_order'] ) ) {
            $fields['sort_order'] = (int) $data['sort_order'];
            $formats[]            = '%d';
        }
        if ( empty( $fields ) ) {
            return false;
        }
        return (bool) $wpdb->update( $wpdb->prefix . 'sc_ls_cities', $fields, [ 'id' => $id ], $formats, [ '%d' ] );
    }

    public static function delete_city( int $id ): bool {
        global $wpdb;
        // Also delete all areas in this city.
        $wpdb->delete( $wpdb->prefix . 'sc_ls_areas', [ 'city_id' => $id ], [ '%d' ] );
        return (bool) $wpdb->delete( $wpdb->prefix . 'sc_ls_cities', [ 'id' => $id ], [ '%d' ] );
    }

    // -------------------------------------------------------------------------
    // Areas
    // -------------------------------------------------------------------------

    public static function get_areas( int $city_id, bool $active_only = false ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'sc_ls_areas';
        $where = $active_only ? 'AND is_active = 1' : '';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE city_id = %d {$where} ORDER BY sort_order ASC, id ASC", $city_id ),
            ARRAY_A
        ) ?: [];
    }

    public static function get_area( int $id ): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'sc_ls_areas';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
        return $row ?: null;
    }

    /**
     * Returns all active cities with their active areas, grouped.
     * [ city_id => [ 'city' => [...], 'areas' => [[...], ...] ], ... ]
     */
    public static function get_all_areas_grouped(): array {
        $cities = self::get_cities( true );
        $result = [];
        foreach ( $cities as $city ) {
            $areas = self::get_areas( (int) $city['id'], true );
            if ( ! empty( $areas ) ) {
                $result[ $city['id'] ] = [
                    'city'  => $city,
                    'areas' => $areas,
                ];
            }
        }
        return $result;
    }

    /**
     * @param array{city_id: int, name: array, delivery_price?: float, ...} $data
     */
    public static function insert_area( array $data ): int|false {
        global $wpdb;
        $result = $wpdb->insert(
            $wpdb->prefix . 'sc_ls_areas',
            [
                'city_id'            => (int) $data['city_id'],
                'name'               => self::encode_name( $data['name'] ),
                'delivery_price'     => (float) ( $data['delivery_price'] ?? 0 ),
                'express_fee'        => (float) ( $data['express_fee'] ?? 0 ),
                'minimum_order'      => (float) ( $data['minimum_order'] ?? 0 ),
                'free_minimum_order' => (float) ( $data['free_minimum_order'] ?? 0 ),
                'is_active'          => (int) ( $data['is_active'] ?? 1 ),
                'sort_order'         => (int) ( $data['sort_order'] ?? 0 ),
            ],
            [ '%d', '%s', '%f', '%f', '%f', '%f', '%d', '%d' ]
        );
        return $result ? (int) $wpdb->insert_id : false;
    }

    public static function update_area( int $id, array $data ): bool {
        global $wpdb;
        $fields  = [];
        $formats = [];

        $map = [
            'city_id'            => [ '%d', 'int' ],
            'delivery_price'     => [ '%f', 'float' ],
            'express_fee'        => [ '%f', 'float' ],
            'minimum_order'      => [ '%f', 'float' ],
            'free_minimum_order' => [ '%f', 'float' ],
            'is_active'          => [ '%d', 'int' ],
            'sort_order'         => [ '%d', 'int' ],
        ];

        foreach ( $map as $key => [ $fmt, $cast ] ) {
            if ( isset( $data[ $key ] ) ) {
                $fields[ $key ] = $cast === 'int' ? (int) $data[ $key ] : (float) $data[ $key ];
                $formats[]      = $fmt;
            }
        }

        if ( isset( $data['name'] ) ) {
            $fields['name'] = self::encode_name( $data['name'] );
            $formats[]      = '%s';
        }

        if ( empty( $fields ) ) {
            return false;
        }

        return (bool) $wpdb->update( $wpdb->prefix . 'sc_ls_areas', $fields, [ 'id' => $id ], $formats, [ '%d' ] );
    }

    public static function delete_area( int $id ): bool {
        global $wpdb;
        return (bool) $wpdb->delete( $wpdb->prefix . 'sc_ls_areas', [ 'id' => $id ], [ '%d' ] );
    }
}
