<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignmentRequest;
use App\Models\GradeLevel;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssignmentController extends Controller
{
    public function index(Request $request): Response
    {
        $semesters = Semester::query()->with('year')->orderByDesc('academic_year_id')->orderByDesc('term')->get();
        $selected = (int) $request->query('semester_id', (string) (Semester::current()?->id ?? $semesters->first()?->id ?? 0));
        $search = trim((string) $request->query('search', ''));

        $assignments = TeachingAssignment::query()
            ->with(['teacher:id,name', 'subject:id,name,code', 'gradeLevel:id,name'])
            ->where('semester_id', $selected)
            ->when($search !== '', fn ($q) => $q->whereHas(
                'teacher',
                fn ($t) => $t->where('name', 'like', "%{$search}%"),
            ))
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (TeachingAssignment $a): array => [
                'id' => $a->id,
                'teacher' => $a->teacher->name,
                'subject' => $a->subject->name,
                'code' => $a->subject->code,
                'grade' => $a->gradeLevel->name,
            ]);

        return Inertia::render('Admin/Assignments/Index', [
            'assignments' => $assignments,
            'semesters' => $semesters->map(fn (Semester $s): array => [
                'id' => $s->id,
                'label' => $s->label(),
                'archived' => $s->isArchived(),
            ])->values(),
            'selected_semester_id' => $selected,
            'teachers' => User::query()
                ->teachers()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'subjects' => Subject::query()
                ->with('gradeLevels:id')
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (Subject $s): array => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'code' => $s->code,
                    'grade_ids' => $s->gradeLevels->pluck('id')->values(),
                ])
                ->values(),
            'grades' => GradeLevel::query()->orderBy('sort_order')->get(['id', 'name']),
            'filters' => ['search' => $search],
        ]);
    }

    public function store(AssignmentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $semester = Semester::query()->findOrFail($data['semester_id']);

        if ($semester->isArchived()) {
            return back()->with('error', 'Semester arsip bersifat hanya-baca.');
        }

        $created = 0;

        foreach ($data['grade_level_ids'] as $gradeId) {
            $assignment = TeachingAssignment::query()->firstOrCreate([
                'semester_id' => $data['semester_id'],
                'teacher_id' => $data['teacher_id'],
                'subject_id' => $data['subject_id'],
                'grade_level_id' => $gradeId,
            ]);

            if ($assignment->wasRecentlyCreated) {
                $created++;
            }
        }

        ActivityLogger::log('assignment.created', $semester, ['teacher_id' => $data['teacher_id'], 'count' => $created]);

        return back()->with('success', $created > 0 ? "{$created} penugasan ditambahkan." : 'Penugasan tersebut sudah ada.');
    }

    public function destroy(TeachingAssignment $assignment): RedirectResponse
    {
        if ($assignment->semester->isArchived()) {
            return back()->with('error', 'Semester arsip bersifat hanya-baca.');
        }

        ActivityLogger::log('assignment.deleted', $assignment->semester, ['teacher_id' => $assignment->teacher_id]);
        $assignment->delete();

        return back()->with('success', 'Penugasan dihapus.');
    }

    /**
     * Rollover (FR-ACD-07): menyalin penugasan & cakupan validator dari semester lain.
     */
    public function rollover(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'from_semester_id' => ['required', 'integer', 'exists:semesters,id', 'different:to_semester_id'],
            'to_semester_id' => ['required', 'integer', 'exists:semesters,id'],
        ]);

        $to = Semester::query()->findOrFail($data['to_semester_id']);

        if ($to->isArchived()) {
            return back()->with('error', 'Semester tujuan adalah arsip.');
        }

        $copied = 0;

        TeachingAssignment::query()
            ->where('semester_id', $data['from_semester_id'])
            ->each(function (TeachingAssignment $a) use ($to, &$copied): void {
                $new = TeachingAssignment::query()->firstOrCreate([
                    'semester_id' => $to->id,
                    'teacher_id' => $a->teacher_id,
                    'subject_id' => $a->subject_id,
                    'grade_level_id' => $a->grade_level_id,
                ]);

                $copied += $new->wasRecentlyCreated ? 1 : 0;
            });

        ActivityLogger::log('assignment.rollover', $to, ['from' => $data['from_semester_id'], 'copied' => $copied]);

        return back()->with('success', "{$copied} penugasan disalin ke semester tujuan.");
    }
}
