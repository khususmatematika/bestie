<?php

declare(strict_types=1);

namespace App\Actions\Academic;

use App\Enums\SemesterStatus;
use App\Models\AcademicYear;
use App\Models\Semester;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;

final class ActivateSemester
{
    /**
     * Hanya satu semester yang berstatus aktif; semester aktif sebelumnya menjadi arsip.
     */
    public function handle(Semester $semester): Semester
    {
        DB::transaction(function () use ($semester): void {
            Semester::query()
                ->where('status', SemesterStatus::Aktif->value)
                ->whereKeyNot($semester->id)
                ->update(['status' => SemesterStatus::Arsip->value]);

            $semester->update(['status' => SemesterStatus::Aktif]);

            AcademicYear::query()->update(['is_active' => false]);
            AcademicYear::query()->whereKey($semester->academic_year_id)->update(['is_active' => true]);
        });

        ActivityLogger::log('semester.activated', $semester);

        return $semester->refresh();
    }
}
