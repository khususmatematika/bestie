<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Academic\ActivateSemester;
use App\Enums\SemesterStatus;
use App\Enums\SemesterTerm;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SemesterRequest;
use App\Models\AcademicYear;
use App\Models\Semester;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SemesterController extends Controller
{
    public function index(): Response
    {
        $semesters = Semester::query()
            ->with('year')
            ->withCount(['periods', 'assignments'])
            ->orderByDesc('academic_year_id')
            ->orderByDesc('term')
            ->get()
            ->map(fn (Semester $s): array => [
                'id' => $s->id,
                'academic_year_id' => $s->academic_year_id,
                'label' => $s->label(),
                'term' => $s->term->value,
                'term_label' => $s->term->label(),
                'starts_on' => $s->starts_on->toDateString(),
                'ends_on' => $s->ends_on->toDateString(),
                'status' => $s->status->value,
                'status_label' => $s->status->label(),
                'periods_count' => $s->periods_count,
                'assignments_count' => $s->assignments_count,
            ]);

        return Inertia::render('Admin/Academic/Semesters', [
            'semesters' => $semesters,
            'years' => AcademicYear::query()->orderByDesc('name')->get(['id', 'name']),
            'terms' => array_map(
                static fn (SemesterTerm $t): array => ['value' => $t->value, 'label' => $t->label()],
                SemesterTerm::cases(),
            ),
        ]);
    }

    public function store(SemesterRequest $request): RedirectResponse
    {
        $semester = Semester::query()->create($request->validated() + ['status' => SemesterStatus::Rencana]);
        ActivityLogger::log('semester.created', $semester);

        return back()->with('success', 'Semester ditambahkan.');
    }

    public function update(SemesterRequest $request, Semester $semester): RedirectResponse
    {
        if ($semester->isArchived()) {
            return back()->with('error', 'Semester arsip bersifat hanya-baca.');
        }

        $semester->update($request->validated());
        ActivityLogger::log('semester.updated', $semester);

        return back()->with('success', 'Semester diperbarui.');
    }

    public function activate(Semester $semester, ActivateSemester $action): RedirectResponse
    {
        $action->handle($semester);

        return back()->with('success', 'Semester dijadikan aktif. Semester aktif sebelumnya otomatis diarsipkan.');
    }

    public function destroy(Semester $semester): RedirectResponse
    {
        if (! $semester->canBeDeleted()) {
            return back()->with('error', 'Semester yang sudah memiliki periode atau penugasan tidak dapat dihapus.');
        }

        ActivityLogger::log('semester.deleted', $semester);
        $semester->delete();

        return back()->with('success', 'Semester dihapus.');
    }
}
