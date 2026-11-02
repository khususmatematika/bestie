<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\GradeLevel;
use App\Models\Subject;
use Illuminate\Database\Seeder;

/**
 * Data referensi bawaan (aman dijalankan berulang). Admin dapat mengubahnya di aplikasi.
 */
class ReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $grades = [
            // phase = fase mapel umum; phase_elective = fase mapel pilihan
            ['name' => 'X', 'phase' => 'Fase E', 'phase_elective' => 'Fase E', 'sort_order' => 1],
            ['name' => 'XI', 'phase' => 'Fase F', 'phase_elective' => 'Fase F+', 'sort_order' => 2],
            ['name' => 'XII', 'phase' => 'Fase F', 'phase_elective' => 'Fase F+', 'sort_order' => 3],
        ];

        foreach ($grades as $grade) {
            GradeLevel::query()->updateOrCreate(['name' => $grade['name']], $grade);
        }

        $subjects = [
            ['code' => 'PAI', 'name' => 'Pendidikan Agama dan Budi Pekerti', 'category' => 'umum'],
            ['code' => 'PPKN', 'name' => 'Pendidikan Pancasila dan Kewarganegaraan', 'category' => 'umum'],
            ['code' => 'BIN', 'name' => 'Bahasa Indonesia', 'category' => 'umum'],
            ['code' => 'MAT', 'name' => 'Matematika', 'category' => 'umum'],
            ['code' => 'BING', 'name' => 'Bahasa Inggris', 'category' => 'umum'],
            ['code' => 'SEJ', 'name' => 'Sejarah', 'category' => 'umum'],
            ['code' => 'PJOK', 'name' => 'Pendidikan Jasmani, Olahraga, dan Kesehatan', 'category' => 'umum'],
            ['code' => 'INF', 'name' => 'Informatika', 'category' => 'umum'],
            ['code' => 'FIS', 'name' => 'Fisika', 'category' => 'pilihan'],
            ['code' => 'KIM', 'name' => 'Kimia', 'category' => 'pilihan'],
            ['code' => 'BIO', 'name' => 'Biologi', 'category' => 'pilihan'],
            ['code' => 'EKO', 'name' => 'Ekonomi', 'category' => 'pilihan'],
            ['code' => 'GEO', 'name' => 'Geografi', 'category' => 'pilihan'],
            ['code' => 'SOS', 'name' => 'Sosiologi', 'category' => 'pilihan'],
        ];

        $gradeIds = GradeLevel::query()->pluck('id', 'name');

        foreach ($subjects as $data) {
            $subject = Subject::query()->firstOrCreate(['code' => $data['code']], $data + ['is_active' => true]);

            // Umum: X, XI, XII. Pilihan: XI, XII. (Tidak menimpa pengaturan admin yang sudah ada.)
            if ($subject->gradeLevels()->doesntExist()) {
                $names = $subject->isElective() ? ['XI', 'XII'] : ['X', 'XI', 'XII'];
                $subject->gradeLevels()->sync($gradeIds->only($names)->values()->all());
            }
        }
    }
}
