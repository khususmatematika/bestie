<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_items', function (Blueprint $table): void {
            // Daftar id berkas gambar (package_files) yang menyertai butir soal.
            $table->json('images')->nullable()->after('scoring_guide');
        });
    }

    public function down(): void
    {
        Schema::table('question_items', function (Blueprint $table): void {
            $table->dropColumn('images');
        });
    }
};
