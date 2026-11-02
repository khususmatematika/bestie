<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 20)->unique(); // contoh: 2026/2027
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_active')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('semesters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->string('term', 10); // ganjil | genap
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 10)->default('rencana')->index(); // rencana | aktif | arsip
            $table->timestamps();

            $table->unique(['academic_year_id', 'term']);
        });

        Schema::create('assessment_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('semester_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('type', 10); // sts | sas
            $table->date('upload_deadline')->nullable();
            $table->date('validation_deadline')->nullable();
            $table->date('revision_deadline')->nullable();
            $table->string('status', 10)->default('buka')->index(); // buka | ditutup
            $table->timestamps();

            $table->unique(['semester_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_periods');
        Schema::dropIfExists('semesters');
        Schema::dropIfExists('academic_years');
    }
};
