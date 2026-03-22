<?php
declare(strict_types=1);
/**
 * Plugin Name:  WP Feed Aggregator
 * Description:  Pull, cache, and display content from Instagram, YouTube, RSS, and custom APIs in unified, filterable feeds.
 * Version:      1.0.0
 * Requires PHP: 8.0
 * License:      GPL-2.0-or-later
 *
 * @package FeedAggregator
 */

defined( 'ABSPATH' ) || exit;

define( 'FEED_AGG_VERSION', '1.0.0' );
define( 'FEED_AGG_DIR', plugin_dir_path( __FILE__ ) );
define( 'FEED_AGG_URL', plugin_dir_url( __FILE__ ) );
define( 'FEED_AGG_TABLE_FEEDS', 'feed_agg_feeds' );
define( 'FEED_AGG_TABLE_ITEMS', 'feed_agg_items' );

require_once FEED_AGG_DIR . 'vendor/autoload.php';

register_activation_hook( __FILE__, [ FeedAggregator\Schema::class, 'install' ] );

add_action( 'init', static function (): void {
	( new FeedAggregator\Plugin() )->init();
} );
