<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AcademicYearRequest;
use App\Models\AcademicYear;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AcademicYearController extends Controller
{
    public function index(): Response
    {
        $years = AcademicYear::query()
            ->withCount('semesters')
            ->orderByDesc('name')
            ->get()
            ->map(fn (AcademicYear $y): array => [
                'id' => $y->id,
                'name' => $y->name,
                'starts_on' => $y->starts_on->toDateString(),
                'ends_on' => $y->ends_on->toDateString(),
                'is_active' => $y->is_active,
                'semesters_count' => $y->semesters_count,
            ]);

        return Inertia::render('Admin/Academic/Years', ['years' => $years]);
    }

    public function store(AcademicYearRequest $request): RedirectResponse
    {
        $year = AcademicYear::query()->create($request->validated());
        ActivityLogger::log('academic_year.created', $year, ['name' => $year->name]);

        return back()->with('success', "Tahun ajaran {$year->name} ditambahkan.");
    }

    public function update(AcademicYearRequest $request, AcademicYear $academicYear): RedirectResponse
    {
        $academicYear->update($request->validated());
        ActivityLogger::log('academic_year.updated', $academicYear);

        return back()->with('success', 'Tahun ajaran diperbarui.');
    }

    public function destroy(AcademicYear $academicYear): RedirectResponse
    {
        if (! $academicYear->canBeDeleted()) {
            return back()->with('error', 'Tahun ajaran yang sudah memiliki semester tidak dapat dihapus.');
        }

        ActivityLogger::log('academic_year.deleted', $academicYear, ['name' => $academicYear->name]);
        $academicYear->delete();

        return back()->with('success', 'Tahun ajaran dihapus.');
    }
}
