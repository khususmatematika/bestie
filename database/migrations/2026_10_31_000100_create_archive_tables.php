<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Arsip naskah tahun lalu yang diimpor admin (untuk deteksi kemiripan).
        Schema::create('archive_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grade_level_id')->nullable()->constrained()->nullOnDelete();
            $table->string('year_label', 40);
            $table->string('original_name');
            $table->unsignedInteger('items_count')->default(0);
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['subject_id', 'year_label']);
        });

        Schema::create('archive_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('archive_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('type', 30)->nullable();
            $table->text('stem');
            $table->json('options')->nullable();
            $table->string('answer_key', 500)->nullable();

            $table->index('archive_import_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archive_items');
        Schema::dropIfExists('archive_imports');
    }
};
