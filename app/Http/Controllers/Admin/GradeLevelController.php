<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\SubjectCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GradeLevelRequest;
use App\Models\GradeLevel;
use App\Models\TeachingAssignment;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class GradeLevelController extends Controller
{
    public function index(): Response
    {
        $grades = GradeLevel::query()
            ->withCount([
                'subjects as general_count' => fn ($q) => $q->where('category', SubjectCategory::Umum->value),
                'subjects as elective_count' => fn ($q) => $q->where('category', SubjectCategory::Pilihan->value),
            ])
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Admin/Master/GradeLevels', [
            'grades' => $grades->map(fn (GradeLevel $g): array => [
                'id' => $g->id,
                'name' => $g->name,
                'phase' => $g->phase,
                'phase_elective' => $g->phase_elective,
                'sort_order' => $g->sort_order,
                'general_count' => $g->general_count,
                'elective_count' => $g->elective_count,
            ])->values(),
        ]);
    }

    public function store(GradeLevelRequest $request): RedirectResponse
    {
        GradeLevel::query()->create($request->validated());

        return back()->with('success', 'Tingkat ditambahkan.');
    }

    public function update(GradeLevelRequest $request, GradeLevel $gradeLevel): RedirectResponse
    {
        $gradeLevel->update($request->validated());

        return back()->with('success', 'Tingkat diperbarui.');
    }

    public function destroy(GradeLevel $gradeLevel): RedirectResponse
    {
        if (TeachingAssignment::query()->where('grade_level_id', $gradeLevel->id)->exists()) {
            return back()->with('error', 'Tingkat yang sudah dipakai pada penugasan tidak dapat dihapus.');
        }

        ActivityLogger::log('grade_level.deleted', $gradeLevel, ['name' => $gradeLevel->name]);
        $gradeLevel->delete();

        return back()->with('success', 'Tingkat dihapus.');
    }
}
