<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teaching_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('semester_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('grade_level_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['semester_id', 'teacher_id', 'subject_id', 'grade_level_id'], 'teaching_assignments_unique');
            $table->index(['semester_id', 'teacher_id']);
        });

        Schema::create('validator_scopes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('semester_id')->constrained()->restrictOnDelete();
            $table->foreignId('validator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->restrictOnDelete(); // null = semua mapel
            $table->timestamps();

            $table->index(['semester_id', 'validator_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('validator_scopes');
        Schema::dropIfExists('teaching_assignments');
    }
};
