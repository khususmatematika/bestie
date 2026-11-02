<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Models\Semester;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Support\ActivityLogger;

/**
 * Menyamakan penugasan mengajar (mapel x tingkat) seorang guru pada satu semester dengan daftar dari formulir Akun Guru.
 * Hanya pasangan yang memang diajarkan (menu Mapel) yang dibuat; selebihnya diabaikan.
 */
final class SyncTeacherAssignments
{
    /**
     * @param  list<array{subject_id: int|string, grade_level_ids: list<int|string>}>  $rows
     * @return array{added: int, removed: int}
     */
    public function handle(User $teacher, ?Semester $semester, array $rows): array
    {
        if ($semester === null || $semester->isArchived()) {
            return ['added' => 0, 'removed' => 0];
        }

        $subjects = Subject::query()->with('gradeLevels:id')->whereIn('id', array_column($rows, 'subject_id'))->get()->keyBy('id');

        $wanted = [];

        foreach ($rows as $row) {
            $subject = $subjects->get((int) $row['subject_id']);

            if ($subject === null) {
                continue;
            }

            foreach ($row['grade_level_ids'] as $gradeId) {
                if ($subject->gradeLevels->contains('id', (int) $gradeId)) {
                    $wanted[$subject->id.'-'.(int) $gradeId] = [$subject->id, (int) $gradeId];
                }
            }
        }

        $added = 0;
        $removed = 0;

        $existing = TeachingAssignment::query()->where('semester_id', $semester->id)->where('teacher_id', $teacher->id)->get();

        foreach ($existing as $assignment) {
            $key = $assignment->subject_id.'-'.$assignment->grade_level_id;

            if (isset($wanted[$key])) {
                unset($wanted[$key]);
            } else {
                $assignment->delete();
                $removed++;
            }
        }

        foreach ($wanted as [$subjectId, $gradeId]) {
            TeachingAssignment::query()->create([
                'semester_id' => $semester->id,
                'teacher_id' => $teacher->id,
                'subject_id' => $subjectId,
                'grade_level_id' => $gradeId,
            ]);
            $added++;
        }

        if ($added + $removed > 0) {
            ActivityLogger::log('assignment.synced', $semester, ['teacher_id' => $teacher->id, 'added' => $added, 'removed' => $removed]);
        }

        return ['added' => $added, 'removed' => $removed];
    }
}
