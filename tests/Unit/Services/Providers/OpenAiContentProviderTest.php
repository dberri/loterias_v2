<?php

namespace Tests\Unit\Services\Providers\OpenAiContentProviderTest;

use App\DTOs\BatchStatus;
use App\DTOs\GenerationRequest;
use App\Services\Providers\OpenAiContentProvider;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Batches\BatchResponse;
use OpenAI\Responses\Chat\CreateResponse;
use OpenAI\Responses\Files\CreateResponse as FileCreateResponse;

test('submit batch writes jsonl uploads it and returns batch id', function () {
    $provider = new OpenAiContentProvider;
    $request = request();

    $fake = OpenAI::fake([
        FileCreateResponse::fake([
            'id' => 'file-123',
            'object' => 'file',
            'bytes' => 0,
            'created_at' => 1_700_000_000,
            'filename' => 'commands.jsonl',
            'purpose' => 'batch',
            'status' => 'succeeded',
            'status_details' => null,
        ]),
        BatchResponse::fake([
            'id' => 'batch_123',
            'object' => 'batch',
            'endpoint' => '/v1/chat/completions',
            'errors' => null,
            'input_file_id' => 'file-123',
            'completion_window' => '24h',
            'status' => 'in_progress',
            'output_file_id' => null,
            'error_file_id' => null,
            'created_at' => 1_700_000_001,
            'in_progress_at' => 1_700_000_002,
            'expires_at' => null,
            'finalizing_at' => null,
            'completed_at' => null,
            'failed_at' => null,
            'expired_at' => null,
            'cancelling_at' => null,
            'cancelled_at' => null,
            'request_counts' => [
                'total' => 1,
                'completed' => 0,
                'failed' => 0,
            ],
            'metadata' => [
                'customer_id' => 'user_123456789',
                'batch_description' => 'Nightly eval job',
            ],
        ]),
    ]);

    $batchId = $provider->submitBatch([$request]);

    expect($batchId)->toBe('batch_123');
    expect(storage_path('app/private/commands.jsonl'))->toBeFile();

    $line = trim((string) file_get_contents(storage_path('app/private/commands.jsonl')));
    $payload = json_decode($line, true, flags: JSON_THROW_ON_ERROR);

    expect($payload['custom_id'])->toBe('page_megasena_2500');
    expect($payload['method'])->toBe('POST');
    expect($payload['url'])->toBe('/v1/chat/completions');
    expect($payload['body']['model'])->toBe('gpt-4o-mini');
    expect($payload['body']['messages'][1]['content'])->toBe('prompt text');
    expect($payload['body']['response_format']['type'])->toBe('json_schema');

    $fake->files()->assertSent(function (string $method, array $parameters): bool {
        return $method === 'upload'
            && $parameters['purpose'] === 'batch'
            && is_resource($parameters['file']);
    });

    $fake->batches()->assertSent(function (string $method, array $parameters): bool {
        return $method === 'create'
            && $parameters['input_file_id'] === 'file-123'
            && $parameters['endpoint'] === '/v1/chat/completions'
            && $parameters['completion_window'] === '24h';
    });
});

test('poll batch maps in progress status', function () {
    OpenAI::fake([
        BatchResponse::fake(batchAttributes('in_progress')),
    ]);

    expect((new OpenAiContentProvider)->pollBatch('batch_123'))->toBe(BatchStatus::InProgress);
});

test('poll batch maps completed status', function () {
    OpenAI::fake([
        BatchResponse::fake(batchAttributes('completed')),
    ]);

    expect((new OpenAiContentProvider)->pollBatch('batch_123'))->toBe(BatchStatus::Completed);
});

test('poll batch maps expired status', function () {
    OpenAI::fake([
        BatchResponse::fake(batchAttributes('expired')),
    ]);

    expect((new OpenAiContentProvider)->pollBatch('batch_123'))->toBe(BatchStatus::Expired);
});

test('poll batch maps cancelled status to failed', function () {
    OpenAI::fake([
        BatchResponse::fake(batchAttributes('cancelled')),
    ]);

    expect((new OpenAiContentProvider)->pollBatch('batch_123'))->toBe(BatchStatus::Failed);
});

test('poll batch maps unknown status to failed and logs it', function () {
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            return $message === 'Unknown OpenAI batch status encountered.'
                && $context['batch_id'] === 'batch_123'
                && $context['status'] === 'mystery';
        });

    OpenAI::fake([
        BatchResponse::fake(batchAttributes('mystery')),
    ]);

    expect((new OpenAiContentProvider)->pollBatch('batch_123'))->toBe(BatchStatus::Failed);
});

