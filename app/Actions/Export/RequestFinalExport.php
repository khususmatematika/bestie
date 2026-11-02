<?php

declare(strict_types=1);

namespace App\Actions\Export;

use App\Enums\ExportStatus;
use App\Enums\PackageStatus;
use App\Exceptions\WorkflowException;
use App\Jobs\GeneratePackageExportJob;
use App\Models\PackageExport;
use App\Models\QuestionPackage;
use App\Models\User;
use App\Services\Export\PackageDocument;
use App\Support\ActivityLogger;
use App\Support\ExportFormat;
use Illuminate\Support\Facades\DB;

/**
 * Meminta ekspor final paket (FR-EXP-01, FR-EXP-05). Hanya untuk paket Fix/Terkunci. Dipanggil otomatis saat
 * paket mencapai Fix (langkah 8), atau manual oleh pengguna berhak. Permintaan berulang memakai ekspor yang sama
 * selama paket belum berubah sejak Fix.
 */
final class RequestFinalExport
{
    public function handle(QuestionPackage $package, ?User $by = null): PackageExport
    {
        if (! in_array($package->status, [PackageStatus::Fix, PackageStatus::Terkunci], true)) {
            throw new WorkflowException('Unduh final hanya tersedia setelah semua nomor disetujui (paket berstatus Fix).');
        }

        $created = false;

        $export = DB::transaction(function () use ($package, $by, &$created): PackageExport {
            $since = $package->fixed_at ?? $package->created_at;

            $existing = PackageExport::query()
                ->where('package_id', $package->id)
                ->whereIn('status', [ExportStatus::Queued->value, ExportStatus::Ready->value])
                ->where('created_at', '>=', $since)
                ->latest('id')
                ->first();

            // Naskah yang sudah jadi dipakai ulang hanya bila format dokumennya (kop, font, dll.) masih sama.
            if ($existing !== null && $existing->status === ExportStatus::Ready && $existing->format_hash === PackageDocument::fingerprint()) {
                return $existing;
            }

            // Permintaan lama yang macet di antrian (antrian tidak berjalan) diproses ulang sekarang juga.
            if ($existing !== null && $existing->status === ExportStatus::Queued) {
                $created = true;

                return $existing;
            }

            $export = PackageExport::query()->create([
                'package_id' => $package->id,
                'requested_by' => $by?->id,
                'status' => ExportStatus::Queued,
            ]);

            $export->update(['doc_number' => sprintf(
                '%s/%s/%05d-%d',
                ExportFormat::get()['doc_prefix'] ?: 'NSK',
                now()->format('Y'),
                $package->id,
                $export->id,
            )]);

            $created = true;

            ActivityLogger::log('export.requested', $package, ['export_id' => $export->id], $by);

            return $export;
        });

        if ($created) {
            // DOCX dibuat langsung (cepat) agar tombol unduh langsung aktif; PDF menyusul di antrian bila dikonfigurasi.
            try {
                app(GeneratePackageExport::class)->handle($export);
            } catch (\Throwable $e) {
                report($e);
                app(GeneratePackageExport::class)->fail($export, $e);
            }

            if (GeneratePackageExport::wantsPdf() && $export->refresh()->status === ExportStatus::Ready) {
                \App\Support\RunJob::dispatch(new GeneratePackageExportJob($export->id));
            }
        }

        return $export->refresh();
    }
}
