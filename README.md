# WP Feed Aggregator

Pull, cache, and display content from Instagram, YouTube, RSS, and any JSON API in unified, filterable feeds. Built for WordPress developers who need a Spotlight/Social-Feed-style aggregator with full control over the architecture.

## Features

- **Multi-source aggregation** — Instagram (Graph API), YouTube (Data API v3), RSS/Atom, and a generic Custom JSON API provider that maps any endpoint to feed items
- **Normalized storage** — all content from all providers is stored in a common `feed_agg_items` table with a unified schema (title, body, media, author, permalink, metadata)
- **Deduplication** — `INSERT IGNORE` on `(feed_id, external_id)` prevents duplicate items even if the source API returns overlapping results
- **Automatic refresh** — hourly WP-Cron job refreshes all active feeds; per-feed configurable intervals
- **Multiple layouts** — grid, list, and masonry via shortcode or Gutenberg block
- **Public REST API** — headless-ready item endpoint with filtering by feed, content type, and search
- **Admin REST API** — full feed CRUD, manual refresh, and provider listing
- **Extensible** — register custom providers via the `feed_agg_providers` filter
- **WP-CLI** — `feed-agg list`, `feed-agg refresh`, `feed-agg stats`, `feed-agg providers`

## Installation

```bash
composer require wp-coding-agent/wp-feed-aggregator
```

Activate the plugin. Tables are created automatically.

## Quick Start

### 1. Add a Feed

```bash
# Via WP-CLI or REST API:
wp feed-agg providers    # See available providers and their config fields

# Via REST:
POST /wp-json/feed-aggregator/v1/feeds
{
  "name": "Company Blog",
  "provider": "rss",
  "config": { "feed_url": "https://example.com/feed", "max_items": 20 }
}
```

### 2. Display Items

**Shortcode:**
```
[feed_aggregator feed="1" layout="grid" columns="3" limit="12"]
[feed_aggregator feed="1,2" layout="masonry" columns="4" type="image"]
```

**Gutenberg:** Insert the "Feed Display" block and configure in the sidebar.

**REST API (headless):**
```
GET /wp-json/feed-aggregator/v1/items?feed=1&per_page=12&type=video
```

### 3. Add a Custom Provider

```php
add_filter('feed_agg_providers', function (array $providers): array {
    $providers[] = new MyCustomProvider();
    return $providers;
});
```

Your provider must implement `FeedAggregator\Providers\ProviderInterface` — just `getSlug()`, `getName()`, `fetch(array $config)`, and `getConfigFields()`.

## Providers

| Provider | Slug | Config Fields |
|----------|------|---------------|
| RSS / Atom | `rss` | `feed_url`, `max_items` |
| Instagram | `instagram` | `access_token`, `max_items` |
| YouTube | `youtube` | `api_key`, `channel_id`, `max_items` |
| Custom JSON API | `custom_api` | `url`, `headers`, `items_path`, `field_map`, `content_type` |

### Custom API Provider

The generic provider maps any JSON API to feed items using dot-notation field mapping:

```json
{
  "url": "https://api.example.com/articles",
  "headers": {"Authorization": "Bearer xxx"},
  "items_path": "data.articles",
  "field_map": {
    "externalId": "id",
    "title": "headline",
    "body": "summary",
    "mediaUrl": "cover.url",
    "permalink": "web_url",
    "publishedAt": "created_at"
  }
}
```

## REST API

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/feed-aggregator/v1/items` | Public | List items with filtering |
| GET | `/feed-aggregator/v1/feeds` | Admin | List all feeds |
| POST | `/feed-aggregator/v1/feeds` | Admin | Create a feed |
| PUT | `/feed-aggregator/v1/feeds/{id}` | Admin | Update a feed |
| DELETE | `/feed-aggregator/v1/feeds/{id}` | Admin | Delete feed + items |
| POST | `/feed-aggregator/v1/feeds/{id}/refresh` | Admin | Manual refresh |
| GET | `/feed-aggregator/v1/providers` | Admin | List available providers |

## WP-CLI

```bash
wp feed-agg list                  # All configured feeds
wp feed-agg refresh               # Refresh all feeds
wp feed-agg refresh 3             # Refresh feed ID 3 only
wp feed-agg stats                 # Item counts per feed
wp feed-agg providers             # Available providers + config fields
```

## License

GPL-2.0-or-later
