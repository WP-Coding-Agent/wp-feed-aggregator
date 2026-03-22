<?php
declare(strict_types=1);

namespace FeedAggregator\Providers;

/**
 * Normalized feed item — the common format all providers emit.
 */
final class FeedItem
{
    public function __construct(
        public readonly string $externalId,
        public readonly string $contentType,   // 'image', 'video', 'article', 'post'.
        public readonly ?string $title,
        public readonly ?string $body,
        public readonly ?string $mediaUrl,
        public readonly ?string $mediaType,    // 'image/jpeg', 'video/mp4', etc.
        public readonly ?string $permalink,
        public readonly ?string $authorName,
        public readonly ?string $authorAvatar,
        public readonly \DateTimeImmutable $publishedAt,
        public readonly array $metadata = [],   // Provider-specific extra data.
    ) {}

    /**
     * Convert to an associative array for database storage.
     */
    public function toArray(): array
    {
        return [
            'external_id'   => $this->externalId,
            'content_type'  => $this->contentType,
            'title'         => $this->title,
            'body'          => $this->body,
            'media_url'     => $this->mediaUrl,
            'media_type'    => $this->mediaType,
            'permalink'     => $this->permalink,
            'author_name'   => $this->authorName,
            'author_avatar' => $this->authorAvatar,
            'published_at'  => $this->publishedAt->format('Y-m-d H:i:s'),
            'metadata'      => wp_json_encode($this->metadata),
        ];
    }
}
