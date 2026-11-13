<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Archive\ImportArchive;
use App\Enums\PackageStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ArchiveImportRequest;
use App\Models\ArchiveImport;
use App\Models\GradeLevel;
use App\Models\QuestionItem;
use App\Models\Subject;
use App\Support\ActivityLogger;
use App\Support\PerPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Throwable;

/**
 * Arsip soal tahun lalu untuk deteksi kemiripan: impor naskah lama + ringkasan paket Fix semester lama.
 */
class ArchiveController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Archive/Index', [
            'imports' => ArchiveImport::query()
                ->with(['subject:id,name', 'gradeLevel:id,name'])
                ->latest('id')
                ->paginate(PerPage::resolve($request))
                ->withQueryString()
                ->through(static fn (ArchiveImport $i): array => [
                    'id' => $i->id,
                    'subject' => $i->subject->name,
                    'grade' => $i->gradeLevel?->name,
                    'year_label' => $i->year_label,
                    'file' => $i->original_name,
                    'items' => $i->items_count,
                    'created_at' => $i->created_at?->toIso8601String(),
                ]),
            'subjects' => Subject::query()->orderBy('name')->get(['id', 'name', 'code']),
            'grades' => GradeLevel::query()->orderBy('sort_order')->get(['id', 'name']),
            'stats' => [
                'imported_items' => (int) ArchiveImport::query()->sum('items_count'),
                'fixed_items' => QuestionItem::query()->whereHas('package', fn ($q) => $q->whereIn('status', [PackageStatus::Fix->value, PackageStatus::Terkunci->value]))->count(),
            ],
        ]);
    }

    public function store(ArchiveImportRequest $request, ImportArchive $import): RedirectResponse
    {
        try {
            $result = $import->handle(
                $request->file('file'),
                (int) $request->input('subject_id'),
                $request->filled('grade_level_id') ? (int) $request->input('grade_level_id') : null,
                trim((string) $request->input('year_label')),
                $request->user(),
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['file' => 'Naskah tidak dapat dibaca. Pastikan berkas tidak rusak dan berformat .docx atau .pdf.']);
        }

        return back()->with('success', $result['import']->items_count.' butir diimpor ke arsip.');
    }

    public function destroy(ArchiveImport $archive): RedirectResponse
    {
        ActivityLogger::log('archive.deleted', $archive, ['year' => $archive->year_label, 'items' => $archive->items_count]);
        $archive->delete();

        return back()->with('success', 'Arsip dihapus.');
    }
}
