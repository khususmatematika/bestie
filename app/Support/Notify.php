<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use App\Notifications\AppNotification;
use Throwable;

/**
 * Pengirim notifikasi dalam aplikasi. Kegagalan mengirim tidak boleh menggagalkan alur kerja utama.
 */
final class Notify
{
    public static function to(User|int|null $user, string $title, string $body, string $url, string $kind, ?string $key = null): void
    {
        if ($user === null) {
            return;
        }

        try {
            $user = $user instanceof User ? $user : User::query()->find($user);
            $user?->notify(new AppNotification($title, $body, $url, $kind, $key));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Sudah pernah dikirim (kunci sama) kepada pengguna ini? Untuk pengingat harian agar tidak berulang. */
    public static function already(User $user, string $key): bool
    {
        return $user->notifications()->where('data', 'like', '%"key":"'.addcslashes($key, '%_\\').'"%')->exists();
    }
}
