<?php

namespace Tests\Unit\Services\Providers;

use App\DTOs\BatchStatus;
use App\DTOs\GenerationRequest;
use App\Services\Providers\OpenAiContentProvider;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Batches\BatchResponse;
use OpenAI\Responses\Files\CreateResponse as FileCreateResponse;
use Tests\TestCase;

class OpenAiContentProviderTest extends TestCase
{
    public function test_submit_batch_writes_jsonl_uploads_it_and_returns_batch_id(): void
    {
        $provider = new OpenAiContentProvider();
        $request = $this->request();

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

        $this->assertSame('batch_123', $batchId);
        $this->assertFileExists(storage_path('app/private/commands.jsonl'));

        $line = trim((string) file_get_contents(storage_path('app/private/commands.jsonl')));
        $payload = json_decode($line, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('page_megasena_2500', $payload['custom_id']);
        $this->assertSame('POST', $payload['method']);
        $this->assertSame('/v1/chat/completions', $payload['url']);
        $this->assertSame('gpt-4o-mini', $payload['body']['model']);
        $this->assertSame('prompt text', $payload['body']['messages'][1]['content']);
        $this->assertSame('json_schema', $payload['body']['response_format']['type']);

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
    }

    public function test_poll_batch_maps_in_progress_status(): void
    {
        OpenAI::fake([
            BatchResponse::fake($this->batchAttributes('in_progress')),
        ]);

        $this->assertSame(BatchStatus::InProgress, (new OpenAiContentProvider())->pollBatch('batch_123'));
    }

    public function test_poll_batch_maps_completed_status(): void
    {
        OpenAI::fake([
            BatchResponse::fake($this->batchAttributes('completed')),
        ]);

        $this->assertSame(BatchStatus::Completed, (new OpenAiContentProvider())->pollBatch('batch_123'));
    }

    public function test_poll_batch_maps_expired_status(): void
    {
        OpenAI::fake([
            BatchResponse::fake($this->batchAttributes('expired')),
        ]);

        $this->assertSame(BatchStatus::Expired, (new OpenAiContentProvider())->pollBatch('batch_123'));
    }

    public function test_poll_batch_maps_cancelled_status_to_failed(): void
    {
        OpenAI::fake([
            BatchResponse::fake($this->batchAttributes('cancelled')),
        ]);

        $this->assertSame(BatchStatus::Failed, (new OpenAiContentProvider())->pollBatch('batch_123'));
    }

    public function test_poll_batch_maps_unknown_status_to_failed_and_logs_it(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Unknown OpenAI batch status encountered.'
                    && $context['batch_id'] === 'batch_123'
                    && $context['status'] === 'mystery';
            });

        OpenAI::fake([
            BatchResponse::fake($this->batchAttributes('mystery')),
        ]);

        $this->assertSame(BatchStatus::Failed, (new OpenAiContentProvider())->pollBatch('batch_123'));
    }

    public function test_fetch_results_keys_generation_results_by_custom_id(): void
    {
        OpenAI::fake([
            BatchResponse::fake($this->batchAttributes('completed', 'file-out')),
            '{"custom_id":"page_megasena_2500","response":{"body":{"choices":[{"message":{"content":"{\\"title\\":\\"Resultado 2500\\",\\"slug\\":\\"mega-sena/resultado/2500\\",\\"meta_description\\":\\"Resumo\\",\\"enrichment_blocks\\":[]}"}}]}}}',
        ]);

        $results = (new OpenAiContentProvider())->fetchResults('batch_123');
        $results = is_array($results) ? $results : iterator_to_array($results);

        $this->assertArrayHasKey('page_megasena_2500', $results);
        $this->assertTrue($results['page_megasena_2500']->valid);
        $this->assertSame([
            'title' => 'Resultado 2500',
            'slug' => 'mega-sena/resultado/2500',
            'meta_description' => 'Resumo',
            'enrichment_blocks' => [],
        ], $results['page_megasena_2500']->payload);
        $this->assertNull($results['page_megasena_2500']->failureReason);
    }

    private function request(): GenerationRequest
    {
        return GenerationRequest::fromCustomId(
            'page_megasena_2500',
            context: ['draw' => 2500],
            prompt: 'prompt text',
            schema: ['type' => 'object'],
        );
    }

    private function batchAttributes(string $status, ?string $outputFileId = null): array
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
}
