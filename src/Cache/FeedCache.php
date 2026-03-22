<?php
declare(strict_types=1);

namespace FeedAggregator\Cache;

use FeedAggregator\Providers\ProviderRegistry;

/**
 * Fetches items from providers and stores them in the items table.
 *
 * Uses INSERT IGNORE to avoid duplicates (unique on feed_id + external_id).
 */
final class FeedCache
{
    /**
     * Refresh all active feeds.
     */
    public static function refreshAll(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . FEED_AGG_TABLE_FEEDS;

        $feeds = $wpdb->get_results(
            "SELECT * FROM {$table} WHERE active = 1",
            ARRAY_A
        );

        foreach ($feeds as $feed) {
            self::refreshFeed($feed);
        }
    }

    /**
     * Refresh a single feed.
     *
     * @param array $feed Feed row from database.
     * @return int Number of new items stored.
     */
    public static function refreshFeed(array $feed): int
    {
        global $wpdb;
        $feeds_table = $wpdb->prefix . FEED_AGG_TABLE_FEEDS;
        $items_table = $wpdb->prefix . FEED_AGG_TABLE_ITEMS;

        $provider = ProviderRegistry::get($feed['provider']);
        if (!$provider) {
            self::recordError($feed['id'], "Unknown provider: {$feed['provider']}");
            return 0;
        }

        $config = json_decode($feed['config'], true) ?: [];

        try {
            $items = $provider->fetch($config);
        } catch (\Throwable $e) {
            self::recordError($feed['id'], $e->getMessage());
            return 0;
        }

        $inserted = 0;
        foreach ($items as $item) {
            $data = $item->toArray();
            $data['feed_id'] = $feed['id'];

            // Use INSERT IGNORE to skip duplicates.
            $columns = implode(', ', array_keys($data));
            $placeholders = implode(', ', array_fill(0, count($data), '%s'));

            $sql = $wpdb->prepare(
                "INSERT IGNORE INTO {$items_table} ({$columns}) VALUES ({$placeholders})",
                ...array_values($data)
            );

            $result = $wpdb->query($sql);
            if ($result) {
                ++$inserted;
            }
        }

        // Update feed metadata.
        $wpdb->update(
            $feeds_table,
            [
                'last_fetched_at' => current_time('mysql', true),
                'last_error'      => null,
            ],
            ['id' => $feed['id']],
            ['%s', '%s'],
            ['%d']
        );

        return $inserted;
    }

    private static function recordError(int $feedId, string $message): void
    {
        global $wpdb;
        $table = $wpdb->prefix . FEED_AGG_TABLE_FEEDS;

        $wpdb->update(
            $table,
            [
                'last_error'      => sanitize_text_field($message),
                'last_fetched_at' => current_time('mysql', true),
            ],
            ['id' => $feedId],
            ['%s', '%s'],
            ['%d']
        );
    }
}
