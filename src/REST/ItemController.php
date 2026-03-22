<?php
declare(strict_types=1);

namespace FeedAggregator\REST;

use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Public endpoint for fetching feed items. Useful for headless/JS frontends.
 */
final class ItemController extends WP_REST_Controller
{
    protected $namespace = 'feed-aggregator/v1';
    protected $rest_base = 'items';

    public function register_routes(): void
    {
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_items'],
            'permission_callback' => '__return_true',
            'args'                => [
                'feed'    => ['type' => 'string', 'default' => ''],
                'type'    => ['type' => 'string', 'default' => ''],
                'search'  => ['type' => 'string', 'default' => ''],
                'page'    => ['type' => 'integer', 'default' => 1, 'minimum' => 1],
                'per_page'=> ['type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100],
            ],
        ]);
    }

    public function get_items(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $table = $wpdb->prefix . FEED_AGG_TABLE_ITEMS;

        $where = ['1=1'];
        $params = [];

        if ($request['feed']) {
            $ids = array_map('absint', explode(',', $request['feed']));
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $where[] = "feed_id IN ({$placeholders})";
            $params = array_merge($params, $ids);
        }

        if ($request['type']) {
            $where[] = 'content_type = %s';
            $params[] = sanitize_key($request['type']);
        }

        if ($request['search']) {
            $where[] = '(title LIKE %s OR body LIKE %s)';
            $search = '%' . $wpdb->esc_like(sanitize_text_field($request['search'])) . '%';
            $params[] = $search;
            $params[] = $search;
        }

        $where_sql = implode(' AND ', $where);
        $per_page = $request['per_page'];
        $offset = ($request['page'] - 1) * $per_page;

        // Count total.
        $total = (int)$wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", ...$params)
        );

        $params[] = $per_page;
        $params[] = $offset;

        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, feed_id, external_id, content_type, title, body, media_url, media_type,
                        permalink, author_name, author_avatar, metadata, published_at
                 FROM {$table} WHERE {$where_sql} ORDER BY published_at DESC LIMIT %d OFFSET %d",
                ...$params
            ),
            ARRAY_A
        );

        foreach ($items as &$item) {
            $item['id'] = (int)$item['id'];
            $item['feed_id'] = (int)$item['feed_id'];
            $item['metadata'] = json_decode($item['metadata'] ?? '{}', true);
        }

        $response = new WP_REST_Response($items);
        $response->header('X-WP-Total', (string)$total);
        $response->header('X-WP-TotalPages', (string)ceil($total / $per_page));

        return $response;
    }
}
