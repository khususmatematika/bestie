<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Export\RequestFinalExport;
use App\Enums\ExportStatus;
use App\Enums\PackageStatus;
use App\Http\Controllers\Controller;
use App\Models\QuestionPackage;
use App\Services\Export\PackageDocument;
use App\Services\Export\RecapDocument;
use App\Services\Export\RecapWorkbook;
use App\Support\ActivityLogger;
use App\Support\CurrentPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * Unduh massal oleh admin: banyak paket sekaligus dalam satu berkas ZIP (naskah final, soal tervalidasi, atau rekap).
 * Mengikuti paket yang dicentang, atau seluruh hasil penyaringan pada halaman Monitoring.
 */
class PackageDownloadController extends Controller
{
    public const MAX_PACKAGES = 80;

    public function __invoke(Request $request, RequestFinalExport $final, PackageDocument $document, RecapWorkbook $workbook, RecapDocument $recapDocument): BinaryFileResponse|RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(['final', 'validated', 'recap_docx', 'recap_xlsx'])],
            'ids' => ['sometimes', 'array', 'max:'.self::MAX_PACKAGES],
            'ids.*' => ['integer'],
            'status' => ['sometimes', 'nullable', 'string'],
            'subject_id' => ['sometimes', 'nullable', 'integer'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $period = CurrentPeriod::resolve($request);

        if ($period === null) {
            return back()->with('error', 'Pilih periode asesmen terlebih dahulu.');
        }

        $search = trim((string) ($data['search'] ?? ''));
        $ids = array_map('intval', $data['ids'] ?? []);

        $packages = QuestionPackage::query()
            ->with(['subject:id,name,code,category', 'gradeLevel:id,name,phase,phase_elective', 'assessmentPeriod.semester.year', 'teacher:id,name', 'validator:id,name'])
            ->where('assessment_period_id', $period->id)
            ->when($ids !== [], fn ($q) => $q->whereIn('id', $ids))
            ->when($ids === [] && PackageStatus::tryFrom((string) ($data['status'] ?? '')) !== null, fn ($q) => $q->where('status', $data['status']))
            ->when($ids === [] && ! empty($data['subject_id']), fn ($q) => $q->where('subject_id', $data['subject_id']))
            ->when($ids === [] && $search !== '', fn ($q) => $q->where(
                fn ($w) => $w->where('title', 'like', "%{$search}%")
                    ->orWhereHas('teacher', fn ($t) => $t->where('name', 'like', "%{$search}%")),
            ))
            ->where('status', '!=', PackageStatus::Draft->value)
            ->orderBy('subject_id')->orderBy('grade_level_id')->orderBy('id')
            ->limit(self::MAX_PACKAGES + 1)
            ->get();

        if ($packages->isEmpty()) {
            return back()->with('error', 'Tidak ada paket (selain draf) yang cocok untuk diunduh.');
        }

        if ($packages->count() > self::MAX_PACKAGES) {
            return back()->with('error', 'Terlalu banyak paket sekaligus (maks. '.self::MAX_PACKAGES.'). Persempit dengan filter mapel/status.');
        }

        set_time_limit(600);

        $kind = $data['kind'];
        $zipPath = tempnam(sys_get_temp_dir(), 'bz').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $temp = [];
        $notes = [];
        $added = 0;
        $used = [];

        foreach ($packages as $package) {
            $base = $this->entryName($package, $used);
            $path = null;
            $ext = 'docx';

            try {
                if ($kind === 'final') {
                    if (! in_array($package->status, [PackageStatus::Fix, PackageStatus::Terkunci], true)) {
                        $notes[] = "{$package->title}: dilewati (belum Fix).";

                        continue;
                    }

                    $export = $final->handle($package, $request->user());

                    if ($export->status !== ExportStatus::Ready || $export->path === null || ! Storage::disk('local')->exists($export->path)) {
                        $notes[] = "{$package->title}: naskah final belum tersedia.";

                        continue;
                    }

                    $path = Storage::disk('local')->path($export->path);
                } elseif ($kind === 'validated') {
                    if (! $package->items()->where('status', 'disetujui')->exists()) {
                        $notes[] = "{$package->title}: dilewati (belum ada soal yang disetujui).";

                        continue;
                    }

                    $path = $temp[] = tempnam(sys_get_temp_dir(), 'bd').'.docx';
                    $document->build($package, $path, false);
                } elseif ($kind === 'recap_xlsx') {
                    $ext = 'xlsx';
                    $path = $temp[] = tempnam(sys_get_temp_dir(), 'br').'.xlsx';
                    $workbook->build($package, $path);
                } else {
                    $path = $temp[] = tempnam(sys_get_temp_dir(), 'br').'.docx';
                    $recapDocument->build($package, $path);
                }
            } catch (\Throwable $e) {
                report($e);
                $notes[] = "{$package->title}: gagal dibuat.";

                continue;
            }

            $zip->addFile($path, $base.'.'.$ext);
            $added++;
        }

        if ($notes !== []) {
            $zip->addFromString('CATATAN.txt', "Paket yang tidak ikut terunduh:\r\n- ".implode("\r\n- ", $notes)."\r\n");
        }

        $zip->close();

        foreach ($temp as $file) {
            // File sementara baru aman dihapus setelah zip ditutup (addFile membaca saat close).
            @unlink($file);
        }

        if ($added === 0) {
            @unlink($zipPath);

            return back()->with('error', 'Tidak ada berkas yang bisa dibuat. '.implode(' ', array_slice($notes, 0, 3)));
        }

        ActivityLogger::log('download.bulk', $period, ['kind' => $kind, 'packages' => $added]);

        $label = ['final' => 'naskah-final', 'validated' => 'soal-tervalidasi', 'recap_docx' => 'rekap-validasi', 'recap_xlsx' => 'rekap-validasi-xlsx'][$kind];

        return response()->download($zipPath, $label.'-'.Str::slug($period->name ?? 'periode').'-'.now()->format('Ymd-His').'.zip')->deleteFileAfterSend(true);
    }

    /**
     * Nama berkas di dalam ZIP: Mapel/Kelas X - Guru - Judul (unik).
     *
     * @param  array<string, bool>  $used
     */
    private function entryName(QuestionPackage $package, array &$used): string
    {
        $dir = Str::slug($package->subject?->name ?? 'mapel');
        $name = trim(Str::slug('kelas-'.($package->gradeLevel?->name ?? '')).'-'.Str::slug($package->teacher?->name ?? 'guru').'-'.Str::slug($package->title), '-');
        $entry = $dir.'/'.mb_substr($name, 0, 120);

        if (isset($used[$entry])) {
            $entry .= '-'.$package->id;
        }

        $used[$entry] = true;

        return $entry;
    }
}
