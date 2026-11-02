<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use App\Support\PasswordGenerator;
use Illuminate\Database\Seeder;

/**
 * Membuat akun admin awal. Kata sandi diambil dari ADMIN_PASSWORD di .env;
 * bila kosong, dibuat acak dan ditampilkan SEKALI di terminal.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = mb_strtolower((string) config('banksoal.admin.email'));

        if (User::withTrashed()->where('email', $email)->exists()) {
            $this->command?->info("Admin {$email} sudah ada, dilewati.");

            return;
        }

        $password = config('banksoal.admin.password') ?: PasswordGenerator::generate(14);

        $admin = User::query()->create([
            'name' => (string) config('banksoal.admin.name'),
            'email' => $email,
            'password' => $password,
            'must_change_password' => true,
            'is_active' => true,
        ]);

        $admin->assignRole(RoleName::Admin->value);

        $this->command?->newLine();
        $this->command?->warn('AKUN ADMIN DIBUAT — catat sekarang, tidak akan ditampilkan lagi:');
        $this->command?->line("  Email      : {$email}");
        $this->command?->line("  Kata sandi : {$password}");
        $this->command?->line('  (wajib diganti saat login pertama)');
        $this->command?->newLine();
    }
}
