<?php
declare(strict_types=1);

namespace FeedAggregator\Providers;

/**
 * Registry for feed providers.
 *
 * Ships with built-in providers. Third parties can register custom providers
 * via the 'feed_agg_providers' filter.
 */
final class ProviderRegistry
{
    /** @var array<string, ProviderInterface> */
    private static array $providers = [];
    private static bool $initialized = false;

    public static function get(string $slug): ?ProviderInterface
    {
        self::ensureInitialized();
        return self::$providers[$slug] ?? null;
    }

    /**
     * @return array<string, ProviderInterface>
     */
    public static function all(): array
    {
        self::ensureInitialized();
        return self::$providers;
    }

    public static function register(ProviderInterface $provider): void
    {
        self::$providers[$provider->getSlug()] = $provider;
    }

    private static function ensureInitialized(): void
    {
        if (self::$initialized) {
            return;
        }

        // Built-in providers.
        self::register(new RSSProvider());
        self::register(new InstagramProvider());
        self::register(new YouTubeProvider());
        self::register(new CustomAPIProvider());

        /**
         * Register custom feed providers.
         *
         * @param ProviderInterface[] $providers Existing providers.
         * @return ProviderInterface[] All providers including custom ones.
         */
        $custom = apply_filters('feed_agg_providers', []);
        foreach ($custom as $provider) {
            if ($provider instanceof ProviderInterface) {
                self::register($provider);
            }
        }

        self::$initialized = true;
    }
}
