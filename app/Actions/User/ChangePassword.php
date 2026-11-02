<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Models\User;
use App\Support\Sessions;
use App\Support\ActivityLogger;

final class ChangePassword
{
    public function handle(User $user, string $newPassword): void
    {
        $user->forceFill([
            'password' => $newPassword,
            'must_change_password' => false,
        ])->save();

        // Sesi di perangkat lain berakhir; sesi yang sedang dipakai dipertahankan.
        Sessions::revoke($user, request()->hasSession() ? request()->session()->getId() : null);

        ActivityLogger::log('auth.password_changed', $user);
    }
}
