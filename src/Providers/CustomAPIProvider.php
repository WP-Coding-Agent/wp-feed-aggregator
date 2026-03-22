<?php
declare(strict_types=1);

namespace FeedAggregator\Providers;

/**
 * Generic JSON API provider.
 *
 * Fetches a JSON endpoint and maps fields using configurable JSONPath-like
 * selectors. This allows aggregating content from any API without writing
 * a custom provider.
 *
 * Config example:
 *   {
 *     "url": "https://api.example.com/posts",
 *     "headers": {"Authorization": "Bearer xxx"},
 *     "items_path": "data.items",
 *     "field_map": {
 *       "externalId": "id",
 *       "title": "headline",
 *       "body": "summary",
 *       "mediaUrl": "cover_image.url",
 *       "permalink": "web_url",
 *       "publishedAt": "created_at"
 *     }
 *   }
 */
final class CustomAPIProvider implements ProviderInterface
{
    public function getSlug(): string { return 'custom_api'; }
    public function getName(): string { return 'Custom JSON API'; }

    public function fetch(array $config): array
    {
        $url = $config['url'] ?? '';
        if (empty($url)) {
            throw new \RuntimeException('API URL is required.');
        }

        $headers = $config['headers'] ?? [];
        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => $headers,
        ]);

        if (is_wp_error($response)) {
            throw new \RuntimeException('API fetch failed: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body)) {
            throw new \RuntimeException('Invalid JSON response from API.');
        }

        // Navigate to items array.
        $items = $this->resolvePath($body, $config['items_path'] ?? '');
        if (!is_array($items)) {
            throw new \RuntimeException("Could not resolve items at path: {$config['items_path']}");
        }

        $fieldMap = $config['field_map'] ?? [];
        $result = [];

        foreach ($items as $item) {
            $result[] = new FeedItem(
                externalId:  (string) ($this->resolveField($item, $fieldMap['externalId'] ?? 'id') ?? uniqid()),
                contentType: $config['content_type'] ?? 'post',
                title:       $this->resolveField($item, $fieldMap['title'] ?? null),
                body:        $this->resolveField($item, $fieldMap['body'] ?? null),
                mediaUrl:    $this->resolveField($item, $fieldMap['mediaUrl'] ?? null),
                mediaType:   $this->resolveField($item, $fieldMap['mediaType'] ?? null),
                permalink:   $this->resolveField($item, $fieldMap['permalink'] ?? null),
                authorName:  $this->resolveField($item, $fieldMap['authorName'] ?? null),
                authorAvatar: $this->resolveField($item, $fieldMap['authorAvatar'] ?? null),
                publishedAt: new \DateTimeImmutable(
                    $this->resolveField($item, $fieldMap['publishedAt'] ?? null) ?? 'now'
                ),
            );
        }

        return $result;
    }

    public function getConfigFields(): array
    {
        return [
            'url'          => ['label' => 'API URL', 'type' => 'url', 'required' => true],
            'headers'      => ['label' => 'Headers (JSON)', 'type' => 'textarea', 'required' => false, 'help' => 'JSON object of HTTP headers.'],
            'items_path'   => ['label' => 'Items Path', 'type' => 'text', 'required' => true, 'help' => 'Dot-notation path to the items array (e.g., "data.items").'],
            'field_map'    => ['label' => 'Field Map (JSON)', 'type' => 'textarea', 'required' => true, 'help' => 'JSON mapping of FeedItem fields to API response fields.'],
            'content_type' => ['label' => 'Content Type', 'type' => 'text', 'required' => false, 'help' => 'Default: "post".'],
        ];
    }

    /**
     * Resolve a dot-notation path in an array.
     */
    private function resolvePath(array $data, string $path): mixed
    {
        if (empty($path)) {
            return $data;
        }

        $segments = explode('.', $path);
        $current = $data;

        foreach ($segments as $segment) {
            if (!is_array($current) || !isset($current[$segment])) {
                return null;
            }
            $current = $current[$segment];
        }

        return $current;
    }

    private function resolveField(array $item, ?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $value = $this->resolvePath($item, $path);
        return $value !== null ? (string) $value : null;
    }
}
