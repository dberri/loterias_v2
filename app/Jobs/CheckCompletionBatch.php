<?php

namespace App\Jobs;

use App\Services\ContentCreator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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
        // Check if the batch is completed
        $service = new ContentCreator();
        $batchData = $service->retrieveBatch($this->batchId);

        if ($batchData['status'] === 'in_progress') {
            self::dispatch($this->batchId)->delay(now()->addMinutes(10));
            return;
        }

        if (in_array($batchData['status'], ['expired', 'cancelled'])) {
            return;
        }

        if ($batchData['status'] === 'completed') {
            $service->downloadOutputFile($this->batchId, $batchData['output_file_id']);
            $service->updatePagesContent($this->batchId);
        }
    }
}
