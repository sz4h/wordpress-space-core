<?php

namespace Space\Core\Modules\StockNotifier;

defined( 'ABSPATH' ) || exit;

class SubscriberDB {

    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'sc_stock_subscribers';
    }

    public static function create_table(): void {
        global $wpdb;
        $table      = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id BIGINT UNSIGNED NOT NULL,
            contact VARCHAR(255) NOT NULL,
            channel VARCHAR(20) NOT NULL DEFAULT 'email',
            lang VARCHAR(10) NOT NULL DEFAULT 'en',
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY status (status)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public static function insert( int $product_id, string $contact, string $channel, string $lang ): int|false {
        global $wpdb;
        // Avoid duplicate subscriptions.
        $exists = $wpdb->get_var( // phpcs:ignore
            $wpdb->prepare(
                "SELECT id FROM " . self::table_name() . " WHERE product_id = %d AND contact = %s AND status = 'pending'",
                $product_id,
                $contact
            )
        );
        if ( $exists ) {
            return (int) $exists;
        }
        $result = $wpdb->insert( // phpcs:ignore
            self::table_name(),
            [
                'product_id' => $product_id,
                'contact'    => $contact,
                'channel'    => $channel,
                'lang'       => $lang,
                'status'     => 'pending',
                'created_at' => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%s', '%s', '%s' ]
        );
        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get pending subscribers for a set of product IDs.
     */
    public static function get_pending( array $product_ids ): array {
        global $wpdb;
        if ( empty( $product_ids ) ) {
            return [];
        }
        $placeholders = implode( ',', array_fill( 0, count( $product_ids ), '%d' ) );
        return $wpdb->get_results( // phpcs:ignore
            $wpdb->prepare(
                "SELECT * FROM " . self::table_name() . " WHERE product_id IN ({$placeholders}) AND status = 'pending'",
                ...$product_ids
            ),
            ARRAY_A
        ) ?: [];
    }

    public static function mark_notified( int $id ): void {
        global $wpdb;
        $wpdb->update( // phpcs:ignore
            self::table_name(),
            [ 'status' => 'notified' ],
            [ 'id' => $id ],
            [ '%s' ],
            [ '%d' ]
        );
    }
}
