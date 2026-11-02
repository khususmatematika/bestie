<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Package\ExtractPackageItems;
use App\Enums\ExtractionStatus;
use App\Exceptions\WorkflowException;
use App\Models\PackageFile;
use App\Models\QuestionPackage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ExtractPackageItemsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public readonly int $packageId)
    {
        $this->onQueue((string) config('banksoal.queues.extract', 'default'));
    }

    public function handle(ExtractPackageItems $action): void
    {
        $package = QuestionPackage::query()->find($this->packageId);

        if ($package === null || ! $package->isDraft()) {
            return;
        }

        try {
            $action->handle($package);
        } catch (WorkflowException) {
            // Pesan sudah disimpan pada laporan ekstraksi berkas; tampil di halaman paket.
        }
    }

    public function failed(?Throwable $exception): void
    {
        PackageFile::query()
            ->where('package_id', $this->packageId)
            ->whereIn('extraction_status', [ExtractionStatus::Pending->value, ExtractionStatus::Processing->value])
            ->update([
                'extraction_status' => ExtractionStatus::Failed->value,
                'extraction_report' => json_encode(['error' => 'Berkas tidak dapat diproses. Gunakan template standar atau isi butir secara manual.', 'warnings' => []]),
            ]);
    }
}
