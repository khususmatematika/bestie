<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\GradeLevel;
use Illuminate\Database\Seeder;

/**
 * Tingkat X/XI/XII (aman dijalankan berulang). Mapel TIDAK dibuat di sini: admin mengisinya sendiri
 * lewat menu Mapel (atau Impor Mapel).
 */
class GradeLevelSeeder extends Seeder
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
    }
}
