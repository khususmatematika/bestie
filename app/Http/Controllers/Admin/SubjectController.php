<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\SubjectCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SubjectRequest;
use App\Models\GradeLevel;
use App\Models\Subject;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubjectController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $grades = GradeLevel::query()->orderBy('sort_order')->get();

        $subjects = Subject::query()
            ->with('gradeLevels:id')
            ->when($search !== '', fn ($q) => $q->where(
                fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"),
            ))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Subject $s): array => [
                'id' => $s->id,
                'code' => $s->code,
                'name' => $s->name,
                'category' => $s->category->value,
                'category_label' => $s->category->label(),
                'grade_level_ids' => $s->gradeLevels->pluck('id')->values(),
                'phases' => $grades
                    ->filter(fn (GradeLevel $g): bool => $s->gradeLevels->contains('id', $g->id))
                    ->map(fn (GradeLevel $g): array => ['grade' => $g->name, 'phase' => $g->phaseFor($s->category)])
                    ->values(),
                'is_active' => $s->is_active,
            ]);

        return Inertia::render('Admin/Master/Subjects', [
            'subjects' => $subjects,
            'grades' => $grades->map(fn (GradeLevel $g): array => [
                'id' => $g->id,
                'name' => $g->name,
                'phase' => $g->phase,
                'phase_elective' => $g->phase_elective,
            ])->values(),
            'categories' => collect(SubjectCategory::cases())->map(fn (SubjectCategory $c): array => ['value' => $c->value, 'label' => $c->label()])->values(),
            'filters' => ['search' => $search],
        ]);
    }

    public function store(SubjectRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $gradeIds = $data['grade_level_ids'];
        unset($data['grade_level_ids']);

        $subject = Subject::query()->create($data + ['is_active' => true]);
        $subject->gradeLevels()->sync($gradeIds);
        ActivityLogger::log('subject.created', $subject, ['code' => $subject->code]);

        return back()->with('success', 'Mapel ditambahkan.');
    }

    public function update(SubjectRequest $request, Subject $subject): RedirectResponse
    {
        $data = $request->validated();
        $gradeIds = $data['grade_level_ids'];
        unset($data['grade_level_ids']);

        $subject->update($data);
        $subject->gradeLevels()->sync($gradeIds);
        ActivityLogger::log('subject.updated', $subject);

        return back()->with('success', 'Mapel diperbarui.');
    }

    public function destroy(Subject $subject): RedirectResponse
    {
        if (! $subject->canBeDeleted()) {
            return back()->with('error', 'Mapel yang sudah dipakai pada penugasan tidak dapat dihapus. Nonaktifkan saja.');
        }

        ActivityLogger::log('subject.deleted', $subject, ['code' => $subject->code]);
        $subject->delete();

        return back()->with('success', 'Mapel dihapus.');
    }
}
