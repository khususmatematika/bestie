<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PeriodStatus;
use App\Enums\PeriodType;
use App\Enums\RoleName;
use App\Enums\SemesterStatus;
use App\Enums\SemesterTerm;
use App\Models\AcademicYear;
use App\Models\AssessmentPeriod;
use App\Models\GradeLevel;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Data contoh untuk lingkungan lokal/staging. JANGAN dijalankan di produksi.
 * Jalankan: php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('DemoSeeder tidak boleh dijalankan di produksi.');

            return;
        }

        $year = AcademicYear::query()->firstOrCreate(
            ['name' => '2026/2027'],
            ['starts_on' => '2026-07-13', 'ends_on' => '2027-06-26', 'is_active' => true],
        );

        $semester = Semester::query()->firstOrCreate(
            ['academic_year_id' => $year->id, 'term' => SemesterTerm::Ganjil->value],
            ['starts_on' => '2026-07-13', 'ends_on' => '2026-12-19', 'status' => SemesterStatus::Aktif],
        );

        AssessmentPeriod::query()->firstOrCreate(
            ['semester_id' => $semester->id, 'type' => PeriodType::Sts->value],
            [
                'name' => 'Sumatif Tengah Semester Ganjil',
                'upload_deadline' => '2026-09-14',
                'validation_deadline' => '2026-09-21',
                'revision_deadline' => '2026-09-28',
                'status' => PeriodStatus::Buka,
            ],
        );

        AssessmentPeriod::query()->firstOrCreate(
            ['semester_id' => $semester->id, 'type' => PeriodType::Sas->value],
            [
                'name' => 'Sumatif Akhir Semester Ganjil',
                'upload_deadline' => '2026-11-23',
                'validation_deadline' => '2026-11-30',
                'revision_deadline' => '2026-12-04',
                'status' => PeriodStatus::Buka,
            ],
        );

        $teachers = [
            ['Budi Santoso, S.Pd.', 'budi.santoso@banksoal.test', '198503122010011001', 'MAT', false],
            ['Siti Rahmawati, S.Pd.', 'siti.rahmawati@banksoal.test', '198807212012012002', 'BIN', true],
            ['Agus Prasetyo, S.Pd.', 'agus.prasetyo@banksoal.test', '198201052008011003', 'FIS', false],
        ];

        $grade = GradeLevel::query()->where('name', 'X')->first();

        foreach ($teachers as [$name, $email, $nip, $code, $isValidator]) {
            $user = User::query()->firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'nip' => $nip, 'password' => 'Password123', 'must_change_password' => false],
            );

            $user->syncRoles(array_filter([RoleName::Teacher->value, $isValidator ? RoleName::Validator->value : null]));

            $subject = Subject::query()->where('code', $code)->first();

            if ($subject !== null && $grade !== null) {
                TeachingAssignment::query()->firstOrCreate([
                    'semester_id' => $semester->id,
                    'teacher_id' => $user->id,
                    'subject_id' => $subject->id,
                    'grade_level_id' => $grade->id,
                ]);
            }
        }

        $this->command?->info('Data contoh dibuat. Kata sandi guru contoh: Password123');
    }
}
