<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Enums\RoleName;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\PasswordGenerator;
use Illuminate\Support\Facades\DB;

final class CreateTeacher
{
    /**
     * Membuat akun guru. Kata sandi awal dari admin bila diisi, jika tidak dibuat acak.
     * Email atau NIP cukup salah satu (keduanya dapat dipakai untuk login).
     *
     * @param  array{name: string, email?: string|null, nip?: string|null, phone?: string|null, password?: string|null, must_change_password?: bool|null}  $data
     * @return array{user: User, password: string}
     */
    public function handle(array $data): array
    {
        $password = ! empty($data['password']) ? (string) $data['password'] : PasswordGenerator::generate();
        $mustChange = (bool) ($data['must_change_password'] ?? true);

        $user = DB::transaction(function () use ($data, $password, $mustChange): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => ! empty($data['email']) ? mb_strtolower((string) $data['email']) : null,
                'nip' => $data['nip'] ?? null,
                'password' => $password,
                'must_change_password' => $mustChange,
                'is_active' => true,
            ]);

            $user->assignRole(RoleName::Teacher->value);

            if (! empty($data['phone'])) {
                $user->profile()->create(['phone' => $data['phone']]);
            }

            return $user;
        });

        ActivityLogger::log('teacher.created', $user, ['email' => $user->email, 'nip' => $user->nip]);

        return ['user' => $user, 'password' => $password];
    }
}
