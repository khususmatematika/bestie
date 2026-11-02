<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('validator_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('grade_level_id')->constrained()->restrictOnDelete();
            $table->string('title', 180);
            $table->string('status', 20)->default('draft');
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->unsignedSmallInteger('total_items')->default(0);
            $table->unsignedSmallInteger('approved_items')->default(0);
            $table->boolean('is_self_validated')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('first_reviewed_at')->nullable();
            $table->timestamp('fixed_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['assessment_period_id', 'status']);
            $table->index(['validator_id', 'status']);
            $table->index(['teacher_id', 'assessment_period_id']);
        });

        Schema::create('package_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('package_id')->constrained('question_packages')->cascadeOnDelete();
            $table->string('kind', 20); // kisi_kisi | naskah | lampiran
            $table->string('original_name');
            $table->string('path');
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('sha256', 64)->nullable();
            $table->string('extraction_status', 20)->default('pending');
            $table->json('extraction_report')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['package_id', 'kind']);
        });

        Schema::create('question_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('package_id')->constrained('question_packages')->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->string('status', 20)->default('belum_ditelaah');
            $table->json('blueprint')->nullable(); // cp, materi, indikator, level, bentuk, bobot
            $table->string('type', 20)->default('pg');
            $table->text('stem')->nullable(); // teks polos (tanpa HTML)
            $table->json('options')->nullable(); // {"A": "...", "B": "..."}
            $table->text('answer_key')->nullable();
            $table->text('scoring_guide')->nullable();
            $table->unsignedBigInteger('current_revision_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('resubmitted_at')->nullable();
            $table->timestamps();

            $table->unique(['package_id', 'number']);
            $table->index(['package_id', 'status']);
        });

        Schema::create('item_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_id')->constrained('question_items')->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->json('snapshot');
            $table->string('source', 20); // ekstraksi | editor | unggah_ulang
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('uploaded_at');
            $table->foreignId('file_id')->nullable()->constrained('package_files')->nullOnDelete();
            $table->timestamps();

            $table->unique(['item_id', 'version']);
        });

        Schema::create('item_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_id')->constrained('question_items')->cascadeOnDelete();
            $table->foreignId('revision_id')->nullable()->constrained('item_revisions')->nullOnDelete();
            $table->foreignId('validator_id')->constrained('users')->restrictOnDelete();
            $table->string('decision', 20); // setuju | ganti | buka_ulang
            $table->json('checklist')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('reviewed_at');
            $table->timestamps();

            $table->index(['item_id', 'reviewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_reviews');
        Schema::dropIfExists('item_revisions');
        Schema::dropIfExists('question_items');
        Schema::dropIfExists('package_files');
        Schema::dropIfExists('question_packages');
    }
};
