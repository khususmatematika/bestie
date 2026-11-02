<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Mengakhiri sesi login pengguna di penyimpanan sesi basis data (mis. setelah ganti sandi / nonaktif).
 */
final class Sessions
{
    public static function revoke(User $user, ?string $except = null): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table((string) config('session.table', 'sessions'))
            ->where('user_id', $user->getKey())
            ->when($except !== null, fn ($q) => $q->where('id', '!=', $except))
            ->delete();
    }
}