test('fetch results keys generation results by custom id', function () {
    OpenAI::fake([
        BatchResponse::fake(batchAttributes('completed', 'file-out')),
        '{"custom_id":"page_megasena_2500","response":{"body":{"choices":[{"message":{"content":"{\\"title\\":\\"Resultado 2500\\",\\"slug\\":\\"mega-sena/resultado/2500\\",\\"meta_description\\":\\"Resumo\\",\\"enrichment_blocks\\":[]}"}}]}}}',
    ]);

    $results = (new OpenAiContentProvider)->fetchResults('batch_123');
    $results = is_array($results) ? $results : iterator_to_array($results);

    expect($results)->toHaveKey('page_megasena_2500');
    expect($results['page_megasena_2500']->valid)->toBeTrue();
    expect($results['page_megasena_2500']->payload)->toBe([
        'title' => 'Resultado 2500',
        'slug' => 'mega-sena/resultado/2500',
        'meta_description' => 'Resumo',
        'enrichment_blocks' => [],
    ]);
    expect($results['page_megasena_2500']->failureReason)->toBeNull();
});

test('generate one returns a valid result with json schema enforced', function () {
    $fake = OpenAI::fake([
        CreateResponse::fake([
            'id' => 'chatcmpl-123',
            'object' => 'chat.completion',
            'created' => 1_700_000_010,
            'model' => 'gpt-4o-mini',
            'system_fingerprint' => null,
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => '{"title":"Resultado 2500","slug":"mega-sena/resultado/2500","meta_description":"Resumo","enrichment_blocks":[]}',
                        'function_call' => null,
                        'tool_calls' => [],
                    ],
                    'logprobs' => null,
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 12,
                'total_tokens' => 22,
            ],
        ]),
    ]);

    $result = (new OpenAiContentProvider)->generateOne(request());

    expect($result->valid)->toBeTrue();
    expect($result->customId)->toBe('page_megasena_2500');
    expect($result->payload)->toBe([
        'title' => 'Resultado 2500',
        'slug' => 'mega-sena/resultado/2500',
        'meta_description' => 'Resumo',
        'enrichment_blocks' => [],
    ]);
    expect($result->failureReason)->toBeNull();

    $fake->chat()->assertSent(function (string $method, array $parameters): bool {
        return $method === 'create'
            && $parameters['model'] === 'gpt-4o-mini'
            && $parameters['response_format']['type'] === 'json_schema'
            && $parameters['response_format']['json_schema']['strict'] === true
            && $parameters['response_format']['json_schema']['schema'] === ['type' => 'object'];
    });
});

test('generate one marks malformed json as invalid', function () {
    OpenAI::fake([
        CreateResponse::fake([
            'id' => 'chatcmpl-123',
            'object' => 'chat.completion',
            'created' => 1_700_000_011,
            'model' => 'gpt-4o-mini',
            'system_fingerprint' => null,
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'not-json',
                        'function_call' => null,
                        'tool_calls' => [],
                    ],
                    'logprobs' => null,
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 12,
                'total_tokens' => 22,
            ],
        ]),
    ]);

    $result = (new OpenAiContentProvider)->generateOne(request());

    expect($result->valid)->toBeFalse();
    expect($result->failureReason)->toBe('Malformed JSON response.');
    expect($result->payload)->toBeNull();
});

test('generate one marks semantically invalid payload as invalid', function () {
    OpenAI::fake([
        CreateResponse::fake([
            'id' => 'chatcmpl-123',
            'object' => 'chat.completion',
            'created' => 1_700_000_012,
            'model' => 'gpt-4o-mini',
            'system_fingerprint' => null,
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => '{"title":"","slug":"mega-sena/resultado/2500","meta_description":"Resumo","enrichment_blocks":[]}',
                        'function_call' => null,
                        'tool_calls' => [],
                    ],
                    'logprobs' => null,
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 12,
                'total_tokens' => 22,
            ],
        ]),
    ]);

    $result = (new OpenAiContentProvider)->generateOne(request());

    expect($result->valid)->toBeFalse();
    expect($result->failureReason)->toBe('Missing required title.');
    expect($result->payload)->toBe([
        'title' => '',
        'slug' => 'mega-sena/resultado/2500',
        'meta_description' => 'Resumo',
        'enrichment_blocks' => [],
    ]);
});

function request(): GenerationRequest
{
    return GenerationRequest::fromCustomId(
        'page_megasena_2500',
        context: ['draw' => 2500],
        prompt: 'prompt text',
        schema: ['type' => 'object'],
    );
}

function batchAttributes(string $status, ?string $outputFileId = null): array
{
    return [
        'id' => 'batch_123',
        'object' => 'batch',
        'endpoint' => '/v1/chat/completions',
        'errors' => null,
        'input_file_id' => 'file-123',
        'completion_window' => '24h',
        'status' => $status,
        'output_file_id' => $outputFileId,
        'error_file_id' => null,
        'created_at' => 1_700_000_001,
        'in_progress_at' => null,
        'expires_at' => null,
        'finalizing_at' => null,
        'completed_at' => null,
        'failed_at' => null,
        'expired_at' => null,
        'cancelling_at' => null,
        'cancelled_at' => null,
        'request_counts' => [
            'total' => 1,
            'completed' => 0,
            'failed' => 0,
        ],
        'metadata' => [
            'customer_id' => 'user_123456789',
            'batch_description' => 'Nightly eval job',
        ],
    ];
}
