<?php
declare(strict_types=1);

namespace FeedAggregator\Admin;

/**
 * Admin page for managing feeds.
 * Renders a React mount point — the actual UI is built with @wordpress/components.
 */
final class SettingsPage
{
    private const PAGE_SLUG = 'feed-aggregator';

    public function addMenuPage(): void
    {
        add_menu_page(
            __('Feed Aggregator', 'feed-aggregator'),
            __('Feed Aggregator', 'feed-aggregator'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render'],
            'dashicons-rss',
            30
        );
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized.', 'feed-aggregator'));
        }

        echo '<div id="feed-aggregator-root"></div>';
    }

    public function enqueueAssets(string $hookSuffix): void
    {
        if ('toplevel_page_' . self::PAGE_SLUG !== $hookSuffix) {
            return;
        }

        $asset_file = FEED_AGG_DIR . 'build/admin/index.asset.php';
        if (!file_exists($asset_file)) {
            return;
        }

        $asset = require $asset_file;

        wp_enqueue_script(
            'feed-agg-admin',
            FEED_AGG_URL . 'build/admin/index.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        wp_enqueue_style(
            'feed-agg-admin',
            FEED_AGG_URL . 'build/admin/index.css',
            ['wp-components'],
            $asset['version']
        );

        wp_localize_script('feed-agg-admin', 'feedAggData', [
            'restUrl' => rest_url('feed-aggregator/v1'),
            'nonce'   => wp_create_nonce('wp_rest'),
        ]);
    }
}
