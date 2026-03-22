<?php
declare(strict_types=1);

namespace FeedAggregator\Providers;

/**
 * YouTube Data API v3 provider.
 *
 * Fetches recent videos from a channel using the search endpoint.
 */
final class YouTubeProvider implements ProviderInterface
{
    private const API_BASE = 'https://www.googleapis.com/youtube/v3';

    public function getSlug(): string { return 'youtube'; }
    public function getName(): string { return 'YouTube'; }

    public function fetch(array $config): array
    {
        $apiKey    = $config['api_key'] ?? '';
        $channelId = $config['channel_id'] ?? '';

        if (empty($apiKey) || empty($channelId)) {
            throw new \RuntimeException('YouTube API key and channel ID are required.');
        }

        $limit = min((int)($config['max_items'] ?? 12), 50);

        $response = wp_remote_get(
            add_query_arg([
                'part'       => 'snippet',
                'channelId'  => $channelId,
                'maxResults' => $limit,
                'order'      => 'date',
                'type'       => 'video',
                'key'        => $apiKey,
            ], self::API_BASE . '/search'),
            ['timeout' => 15]
        );

        if (is_wp_error($response)) {
            throw new \RuntimeException('YouTube API error: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            throw new \RuntimeException('YouTube API: ' . ($body['error']['message'] ?? 'Unknown error'));
        }

        $result = [];
        foreach ($body['items'] ?? [] as $item) {
            $snippet = $item['snippet'] ?? [];
            $videoId = $item['id']['videoId'] ?? null;

            if (!$videoId) {
                continue;
            }

            $result[] = new FeedItem(
                externalId:  $videoId,
                contentType: 'video',
                title:       $snippet['title'] ?? null,
                body:        $snippet['description'] ?? null,
                mediaUrl:    $snippet['thumbnails']['high']['url'] ?? $snippet['thumbnails']['default']['url'] ?? null,
                mediaType:   'image/jpeg', // Thumbnail.
                permalink:   "https://www.youtube.com/watch?v={$videoId}",
                authorName:  $snippet['channelTitle'] ?? null,
                authorAvatar: null,
                publishedAt: new \DateTimeImmutable($snippet['publishedAt'] ?? 'now'),
                metadata:    [
                    'video_id'   => $videoId,
                    'channel_id' => $channelId,
                    'embed_url'  => "https://www.youtube.com/embed/{$videoId}",
                ],
            );
        }

        return $result;
    }

    public function getConfigFields(): array
    {
        return [
            'api_key'    => ['label' => 'API Key', 'type' => 'password', 'required' => true, 'help' => 'YouTube Data API v3 key.'],
            'channel_id' => ['label' => 'Channel ID', 'type' => 'text', 'required' => true, 'help' => 'YouTube channel ID (e.g., UCxxxxxx).'],
            'max_items'  => ['label' => 'Max Items', 'type' => 'number', 'required' => false, 'help' => 'Default: 12.'],
        ];
    }
}
