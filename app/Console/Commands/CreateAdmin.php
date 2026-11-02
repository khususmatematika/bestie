<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Membuat (atau mengatur ulang) akun Admin secara interaktif.
 * Email & kata sandi diketik di terminal — tidak disimpan di .env dan tidak dapat
 * diberikan lewat argumen perintah (agar tidak tercatat di riwayat shell).
 */
class CreateAdmin extends Command
{
    protected $signature = 'bestie:create-admin {--reset : Atur ulang kata sandi admin yang sudah ada}';

    protected $description = 'Buat akun Admin awal (interaktif, kata sandi tidak disimpan di .env)';

    public function handle(): int
    {
        $min = (int) config('banksoal.password.min_length', 10);

        $email = mb_strtolower(trim(text(label: 'Email admin', placeholder: 'admin@sekolah.sch.id', required: true)));

        if (Validator::make(['e' => $email], ['e' => ['email']])->fails()) {
            $this->components->error('Format email tidak valid.');

            return self::FAILURE;
        }

        $existing = User::withTrashed()->where('email', $email)->first();

        if ($existing !== null && ! $this->option('reset')) {
            $this->components->error("Email {$email} sudah terdaftar. Gunakan --reset untuk mengatur ulang kata sandinya.");

            return self::FAILURE;
        }

        if ($existing === null && $this->option('reset')) {
            $this->components->error("Tidak ada akun dengan email {$email}.");

            return self::FAILURE;
        }

        $name = $existing?->name ?? text(label: 'Nama admin', default: 'Admin Panitia', required: true);

        $pass = password(
            label: "Kata sandi (minimal {$min} karakter)",
            required: true,
            hint: 'Huruf besar, huruf kecil, dan angka.',
            validate: fn (string $v): ?string => Validator::make(['p' => $v], ['p' => [Password::min($min)->letters()->mixedCase()->numbers()]])->errors()->first('p') ?: null,
        );

        $confirm = password(label: 'Ulangi kata sandi', required: true);

        if (! hash_equals($pass, $confirm)) {
            $this->components->error('Kata sandi tidak sama.');

            return self::FAILURE;
        }

        if ($existing !== null) {
            $existing->forceFill([
                'password' => $pass,
                'must_change_password' => false,
                'is_active' => true,
                'deleted_at' => null,
            ])->save();
            $existing->assignRole(RoleName::Admin->value);
            $this->components->info("Kata sandi admin {$email} diperbarui.");

            return self::SUCCESS;
        }

        $admin = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $pass,
            'must_change_password' => false,
            'is_active' => true,
        ]);
        $admin->assignRole(RoleName::Admin->value);

        $this->components->info("Admin {$email} dibuat. Silakan login.");

        return self::SUCCESS;
    }
}
