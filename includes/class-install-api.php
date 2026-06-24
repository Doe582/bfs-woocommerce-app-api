<?php
defined('ABSPATH') || exit;

/**
 * Class BFS_Install_API
 * Creates and upgrades the plugin database table.
 *
 * Table: wp_bfs_carts
 *   cart_key  — 'user_{id}' for logged-in users, 'guest_{uuid}' for guests
 *   user_id   — 0 for guests
 *   cart_data — full cart JSON (items, coupons, fees, shipping)
 *   expires_at — auto-cleanup support
 */
class BFS_Install_API {

    const DB_VER_KEY = 'bfs_db_version';
    const DB_VER     = '1.1';

    public static function activate(): void {
        self::create_tables();
        update_option(self::DB_VER_KEY, self::DB_VER);
        self::schedule_cleanup();
    }

    private static function create_tables(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql_carts = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bfs_carts (
            id         BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
            cart_key   VARCHAR(64)      NOT NULL COMMENT 'user_{id} or guest_{uuid}',
            user_id    BIGINT UNSIGNED  NOT NULL DEFAULT 0,
            cart_data  LONGTEXT         NOT NULL COMMENT 'JSON payload',
            expires_at DATETIME         NOT NULL,
            created_at DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY  uq_cart_key  (cart_key),
            KEY         idx_user_id  (user_id),
            KEY         idx_expires  (expires_at)
        ) $charset;";

        $sql_wishlists = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bfs_wishlists (
            id           BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
            wishlist_key VARCHAR(64)      NOT NULL COMMENT 'user_{id} or guest_{uuid}',
            user_id      BIGINT UNSIGNED  NOT NULL DEFAULT 0,
            items        LONGTEXT         NOT NULL COMMENT 'JSON array of product IDs',
            expires_at   DATETIME         NOT NULL,
            created_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY  uq_wishlist_key  (wishlist_key),
            KEY         idx_user_id      (user_id),
            KEY         idx_expires      (expires_at)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_carts);
        dbDelta($sql_wishlists);
    }

    /** Remove expired carts daily */
    private static function schedule_cleanup(): void {
        if (!wp_next_scheduled('bfs_cleanup_carts')) {
            wp_schedule_event(time(), 'daily', 'bfs_cleanup_carts');
        }
    }

    public static function setup_cleanup_hook(): void {
        add_action('bfs_cleanup_carts', static function () {
            global $wpdb;
            $wpdb->query("DELETE FROM {$wpdb->prefix}bfs_carts WHERE expires_at < NOW()");
        });
    }
}

// Register cleanup hook
BFS_Install_API::setup_cleanup_hook();
