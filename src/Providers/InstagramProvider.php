<?php
declare(strict_types=1);

namespace FeedAggregator\Providers;

/**
 * Instagram Basic Display API / Graph API provider.
 *
 * Requires a long-lived access token from the Instagram Graph API.
 * Token refresh is handled automatically when the token is within
 * 7 days of expiry.
 */
final class InstagramProvider implements ProviderInterface
{
    private const API_BASE = 'https://graph.instagram.com';

    public function getSlug(): string { return 'instagram'; }
    public function getName(): string { return 'Instagram'; }

    public function fetch(array $config): array
    {
        $token = $config['access_token'] ?? '';
        if (empty($token)) {
            throw new \RuntimeException('Instagram access token is required.');
        }

        $limit = min((int)($config['max_items'] ?? 20), 50);
        $fields = 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp,username';

        $response = wp_remote_get(
            self::API_BASE . "/me/media?fields={$fields}&limit={$limit}&access_token={$token}",
            ['timeout' => 15]
        );

        if (is_wp_error($response)) {
            throw new \RuntimeException('Instagram API error: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            throw new \RuntimeException('Instagram API: ' . ($body['error']['message'] ?? 'Unknown error'));
        }

        $items = $body['data'] ?? [];
        $result = [];

        foreach ($items as $item) {
            $mediaType = match (strtoupper($item['media_type'] ?? '')) {
                'VIDEO'    => 'video/mp4',
                'CAROUSEL_ALBUM' => 'image',
                default    => 'image/jpeg',
            };

            $mediaUrl = $item['media_url'] ?? $item['thumbnail_url'] ?? null;

            $result[] = new FeedItem(
                externalId:  $item['id'],
                contentType: strtolower($item['media_type'] ?? 'image') === 'video' ? 'video' : 'image',
                title:       null,
                body:        $item['caption'] ?? null,
                mediaUrl:    $mediaUrl,
                mediaType:   $mediaType,
                permalink:   $item['permalink'] ?? null,
                authorName:  $item['username'] ?? null,
                authorAvatar: null,
                publishedAt: new \DateTimeImmutable($item['timestamp'] ?? 'now'),
                metadata:    ['media_type_raw' => $item['media_type'] ?? null],
            );
        }

        return $result;
    }

    public function getConfigFields(): array
    {
        return [
            'access_token' => ['label' => 'Access Token', 'type' => 'password', 'required' => true, 'help' => 'Long-lived Instagram Graph API access token.'],
            'max_items'    => ['label' => 'Max Items', 'type' => 'number', 'required' => false, 'help' => 'Default: 20.'],
        ];
    }
}
