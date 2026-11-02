<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ImportStatus;
use App\Exports\TeacherImportTemplate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TeacherImportRequest;
use App\Jobs\ImportTeachersJob;
use App\Models\ImportBatch;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeacherImportController extends Controller
{
    public function index(): Response
    {
        $batches = ImportBatch::query()
            ->with('creator:id,name')
            ->where('type', 'teachers')
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (ImportBatch $b): array => $this->present($b));

        return Inertia::render('Admin/Teachers/Import', [
            'batches' => $batches,
            // Memicu polling otomatis di halaman selama masih ada batch yang berjalan.
            'hasRunning' => $batches->contains(fn (array $b): bool => ! $b['finished']),
        ]);
    }

    public function store(TeacherImportRequest $request): RedirectResponse
    {
        $file = $request->file('file');

        $path = $file->storeAs('imports/uploads', \Illuminate\Support\Str::uuid().'.'.$file->getClientOriginalExtension(), 'local');

        $batch = ImportBatch::query()->create([
            'type' => 'teachers',
            'original_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'status' => ImportStatus::Pending,
            'options' => ['update_existing' => $request->boolean('update_existing'), 'must_change_password' => $request->boolean('must_change_password', true)],
            'created_by' => $request->user()->id,
        ]);

        \App\Support\RunJob::dispatch(new ImportTeachersJob($batch->id));

        ActivityLogger::log('import.teachers.started', $batch, ['file' => $batch->original_name]);

        return redirect()
            ->route('admin.teachers.import')
            ->with('success', 'Berkas diterima dan sedang diproses.');
    }

    public function template(): BinaryFileResponse|\Symfony\Component\HttpFoundation\Response
    {
        return Excel::download(new TeacherImportTemplate, 'template-impor-guru.xlsx');
    }

    /**
     * Kartu akun berisi kata sandi awal: hanya dapat diunduh SATU kali,
     * lalu dihapus dari server (rules.md §6).
     */
    public function accounts(ImportBatch $batch): StreamedResponse|RedirectResponse
    {
        if ($batch->report_path === null || ! Storage::disk('local')->exists($batch->report_path)) {
            return back()->with('error', 'Kartu akun sudah diunduh atau tidak tersedia.');
        }

        $path = $batch->report_path;
        $content = Storage::disk('local')->get($path);

        Storage::disk('local')->delete($path);
        $batch->update(['report_path' => null]);

        ActivityLogger::log('import.accounts.downloaded', $batch);

        return response()->streamDownload(
            static function () use ($content): void {
                echo $content;
            },
            'kartu-akun-guru-'.$batch->id.'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function present(ImportBatch $batch): array
    {
        return [
            'id' => $batch->id,
            'file' => $batch->original_name,
            'status' => $batch->status->value,
            'status_label' => $batch->status->label(),
            'finished' => $batch->status->isFinished(),
            'total' => $batch->total,
            'success' => $batch->success,
            'failed' => $batch->failed,
            'error_message' => $batch->error_message,
            'has_accounts' => $batch->report_path !== null,
            'results' => $batch->results ?? [],
            'created_by' => $batch->creator?->name,
            'created_at' => $batch->created_at?->toIso8601String(),
        ];
    }
}
