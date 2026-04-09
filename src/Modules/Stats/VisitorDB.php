<?php

namespace Space\Core\Modules\Stats;

defined( 'ABSPATH' ) || exit;

/**
 * Visitor tracking database.
 *
 * Table: {prefix}sc_visitors
 * Resolves country via ip-api.com (free, non-commercial tier, called lazily).
 */
class VisitorDB {

    public static function create_table(): void {
        global $wpdb;
        $table           = $wpdb->prefix . 'sc_visitors';
        $charset_collate = $wpdb->get_charset_collate();
        $sql             = "CREATE TABLE IF NOT EXISTS {$table} (
            id           BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
            ip           VARCHAR(45)      NOT NULL DEFAULT '',
            country_code VARCHAR(5)       NOT NULL DEFAULT '',
            country_name VARCHAR(100)     NOT NULL DEFAULT '',
            page_url     VARCHAR(512)     NOT NULL DEFAULT '',
            is_unique    TINYINT(1)       NOT NULL DEFAULT 1,
            visited_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY ip (ip),
            KEY visited_at (visited_at),
            KEY country_code (country_code)
        ) {$charset_collate};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public static function drop_table(): void {
        global $wpdb;
        $wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'sc_visitors' ); // phpcs:ignore
    }

    /**
     * Record a page view. Country is resolved from IP asynchronously (stored empty, filled by cron).
     */
    public static function record( string $ip, string $url ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'sc_visitors';

        // Mark unique: first visit of this IP today.
        $today    = current_time( 'Y-m-d' );
        $is_unique = ! $wpdb->get_var( // phpcs:ignore
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE ip = %s AND visited_at >= %s LIMIT 1", // phpcs:ignore
                $ip, $today . ' 00:00:00'
            )
        );

        $wpdb->insert( $table, [
            'ip'        => $ip,
            'page_url'  => substr( $url, 0, 512 ),
            'is_unique' => $is_unique ? 1 : 0,
            'visited_at' => current_time( 'mysql' ),
        ], [ '%s', '%s', '%d', '%s' ] );
    }

    /**
     * Resolve country for rows that still have empty country_code.
     * Called by WP-Cron (sc_resolve_visitor_countries).
     * Uses ip-api.com batch endpoint — free for < 45 req/min.
     */
    public static function resolve_pending_countries(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'sc_visitors';

        // Get unique IPs without country info (max 50 at a time).
        $ips = $wpdb->get_col( // phpcs:ignore
            "SELECT DISTINCT ip FROM {$table} WHERE country_code = '' LIMIT 50"
        );
        if ( empty( $ips ) ) return;

        // ip-api.com batch endpoint.
        $payload = array_map( fn( $ip ) => [ 'query' => $ip ], $ips );
        $response = wp_remote_post( 'http://ip-api.com/batch?fields=status,query,countryCode,country', [
            'timeout' => 10,
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( $payload ),
        ] );

        if ( is_wp_error( $response ) ) return;

        $results = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $results ) ) return;

        foreach ( $results as $r ) {
            if ( ( $r['status'] ?? '' ) !== 'success' ) continue;
            $wpdb->query( // phpcs:ignore
                $wpdb->prepare(
                    "UPDATE {$table} SET country_code = %s, country_name = %s WHERE ip = %s AND country_code = ''", // phpcs:ignore
                    $r['countryCode'] ?? '', $r['country'] ?? '', $r['query'] ?? ''
                )
            );
        }
    }

    // ── Statistics queries ────────────────────────────────────────

    public static function total_visitors(): int {
        global $wpdb;
        return (int) $wpdb->get_var( 'SELECT COUNT(DISTINCT ip) FROM ' . $wpdb->prefix . 'sc_visitors' ); // phpcs:ignore
    }

    public static function today_visitors(): int {
        global $wpdb;
        $today = current_time( 'Y-m-d' );
        return (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore
            'SELECT COUNT(DISTINCT ip) FROM ' . $wpdb->prefix . 'sc_visitors WHERE visited_at >= %s',
            $today . ' 00:00:00'
        ) );
    }

    public static function total_pageviews(): int {
        global $wpdb;
        return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'sc_visitors' ); // phpcs:ignore
    }

    public static function today_pageviews(): int {
        global $wpdb;
        $today = current_time( 'Y-m-d' );
        return (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore
            'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'sc_visitors WHERE visited_at >= %s',
            $today . ' 00:00:00'
        ) );
    }

    /**
     * Top countries by visitor count.
     * Returns [ ['country_code', 'country_name', 'count'], ... ]
     */
    public static function top_visitor_countries( int $limit = 10 ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'sc_visitors';
        return $wpdb->get_results( $wpdb->prepare( // phpcs:ignore
            "SELECT country_code, country_name, COUNT(DISTINCT ip) as count
             FROM {$table}
             WHERE country_code != ''
             GROUP BY country_code, country_name
             ORDER BY count DESC
             LIMIT %d",
            $limit
        ), ARRAY_A ) ?: [];
    }
}
