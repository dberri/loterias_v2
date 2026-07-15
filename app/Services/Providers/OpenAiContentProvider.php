<?php

namespace App\Services\Providers;

use App\Contracts\BatchContentProvider;
use App\DTOs\BatchStatus;
use App\DTOs\GenerationRequest;
use App\DTOs\GenerationResult;

class OpenAiContentProvider implements BatchContentProvider
{
    public function __construct(private readonly array $config = []) {}

    public function submitBatch(iterable $requests): string
    {
        throw new \BadMethodCallException('Batch submission is implemented in T10.');
    }

    public function pollBatch(string $id): BatchStatus
    {
        throw new \BadMethodCallException('Batch polling is implemented in T10.');
    }

    public function fetchResults(string $id): iterable
    {
        throw new \BadMethodCallException('Batch result fetching is implemented in T10.');
    }

    public function generateOne(GenerationRequest $request): GenerationResult
    {
        throw new \BadMethodCallException('Synchronous generation is implemented in T11.');
    }
}
