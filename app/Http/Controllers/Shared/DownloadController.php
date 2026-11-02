<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shared;

use App\Actions\Export\RequestFinalExport;
use App\Enums\ExportStatus;
use App\Services\Export\RecapDocument;
use App\Services\Export\RecapWorkbook;
use App\Http\Controllers\Controller;
use App\Models\PackageExport;
use App\Models\QuestionPackage;
use App\Services\Export\PackageDocument;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Unduhan rekap & naskah (FR-DLD-01..04, FR-EXP-01): guru pemilik, validator terkait, dan admin.
 * Semua unduhan dicatat pada log aktivitas.
 */
class DownloadController extends Controller
{
    public function recap(Request $request, QuestionPackage $package, RecapWorkbook $workbook, RecapDocument $document): BinaryFileResponse
    {
        Gate::authorize('view', $package);

        $docx = $request->query('format') === 'docx';
        $path = tempnam(sys_get_temp_dir(), 'bs').($docx ? '.docx' : '.xlsx');

        $docx ? $document->build($package, $path) : $workbook->build($package, $path);

        ActivityLogger::log('download.recap', $package, ['format' => $docx ? 'docx' : 'xlsx']);

        return response()->download($path, 'rekap-validasi-'.$this->slug($package).($docx ? '.docx' : '.xlsx'))->deleteFileAfterSend(true);
    }

    public function validated(Request $request, QuestionPackage $package, PackageDocument $document): BinaryFileResponse
    {
        Gate::authorize('view', $package);

        $numbers = collect(explode(',', (string) $request->query('numbers', '')))
            ->map(static fn (string $n): int => (int) trim($n))
            ->filter(static fn (int $n): bool => $n > 0 && $n <= 999)
            ->unique()
            ->take(200)
            ->values()
            ->all();

        $path = tempnam(sys_get_temp_dir(), 'bs').'.docx';
        $document->build($package, $path, false, null, $numbers === [] ? null : $numbers);

        ActivityLogger::log('download.validated', $package, ['numbers' => $numbers]);

        return response()->download($path, 'soal-tervalidasi-'.$this->slug($package).'.docx')->deleteFileAfterSend(true);
    }

    public function requestFinal(Request $request, QuestionPackage $package, RequestFinalExport $action): RedirectResponse
    {
        Gate::authorize('view', $package);

        $export = $action->handle($package, $request->user());

        return back()->with('success', $export->status === ExportStatus::Ready
            ? 'Naskah final siap diunduh.'
            : 'Naskah final sedang disiapkan. Tombol unduh akan muncul otomatis.');
    }

    public function export(QuestionPackage $package, PackageExport $export, string $format): StreamedResponse
    {
        Gate::authorize('view', $package);

        abort_unless($export->package_id === $package->id && $export->status === ExportStatus::Ready, 404);
        abort_unless(in_array($format, ['docx', 'pdf'], true), 404);

        $path = $format === 'pdf' ? $export->pdf_path : $export->path;

        abort_if($path === null || ! Storage::disk('local')->exists($path), 404);

        ActivityLogger::log('download.final', $package, ['export_id' => $export->id, 'format' => $format]);

        return Storage::disk('local')->download($path, 'naskah-final-'.$this->slug($package).'.'.$format);
    }

    private function slug(QuestionPackage $package): string
    {
        return Str::slug($package->title) ?: 'paket-'.$package->id;
    }
}
