<?php

declare(strict_types=1);

namespace App\Support;

use App\Actions\Export\RequestFinalExport;
use App\Enums\ExportStatus;
use App\Enums\PackageStatus;
use App\Models\PackageExport;
use App\Models\QuestionPackage;
use App\Services\Export\PackageDocument;

/**
 * Data panel unduhan untuk halaman paket (guru, validator): tautan rekap, soal tervalidasi, dan status ekspor final.
 */
final class DownloadsPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function for(QuestionPackage $package): array
    {
        $finished = in_array($package->status, [PackageStatus::Fix, PackageStatus::Terkunci], true);

        $export = $finished
            ? PackageExport::query()->where('package_id', $package->id)->latest('id')->first()
            : null;

        // Naskah yang macet "queued" (mis. antrian tidak berjalan) dibuat sekarang; DOCX hanya butuh beberapa detik.
        if ($export !== null && $export->status === ExportStatus::Queued) {
            try {
                $export = app(RequestFinalExport::class)->handle($package);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return [
            'recap_url' => route('downloads.recap', $package),
            'recap_docx_url' => route('downloads.recap', ['package' => $package, 'format' => 'docx']),
            'validated_url' => route('downloads.validated', $package),
            'final_url' => route('downloads.final', $package),
            'is_final' => $finished,
            'final' => $export === null ? null : [
                'status' => $export->status->value,
                'doc_number' => $export->doc_number,
                'generated_at' => $export->generated_at?->toIso8601String(),
                'sha256' => $export->sha256,
                'docx_url' => $export->status === ExportStatus::Ready ? route('downloads.export', [$package, $export, 'docx']) : null,
                'pdf_url' => $export->status === ExportStatus::Ready && $export->pdf_path !== null ? route('downloads.export', [$package, $export, 'pdf']) : null,
                'error' => $export->status === ExportStatus::Failed ? $export->error : null,
                // Format dokumen (kop, font, dll.) berubah sejak naskah ini dibuat.
                'stale' => $export->status === ExportStatus::Ready && $export->format_hash !== PackageDocument::fingerprint(),
            ],
        ];
    }
}
