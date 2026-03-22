<?php
declare(strict_types=1);

namespace FeedAggregator\Providers;

/**
 * All feed providers implement this interface.
 *
 * A provider fetches items from an external source and normalizes them
 * into a standard FeedItem format.
 */
interface ProviderInterface
{
    /**
     * Unique provider slug (e.g., 'instagram', 'youtube', 'rss').
     */
    public function getSlug(): string;

    /**
     * Human-readable name.
     */
    public function getName(): string;

    /**
     * Fetch items from the external source.
     *
     * @param array $config Provider-specific configuration (API keys, usernames, URLs, etc).
     * @return FeedItem[]
     * @throws \RuntimeException On fetch failure.
     */
    public function fetch(array $config): array;

    /**
     * Return the configuration fields this provider needs (for the admin UI).
     *
     * @return array<string, array{label: string, type: string, required: bool, help?: string}>
     */
    public function getConfigFields(): array;
}
