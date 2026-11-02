<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\PasswordGenerator;
use Illuminate\Support\Facades\DB;

final class ResetUserPassword
{
    /**
     * Mengatur ulang kata sandi menjadi acak dan memaksa penggantian saat login berikutnya.
     */
    public function handle(User $user): string
    {
        $password = PasswordGenerator::generate();

        $user->forceFill([
            'password' => $password,
            'must_change_password' => true,
        ])->save();

        // Akhiri sesi yang masih aktif agar kata sandi lama tidak dapat dipakai lagi.
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }

        ActivityLogger::log('teacher.password_reset', $user);

        return $password;
    }
}
