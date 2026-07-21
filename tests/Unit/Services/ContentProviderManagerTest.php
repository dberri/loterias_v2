<?php

namespace Tests\Unit\Services\ContentProviderManagerTest;

use App\Contracts\BatchContentProvider;
use App\Services\ContentProviderManager;
use App\Services\Providers\OpenAiContentProvider;
use InvalidArgumentException;

test('default resolution returns openai driver', function () {
    $manager = app(ContentProviderManager::class);

    $driver = $manager->driver();

    expect($driver)->toBeInstanceOf(BatchContentProvider::class);
    expect($driver)->toBeInstanceOf(OpenAiContentProvider::class);
});

test('unknown driver name throws', function () {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Driver [bogus] not supported.');

    app(ContentProviderManager::class)->driver('bogus');
});
