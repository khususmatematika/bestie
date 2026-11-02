<?php

declare(strict_types=1);

namespace App\Actions\Export;

use App\Enums\ExportStatus;
use App\Models\PackageExport;
use App\Services\Export\PackageDocument;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Membuat berkas ekspor final: DOCX (wajib) dan PDF (bila soffice dikonfigurasi), disimpan di disk privat
 * dengan nama UUID, lengkap dengan hash SHA-256 untuk bukti keutuhan (FR-EXP-06).
 */
final class GeneratePackageExport
{
    public function __construct(private readonly PackageDocument $document)
    {
    }

    public function handle(PackageExport $export): void
    {
        $export->loadMissing('package');
        $disk = Storage::disk('local');
        $disk->makeDirectory('exports');

        $name = 'exports/'.Str::uuid()->toString();
        $docx = $disk->path($name.'.docx');

        $this->document->build($export->package, $docx, true, $export->doc_number);

        // DOCX langsung siap diunduh; PDF (butuh LibreOffice, lambat) menyusul lewat addPdf() di antrian.
        $export->update([
            'status' => ExportStatus::Ready,
            'path' => $name.'.docx',
            'pdf_path' => null,
            'sha256' => hash_file('sha256', $docx) ?: null,
            'format_hash' => PackageDocument::fingerprint(),
            'generated_at' => now(),
            'error' => null,
        ]);

        ActivityLogger::log('export.ready', $export->package, ['export_id' => $export->id, 'doc_number' => $export->doc_number]);
    }

    public static function wantsPdf(): bool
    {
        $soffice = config('banksoal.soffice_path');

        return is_string($soffice) && $soffice !== '' && is_file($soffice);
    }

    /** Membuat PDF dari DOCX yang sudah jadi (dipanggil dari antrian). */
    public function addPdf(PackageExport $export): void
    {
        if ($export->status !== ExportStatus::Ready || $export->path === null || $export->pdf_path !== null) {
            return;
        }

        $disk = Storage::disk('local');
        $docx = $disk->path($export->path);
        $pdf = $this->toPdf($docx, dirname($docx));

        if ($pdf !== null) {
            $export->update(['pdf_path' => substr($export->path, 0, -5).'.pdf']);
        }
    }

    public function fail(PackageExport $export, Throwable $e): void
    {
        $export->update(['status' => ExportStatus::Failed, 'error' => mb_substr($e->getMessage(), 0, 500)]);
    }

    private function toPdf(string $docx, string $dir): ?string
    {
        $soffice = config('banksoal.soffice_path');

        if (! is_string($soffice) || $soffice === '' || ! is_file($soffice)) {
            return null;
        }

        try {
            $process = new Process([$soffice, '--headless', '--convert-to', 'pdf', '--outdir', $dir, $docx]);
            $process->setTimeout(120)->run();
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        $pdf = substr($docx, 0, -5).'.pdf';

        return is_file($pdf) ? $pdf : null;
    }
}
