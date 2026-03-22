<?php
declare(strict_types=1);

namespace FeedAggregator\CLI;

use FeedAggregator\Cache\FeedCache;
use FeedAggregator\Providers\ProviderRegistry;
use WP_CLI;

final class FeedCommand
{
    /**
     * List all configured feeds.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format. Default: table.
     *
     * ## EXAMPLES
     *
     *     wp feed-agg list
     *
     * @subcommand list
     */
    public function list_(array $args, array $assoc_args): void
    {
        global $wpdb;
        $table = $wpdb->prefix . FEED_AGG_TABLE_FEEDS;

        $feeds = $wpdb->get_results("SELECT id, name, provider, active, last_fetched_at, last_error FROM {$table}", ARRAY_A);

        if (empty($feeds)) {
            WP_CLI::log('No feeds configured.');
            return;
        }

        \WP_CLI\Utils\format_items(
            $assoc_args['format'] ?? 'table',
            $feeds,
            ['id', 'name', 'provider', 'active', 'last_fetched_at', 'last_error']
        );
    }

    /**
     * Refresh one or all feeds immediately.
     *
     * ## OPTIONS
     *
     * [<feed_id>]
     * : ID of a specific feed to refresh. Omit for all feeds.
     *
     * ## EXAMPLES
     *
     *     wp feed-agg refresh
     *     wp feed-agg refresh 3
     */
    public function refresh(array $args): void
    {
        global $wpdb;
        $table = $wpdb->prefix . FEED_AGG_TABLE_FEEDS;

        if (!empty($args[0])) {
            $feed = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", (int)$args[0]),
                ARRAY_A
            );

            if (!$feed) {
                WP_CLI::error("Feed {$args[0]} not found.");
            }

            $count = FeedCache::refreshFeed($feed);
            WP_CLI::success("{$feed['name']}: {$count} new items.");
            return;
        }

        WP_CLI::log('Refreshing all feeds...');
        FeedCache::refreshAll();
        WP_CLI::success('All feeds refreshed.');
    }

    /**
     * Show available feed providers.
     *
     * ## EXAMPLES
     *
     *     wp feed-agg providers
     */
    public function providers(): void
    {
        $rows = [];
        foreach (ProviderRegistry::all() as $provider) {
            $fields = array_keys($provider->getConfigFields());
            $rows[] = [
                'slug'   => $provider->getSlug(),
                'name'   => $provider->getName(),
                'fields' => implode(', ', $fields),
            ];
        }

        \WP_CLI\Utils\format_items('table', $rows, ['slug', 'name', 'fields']);
    }

    /**
     * Show item count per feed.
     *
     * ## EXAMPLES
     *
     *     wp feed-agg stats
     */
    public function stats(): void
    {
        global $wpdb;
        $feeds_table = $wpdb->prefix . FEED_AGG_TABLE_FEEDS;
        $items_table = $wpdb->prefix . FEED_AGG_TABLE_ITEMS;

        $rows = $wpdb->get_results(
            "SELECT f.id, f.name, f.provider, COUNT(i.id) as item_count, f.last_fetched_at
             FROM {$feeds_table} f
             LEFT JOIN {$items_table} i ON i.feed_id = f.id
             GROUP BY f.id
             ORDER BY item_count DESC",
            ARRAY_A
        );

        if (empty($rows)) {
            WP_CLI::log('No feeds configured.');
            return;
        }

        \WP_CLI\Utils\format_items('table', $rows, ['id', 'name', 'provider', 'item_count', 'last_fetched_at']);
    }
}
