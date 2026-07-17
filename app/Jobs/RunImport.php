<?php

namespace App\Jobs;

use App\Models\ImportBatch;
use App\Services\ImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * A 3000-row sheet has no business running inside a request (CLAUDE.md § 4.9).
 * Files under ImportService::QUEUE_THRESHOLD are committed inline instead, so a
 * small import stays instant and doesn't need a worker running.
 */
class RunImport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(public readonly int $batchId)
    {
    }

    public function handle(ImportService $importer): void
    {
        $batch = ImportBatch::find($this->batchId);

        if ($batch === null || $batch->status === 'completed') {
            return;
        }

        $importer->commit($batch);
    }

    public function failed(\Throwable $e): void
    {
        ImportBatch::where('id', $this->batchId)->update([
            'status' => 'failed',
            'errors' => [['row' => 0, 'column' => '-', 'reason' => $e->getMessage()]],
            'completed_at' => now(),
        ]);
    }
}
