<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\User\ImportTeachers;
use App\Enums\ImportStatus;
use App\Models\ImportBatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ImportTeachersJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public readonly int $batchId)
    {
        $this->onQueue((string) config('banksoal.queues.import', 'default'));
    }

    public function handle(ImportTeachers $action): void
    {
        $batch = ImportBatch::query()->find($this->batchId);

        // Idempoten: batch yang sudah selesai tidak diproses ulang.
        if ($batch === null || $batch->status->isFinished()) {
            return;
        }

        $action->handle($batch);
    }

    public function failed(?Throwable $exception): void
    {
        ImportBatch::query()->whereKey($this->batchId)->update([
            'status' => ImportStatus::Failed->value,
            'error_message' => 'Berkas tidak dapat dibaca. Pastikan memakai template resmi (.xlsx / .csv).',
            'finished_at' => now(),
        ]);
    }
}
