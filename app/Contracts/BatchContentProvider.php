<?php

namespace App\Contracts;

use App\DTOs\BatchStatus;
use App\DTOs\GenerationRequest;
use App\DTOs\GenerationResult;

interface BatchContentProvider
{
    public function submitBatch(iterable $requests): string;

    public function pollBatch(string $id): BatchStatus;

    public function fetchResults(string $id): iterable;

    public function generateOne(GenerationRequest $request): GenerationResult;
}
