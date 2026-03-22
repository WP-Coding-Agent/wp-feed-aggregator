<?php
declare(strict_types=1);

namespace FeedAggregator\Tests\Unit;

use FeedAggregator\Providers\CustomAPIProvider;
use PHPUnit\Framework\TestCase;

final class CustomAPIProviderTest extends TestCase
{
    public function test_get_slug(): void
    {
        $provider = new CustomAPIProvider();
        $this->assertSame('custom_api', $provider->getSlug());
    }

    public function test_get_config_fields_has_required_fields(): void
    {
        $provider = new CustomAPIProvider();
        $fields = $provider->getConfigFields();

        $this->assertArrayHasKey('url', $fields);
        $this->assertArrayHasKey('items_path', $fields);
        $this->assertArrayHasKey('field_map', $fields);
        $this->assertTrue($fields['url']['required']);
    }

    public function test_throws_on_empty_url(): void
    {
        $provider = new CustomAPIProvider();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('API URL is required');

        $provider->fetch(['url' => '']);
    }
}
