<?php
declare(strict_types=1);

namespace FeedAggregator\REST;

use FeedAggregator\Cache\FeedCache;
use FeedAggregator\Providers\ProviderRegistry;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class FeedController extends WP_REST_Controller
{
    protected $namespace = 'feed-aggregator/v1';
    protected $rest_base = 'feeds';

    public function register_routes(): void
    {
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'list_feeds'],
                'permission_callback' => [$this, 'admin_check'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'create_feed'],
                'permission_callback' => [$this, 'admin_check'],
                'args'                => [
                    'name'             => ['type' => 'string', 'required' => true],
                    'provider'         => ['type' => 'string', 'required' => true],
                    'config'           => ['type' => 'object', 'required' => true],
                    'refresh_interval' => ['type' => 'integer', 'default' => 3600],
                ],
            ],
        ]);

        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', [
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'update_feed'],
                'permission_callback' => [$this, 'admin_check'],
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'delete_feed'],
                'permission_callback' => [$this, 'admin_check'],
            ],
        ]);

        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/refresh', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'refresh_feed'],
            'permission_callback' => [$this, 'admin_check'],
        ]);

        register_rest_route($this->namespace, '/providers', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'list_providers'],
            'permission_callback' => [$this, 'admin_check'],
        ]);
    }

    public function list_feeds(): WP_REST_Response
    {
        global $wpdb;
        $table = $wpdb->prefix . FEED_AGG_TABLE_FEEDS;
        $feeds = $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A);

        foreach ($feeds as &$feed) {
            $feed['config'] = json_decode($feed['config'], true);
            $feed['id'] = (int)$feed['id'];
            $feed['active'] = (bool)$feed['active'];
        }

        return new WP_REST_Response($feeds);
    }

    public function create_feed(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;
        $table = $wpdb->prefix . FEED_AGG_TABLE_FEEDS;

        $provider = sanitize_key($request['provider']);
        if (!ProviderRegistry::get($provider)) {
            return new WP_Error('invalid_provider', "Unknown provider: {$provider}", ['status' => 400]);
        }

        $wpdb->insert($table, [
            'name'             => sanitize_text_field($request['name']),
            'provider'         => $provider,
            'config'           => wp_json_encode($request['config']),
            'refresh_interval' => absint($request['refresh_interval']),
        ], ['%s', '%s', '%s', '%d']);

        $id = (int)$wpdb->insert_id;

        // Trigger initial fetch.
        $feed = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
        if ($feed) {
            FeedCache::refreshFeed($feed);
        }

        return new WP_REST_Response(['id' => $id], 201);
    }

    public function update_feed(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $table = $wpdb->prefix . FEED_AGG_TABLE_FEEDS;

        $data = [];
        $formats = [];

        if (isset($request['name'])) {
            $data['name'] = sanitize_text_field($request['name']);
            $formats[] = '%s';
        }
        if (isset($request['config'])) {
            $data['config'] = wp_json_encode($request['config']);
            $formats[] = '%s';
        }
        if (isset($request['active'])) {
            $data['active'] = (int)(bool)$request['active'];
            $formats[] = '%d';
        }
        if (isset($request['refresh_interval'])) {
            $data['refresh_interval'] = absint($request['refresh_interval']);
            $formats[] = '%d';
        }

        $wpdb->update($table, $data, ['id' => (int)$request['id']], $formats, ['%d']);

        return new WP_REST_Response(['updated' => true]);
    }

    public function delete_feed(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $id = (int)$request['id'];

        // Delete items first.
        $items_table = $wpdb->prefix . FEED_AGG_TABLE_ITEMS;
        $wpdb->delete($items_table, ['feed_id' => $id], ['%d']);

        // Delete feed.
        $feeds_table = $wpdb->prefix . FEED_AGG_TABLE_FEEDS;
        $wpdb->delete($feeds_table, ['id' => $id], ['%d']);

        return new WP_REST_Response(null, 204);
    }

    public function refresh_feed(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;
        $table = $wpdb->prefix . FEED_AGG_TABLE_FEEDS;
        $feed = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", (int)$request['id']),
            ARRAY_A
        );

        if (!$feed) {
            return new WP_Error('not_found', 'Feed not found.', ['status' => 404]);
        }

        $inserted = FeedCache::refreshFeed($feed);
        return new WP_REST_Response(['new_items' => $inserted]);
    }

    public function list_providers(): WP_REST_Response
    {
        $providers = [];
        foreach (ProviderRegistry::all() as $provider) {
            $providers[] = [
                'slug'   => $provider->getSlug(),
                'name'   => $provider->getName(),
                'fields' => $provider->getConfigFields(),
            ];
        }
        return new WP_REST_Response($providers);
    }

    public function admin_check(): bool
    {
        return current_user_can('manage_options');
    }
}
