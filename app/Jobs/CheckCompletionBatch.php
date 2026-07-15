<?php

namespace App\Jobs;

use App\DTOs\BatchStatus;
use App\DTOs\GenerationRequest;
use App\Enums\PageStatus;
use App\Models\Draw;
use App\Models\Page;
use App\Services\ContentProviderManager;
use App\Services\PageAssembler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CheckCompletionBatch implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public string $batchId)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $providerName = Page::query()
            ->where('batch_id', $this->batchId)
            ->value('provider') ?: config('content.default', 'openai');

        $provider = app(ContentProviderManager::class)->driver($providerName);
        $batchStatus = $provider->pollBatch($this->batchId);

        if ($batchStatus === BatchStatus::InProgress) {
            self::dispatch($this->batchId)->delay(now()->addMinutes(10));

            return;
        }

        if ($batchStatus === BatchStatus::Expired || $batchStatus === BatchStatus::Failed) {
            $this->markBatchFailed($batchStatus->value);

            return;
        }

        if ($batchStatus === BatchStatus::Completed) {
            $assembler = new PageAssembler;

            foreach ($provider->fetchResults($this->batchId) as $customId => $result) {
                try {
                    $request = GenerationRequest::fromCustomId($customId);
                } catch (\InvalidArgumentException $exception) {
                    Log::warning('Skipping batch result with invalid custom_id.', [
                        'batch_id' => $this->batchId,
                        'custom_id' => $customId,
                        'reason' => $exception->getMessage(),
                    ]);

                    continue;
                }

                $draw = Draw::query()
                    ->where('type', $request->game)
                    ->where('draw_number', $request->drawNumber)
                    ->first();

                if (! $draw) {
                    Log::warning('Skipping batch result with missing draw.', [
                        'batch_id' => $this->batchId,
                        'custom_id' => $customId,
                    ]);

                    continue;
                }

                $assembler->assemble($draw, $result);
            }

            return;
        }
    }

    private function markBatchFailed(string $status): void
    {
        Page::query()
            ->where('batch_id', $this->batchId)
            ->update([
                'status' => PageStatus::Failed->value,
                'generated_at' => null,
            ]);

        Log::warning('Draw page batch failed.', [
            'batch_id' => $this->batchId,
            'status' => $status,
        ]);
    }
}
