<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Export\GeneratePackageExport;
use App\Enums\ExportStatus;
use App\Models\PackageExport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GeneratePackageExportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 180;

    public function __construct(public readonly int $exportId)
    {
        $this->onQueue((string) config('banksoal.queues.export', 'default'));
    }

    public function handle(GeneratePackageExport $action): void
    {
        $export = PackageExport::query()->find($this->exportId);

        if ($export === null) {
            return;
        }

        try {
            // Job ini kini hanya melengkapi PDF; bila DOCX belum ada (mis. gagal sebelumnya), dibuat dulu.
            if ($export->status !== ExportStatus::Ready) {
                $action->handle($export);
                $export->refresh();
            }

            $action->addPdf($export);
        } catch (Throwable $e) {
            report($e);
            $action->fail($export, $e);
        }
    }
}
