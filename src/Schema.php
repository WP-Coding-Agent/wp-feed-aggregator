<?php
declare(strict_types=1);

namespace FeedAggregator;

final class Schema
{
    public static function install(): void
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $feeds = $wpdb->prefix . FEED_AGG_TABLE_FEEDS;
        $items = $wpdb->prefix . FEED_AGG_TABLE_ITEMS;

        dbDelta("CREATE TABLE {$feeds} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            provider VARCHAR(50) NOT NULL,
            config LONGTEXT NOT NULL,
            refresh_interval INT UNSIGNED NOT NULL DEFAULT 3600,
            active TINYINT(1) NOT NULL DEFAULT 1,
            last_fetched_at DATETIME DEFAULT NULL,
            last_error TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_provider (provider),
            KEY idx_active (active)
        ) {$charset};");

        dbDelta("CREATE TABLE {$items} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            feed_id BIGINT UNSIGNED NOT NULL,
            external_id VARCHAR(255) NOT NULL,
            content_type VARCHAR(50) NOT NULL DEFAULT 'post',
            title TEXT DEFAULT NULL,
            body LONGTEXT DEFAULT NULL,
            media_url VARCHAR(2048) DEFAULT NULL,
            media_type VARCHAR(20) DEFAULT NULL,
            permalink VARCHAR(2048) DEFAULT NULL,
            author_name VARCHAR(255) DEFAULT NULL,
            author_avatar VARCHAR(2048) DEFAULT NULL,
            metadata LONGTEXT DEFAULT NULL,
            published_at DATETIME NOT NULL,
            fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_feed_external (feed_id, external_id),
            KEY idx_feed_published (feed_id, published_at),
            KEY idx_content_type (content_type)
        ) {$charset};");
    }
}
