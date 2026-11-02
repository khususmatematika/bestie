<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

/**
 * Akun guru boleh hanya memakai NIP (tanpa email): login dengan email ATAU NIP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Sengaja kosong: mengembalikan ke NOT NULL akan gagal bila sudah ada akun tanpa email.
    }
};
