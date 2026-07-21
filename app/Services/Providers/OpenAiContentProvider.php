<?php

namespace App\Services\Providers;

use App\Contracts\BatchContentProvider;
use App\DTOs\BatchStatus;
use App\DTOs\GenerationRequest;
use App\DTOs\GenerationResult;
use App\Services\Content\DrawPagePrompt;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class OpenAiContentProvider implements BatchContentProvider
{
    public function __construct(private readonly array $config = []) {}

    public function submitBatch(iterable $requests): string
    {
        $filePath = $this->batchFilePath();
        $this->ensureDirectoryExists(dirname($filePath));

        $file = fopen($filePath, 'w');

        foreach ($requests as $request) {
            if (! $request instanceof GenerationRequest) {
                continue;
            }

            fwrite($file, json_encode([
                'custom_id' => $request->customId,
                'method' => 'POST',
                'url' => '/v1/chat/completions',
                'body' => $this->buildChatPayload($request),
            ], JSON_THROW_ON_ERROR).PHP_EOL);
        }

        fclose($file);

        $upload = OpenAI::files()->upload([
            'purpose' => 'batch',
            'file' => fopen($filePath, 'r'),
        ]);

        $batch = OpenAI::batches()->create([
            'input_file_id' => $upload->id,
            'endpoint' => '/v1/chat/completions',
            'completion_window' => '24h',
        ]);

        return $batch->id;
    }

    public function pollBatch(string $id): BatchStatus
    {
        $batch = OpenAI::batches()->retrieve($id);

        return match ($batch->status) {
            'in_progress', 'queued', 'validating', 'finalizing' => BatchStatus::InProgress,
            'completed' => BatchStatus::Completed,
            'expired' => BatchStatus::Expired,
            'failed', 'cancelled', 'cancelling' => BatchStatus::Failed,
            default => $this->unknownStatus($id, $batch->status),
        };
    }

    public function fetchResults(string $id): iterable
    {
        $batch = OpenAI::batches()->retrieve($id);
        $outputFileId = $batch->outputFileId;

        if (! $outputFileId) {
            return [];
        }

        $output = OpenAI::files()->download($outputFileId);
        $results = [];

        foreach (preg_split('/\R/', trim($output)) as $line) {
            if ($line === '') {
                continue;
            }

            $payload = json_decode($line, true);
            if (! is_array($payload)) {
                continue;
            }

            $customId = data_get($payload, 'custom_id');
            $content = data_get($payload, 'response.body.choices.0.message.content');

            if (! is_string($customId) || ! is_string($content)) {
                continue;
            }

            $results[$customId] = $this->resultFromContent($customId, $content);
        }

        return $results;
    }

    public function generateOne(GenerationRequest $request): GenerationResult
    {
        $response = OpenAI::chat()->create($this->buildChatPayload($request));
        $content = data_get($response, 'choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            return GenerationResult::invalid($request->customId, null, 'Missing chat completion content.');
        }

        return $this->resultFromContent($request->customId, $content);
    }

    private function batchFilePath(): string
    {
        return storage_path('app/private/commands.jsonl');
    }

    private function buildChatPayload(GenerationRequest $request): array
    {
        return [
            'model' => $this->model(),
            'temperature' => $this->temperature(),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => DrawPagePrompt::systemPrompt(),
                ],
                [
                    'role' => 'user',
                    'content' => $request->prompt,
                ],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => $request->customId,
                    'strict' => true,
                    'schema' => $request->schema,
                ],
            ],
        ];
    }

    private function resultFromContent(string $customId, string $content): GenerationResult
    {
        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            return GenerationResult::invalid($customId, null, 'Malformed JSON response.');
        }

        $validationError = $this->validateDecodedPayload($decoded);

        if ($validationError) {
            return GenerationResult::invalid($customId, $decoded, $validationError);
        }

        return GenerationResult::valid($customId, $decoded);
    }

    private function validateDecodedPayload(array $payload): ?string
    {
        foreach (['title', 'slug', 'meta_description', 'enrichment_blocks'] as $key) {
            if (! array_key_exists($key, $payload)) {
                return "Missing required {$key}.";
            }
        }

        if (! is_string($payload['title']) || trim($payload['title']) === '') {
            return 'Missing required title.';
        }

        if (! is_string($payload['slug']) || trim($payload['slug']) === '') {
            return 'Missing required slug.';
        }

        if (! is_string($payload['meta_description']) || trim($payload['meta_description']) === '') {
            return 'Missing required meta_description.';
        }

        if (! is_array($payload['enrichment_blocks'])) {
            return 'Missing required enrichment_blocks array.';
        }

        return null;
    }

    private function model(): string
    {
        return $this->config['model'] ?? 'gpt-4o-mini';
    }

    private function temperature(): float
    {
        return 0.2;
    }

    private function unknownStatus(string $batchId, string $status): BatchStatus
    {
        Log::warning('Unknown OpenAI batch status encountered.', [
            'batch_id' => $batchId,
            'status' => $status,
        ]);

        return BatchStatus::Failed;
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
    }
}
