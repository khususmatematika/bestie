<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table): void {
            $table->string('category', 10)->default('umum')->after('name'); // umum | pilihan
        });

        Schema::table('grade_levels', function (Blueprint $table): void {
            $table->string('phase_elective', 10)->nullable()->after('phase'); // fase untuk mapel pilihan
        });

        Schema::create('grade_level_subject', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grade_level_id')->constrained()->cascadeOnDelete();

            $table->unique(['subject_id', 'grade_level_id']);
        });

        // --- Data yang sudah ada ---------------------------------------------------------
        DB::table('subjects')->where('group', 'Peminatan')->update(['category' => 'pilihan']);

        DB::table('grade_levels')->where('name', 'X')->update(['phase' => 'Fase E', 'phase_elective' => 'Fase E']);
        DB::table('grade_levels')->whereIn('name', ['XI', 'XII'])->update(['phase' => 'Fase F', 'phase_elective' => 'Fase F+']);

        // Mapel lama ditautkan ke tingkat: umum = X/XI/XII, pilihan = XI/XII.
        $grades = DB::table('grade_levels')->get(['id', 'name']);
        $rows = [];

        foreach (DB::table('subjects')->get(['id', 'category']) as $subject) {
            foreach ($grades as $grade) {
                if ($subject->category === 'pilihan' && $grade->name === 'X') {
                    continue;
                }

                $rows[] = ['subject_id' => $subject->id, 'grade_level_id' => $grade->id];
            }
        }

        if ($rows !== []) {
            DB::table('grade_level_subject')->insert($rows);
        }

        Schema::table('subjects', function (Blueprint $table): void {
            $table->dropColumn('group');
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table): void {
            $table->string('group', 50)->nullable()->after('name');
        });

        Schema::dropIfExists('grade_level_subject');

        Schema::table('grade_levels', function (Blueprint $table): void {
            $table->dropColumn('phase_elective');
        });

        Schema::table('subjects', function (Blueprint $table): void {
            $table->dropColumn('category');
        });
    }
};
