<?php

namespace Tests\Unit\Services;

use App\Contracts\BatchContentProvider;
use App\Services\ContentProviderManager;
use App\Services\Providers\OpenAiContentProvider;
use InvalidArgumentException;
use Tests\TestCase;

class ContentProviderManagerTest extends TestCase
{
    public function test_default_resolution_returns_openai_driver(): void
    {
        $manager = app(ContentProviderManager::class);

        $driver = $manager->driver();

        $this->assertInstanceOf(BatchContentProvider::class, $driver);
        $this->assertInstanceOf(OpenAiContentProvider::class, $driver);
    }

    public function test_unknown_driver_name_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Driver [bogus] not supported.');

        app(ContentProviderManager::class)->driver('bogus');
    }
}
