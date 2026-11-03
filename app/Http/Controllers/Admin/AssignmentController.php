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

        $assigned = TeachingAssignment::query()
            ->with('teacher:id,name')
            ->where('semester_id', $selected)
            ->get()
            ->groupBy(static fn (TeachingAssignment $a): string => $a->subject_id.'-'.$a->grade_level_id);

        // Semua mapel aktif (hasil unggah/impor) per tingkat beserta fasenya, urut abjad nama mapel lalu tingkat.
        $rows = Subject::query()
            ->with('gradeLevels')
            ->where('is_active', true)
            ->get()
            ->sortBy(static fn (Subject $s): string => mb_strtolower($s->name), SORT_NATURAL)
            ->flatMap(static fn (Subject $s) => $s->gradeLevels->map(static fn (GradeLevel $g): array => [
                'key' => $s->id.'-'.$g->id,
                'subject_id' => $s->id,
                'grade_level_id' => $g->id,
                'subject' => $s->name,
                'code' => $s->code,
                'grade' => $g->name,
                'phase' => $s->category !== null ? $g->phaseFor($s->category) : $g->phase,
                'sort' => $g->sort_order,
            ])->all())
            ->map(static fn (array $r) => $r + ['teachers' => ($assigned[$r['key']] ?? collect())->map(static fn (TeachingAssignment $a): array => [
                'assignment_id' => $a->id,
                'id' => $a->teacher_id,
                'name' => $a->teacher->name,
            ])->sortBy('name')->values()->all()])
            ->values()
            ->all();

        return Inertia::render('Admin/Assignments/Index', [
            'rows' => $rows,
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

    /**
     * Satu guru per mapel-tingkat: memilih guru menggantikan guru sebelumnya; kosong = lepas penugasan.
     */
    public function assign(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'semester_id' => ['required', 'integer', 'exists:semesters,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'grade_level_id' => ['required', 'integer', 'exists:grade_levels,id'],
            'teacher_id' => ['nullable', 'integer'],
        ]);

        $semester = Semester::query()->findOrFail($data['semester_id']);

        if ($semester->isArchived()) {
            return back()->with('error', 'Semester arsip bersifat hanya-baca.');
        }

        $subject = Subject::query()->with('gradeLevels:id')->findOrFail($data['subject_id']);

        if (! $subject->gradeLevels->contains('id', $data['grade_level_id'])) {
            return back()->with('error', 'Mapel ini tidak diajarkan pada tingkat tersebut.');
        }

        $teacherId = $data['teacher_id'] ?? null;

        if ($teacherId !== null && ! User::query()->whereKey($teacherId)->where('is_active', true)->whereHas('roles', fn ($q) => $q->whereIn('name', [RoleName::Teacher->value, RoleName::Validator->value]))->exists()) {
            return back()->with('error', 'Guru yang dipilih tidak valid atau tidak aktif.');
        }

        $scope = TeachingAssignment::query()->where([
            'semester_id' => $semester->id,
            'subject_id' => $subject->id,
            'grade_level_id' => $data['grade_level_id'],
        ]);

        (clone $scope)->when($teacherId !== null, fn ($q) => $q->where('teacher_id', '!=', $teacherId))->delete();

        if ($teacherId !== null) {
            TeachingAssignment::query()->firstOrCreate([
                'semester_id' => $semester->id,
                'subject_id' => $subject->id,
                'grade_level_id' => $data['grade_level_id'],
                'teacher_id' => $teacherId,
            ]);
        }

        ActivityLogger::log('assignment.set', $semester, ['subject_id' => $subject->id, 'grade_level_id' => $data['grade_level_id'], 'teacher_id' => $teacherId]);

        return back()->with('success', $teacherId === null ? 'Guru dilepas dari mapel.' : 'Guru pengampu disimpan.');
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
