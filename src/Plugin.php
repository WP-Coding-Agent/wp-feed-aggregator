<?php
declare(strict_types=1);

namespace FeedAggregator;

use FeedAggregator\Admin\SettingsPage;
use FeedAggregator\Cache\FeedCache;
use FeedAggregator\REST\FeedController;
use FeedAggregator\REST\ItemController;
use FeedAggregator\Render\ShortcodeHandler;

final class Plugin
{
    public function init(): void
    {
        // Admin settings page.
        if (is_admin()) {
            $settings = new SettingsPage();
            add_action('admin_menu', [$settings, 'addMenuPage']);
            add_action('admin_enqueue_scripts', [$settings, 'enqueueAssets']);
        }

        // REST API.
        add_action('rest_api_init', static function (): void {
            (new FeedController())->register_routes();
            (new ItemController())->register_routes();
        });

        // Shortcode: [feed_aggregator feed="1" layout="grid" columns="3" limit="12"]
        $shortcode = new ShortcodeHandler();
        add_shortcode('feed_aggregator', [$shortcode, 'render']);

        // Gutenberg block.
        add_action('init', static function (): void {
            if (file_exists(FEED_AGG_DIR . 'assets/src/blocks/feed-display/block.json')) {
                register_block_type(
                    FEED_AGG_DIR . 'assets/src/blocks/feed-display/block.json',
                    ['render_callback' => [new ShortcodeHandler(), 'renderBlock']]
                );
            }
        });

        // Cron: refresh feeds.
        add_action('feed_agg_refresh', [FeedCache::class, 'refreshAll']);
        if (!wp_next_scheduled('feed_agg_refresh')) {
            wp_schedule_event(time(), 'hourly', 'feed_agg_refresh');
        }

        // WP-CLI.
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('feed-agg', CLI\FeedCommand::class);
        }
    }
}
