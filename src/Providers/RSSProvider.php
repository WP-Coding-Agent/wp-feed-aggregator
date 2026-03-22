<?php
declare(strict_types=1);

namespace FeedAggregator\Providers;

/**
 * RSS/Atom feed provider using WordPress's built-in SimplePie integration.
 */
final class RSSProvider implements ProviderInterface
{
    public function getSlug(): string { return 'rss'; }
    public function getName(): string { return 'RSS / Atom'; }

    public function fetch(array $config): array
    {
        $url = $config['feed_url'] ?? '';
        if (empty($url)) {
            throw new \RuntimeException('RSS feed URL is required.');
        }

        $rss = fetch_feed($url);

        if (is_wp_error($rss)) {
            throw new \RuntimeException('RSS fetch failed: ' . $rss->get_error_message());
        }

        $max = min((int)($config['max_items'] ?? 20), 50);
        $items = $rss->get_items(0, $max);
        $result = [];

        foreach ($items as $item) {
            $enclosure = $item->get_enclosure();
            $mediaUrl = $enclosure ? $enclosure->get_link() : null;
            $mediaType = $enclosure ? $enclosure->get_type() : null;

            // Try to extract an image from content if no enclosure.
            if (!$mediaUrl && $item->get_content()) {
                preg_match('/<img[^>]+src=["\']([^"\']+)/i', $item->get_content(), $matches);
                $mediaUrl = $matches[1] ?? null;
                $mediaType = $mediaUrl ? 'image' : null;
            }

            $date = $item->get_date('Y-m-d H:i:s');

            $result[] = new FeedItem(
                externalId:  md5($item->get_id()),
                contentType: 'article',
                title:       $item->get_title(),
                body:        wp_strip_all_tags($item->get_description()),
                mediaUrl:    $mediaUrl,
                mediaType:   $mediaType,
                permalink:   $item->get_permalink(),
                authorName:  $item->get_author() ? $item->get_author()->get_name() : null,
                authorAvatar: null,
                publishedAt: new \DateTimeImmutable($date ?: 'now'),
                metadata:    [
                    'categories' => array_map(
                        fn($cat) => $cat->get_label(),
                        $item->get_categories() ?? []
                    ),
                ],
            );
        }

        return $result;
    }

    public function getConfigFields(): array
    {
        return [
            'feed_url'  => ['label' => 'Feed URL', 'type' => 'url', 'required' => true, 'help' => 'Full URL to the RSS or Atom feed.'],
            'max_items' => ['label' => 'Max Items', 'type' => 'number', 'required' => false, 'help' => 'Maximum items to fetch per refresh. Default: 20.'],
        ];
    }
}
