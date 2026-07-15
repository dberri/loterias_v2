<?php

namespace App\Services;

use App\Contracts\BatchContentProvider;
use App\Services\Providers\OpenAiContentProvider;
use Illuminate\Support\Manager;

class ContentProviderManager extends Manager
{
    public function driver($driver = null): BatchContentProvider
    {
        return parent::driver($driver);
    }

    public function getDefaultDriver(): string
    {
        return config('content.default', 'openai');
    }

    public function createOpenaiDriver(): BatchContentProvider
    {
        return new OpenAiContentProvider(config('content.drivers.openai', []));
    }
}
