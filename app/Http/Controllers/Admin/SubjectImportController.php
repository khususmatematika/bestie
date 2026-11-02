<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Master\ImportSubjects;
use App\Exports\SubjectImportTemplate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SubjectImportRequest;
use App\Models\GradeLevel;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class SubjectImportController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Master/SubjectImport', [
            'result' => session('subject_import'),
            'grades' => GradeLevel::query()->orderBy('sort_order')->get(['name', 'phase', 'phase_elective']),
        ]);
    }

    public function store(SubjectImportRequest $request, ImportSubjects $import): RedirectResponse
    {
        try {
            $result = $import->handle($request->file('file'), $request->boolean('update_existing'), $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['file' => 'Berkas tidak dapat dibaca. Pastikan memakai template resmi (.xlsx / .csv).']);
        }

        return redirect()
            ->route('admin.subjects.import')
            ->with('subject_import', $result)
            ->with('success', "Impor selesai: {$result['created']} dibuat, {$result['updated']} diperbarui, {$result['failed']} gagal.");
    }

    public function template(): BinaryFileResponse|\Symfony\Component\HttpFoundation\Response
    {
        return Excel::download(new SubjectImportTemplate, 'template-impor-mapel.xlsx');
    }
}
