<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_exports', function (Blueprint $table): void {
            // Sidik format dokumen (kop, font, dll.) saat naskah dibuat; beda sidik = naskah usang.
            $table->string('format_hash', 40)->nullable()->after('sha256');
        });
    }

    public function down(): void
    {
        Schema::table('package_exports', function (Blueprint $table): void {
            $table->dropColumn('format_hash');
        });
    }
};
