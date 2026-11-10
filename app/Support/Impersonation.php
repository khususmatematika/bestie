<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Admin yang sedang "masuk sebagai" guru/validator. ID admin asli disimpan di sesi; selama itu akun yang aktif
 * adalah akun target, sehingga seluruh hak akses mengikuti target (bukan admin).
 */
final class Impersonation
{
    public const SESSION_KEY = 'impersonator_id';

    public const EXPIRES_KEY = 'impersonation_expires_at';

    public static function impersonatorId(?Request $request = null): ?int
    {
        $request ??= request();

        if (! $request->hasSession()) {
            return null;
        }

        $id = $request->session()->get(self::SESSION_KEY);

        return is_numeric($id) ? (int) $id : null;
    }

    public static function active(?Request $request = null): bool
    {
        return self::impersonatorId($request) !== null;
    }

    public static function ttlMinutes(): int
    {
        return max(1, (int) config('banksoal.impersonation.ttl_minutes', 30));
    }

    /** Batas waktu (UNIX) sesi menyamar; null bila tidak ada batas tercatat. */
    public static function expiresAt(?Request $request = null): ?int
    {
        $request ??= request();
        $at = $request->hasSession() ? $request->session()->get(self::EXPIRES_KEY) : null;

        return is_numeric($at) ? (int) $at : null;
    }

    public static function expired(?Request $request = null): bool
    {
        // Sesi menyamar tanpa batas waktu tercatat dianggap kedaluwarsa (aman secara bawaan).
        return self::active($request) && (self::expiresAt($request) ?? 0) <= now()->getTimestamp();
    }

    /**
     * Akhiri penyamaran dan kembalikan sesi ke admin asli.
     * Mengembalikan admin bila berhasil; null bila admin tidak valid (sesi dibersihkan, pengguna harus login ulang).
     */
    public static function restoreAdmin(Request $request, string $reason = 'manual'): ?User
    {
        $adminId = self::impersonatorId($request);
        $admin = $adminId === null ? null : User::query()->find($adminId);

        if ($admin === null || ! $admin->isAdmin() || ! $admin->is_active) {
            $request->session()->forget([self::SESSION_KEY, self::EXPIRES_KEY]);

            return null;
        }

        ActivityLogger::log('auth.impersonate.stop', $request->user(), ['reason' => $reason], $admin);

        Auth::guard('web')->login($admin);
        $request->session()->forget([self::SESSION_KEY, self::EXPIRES_KEY]);
        $request->session()->regenerate();
        $request->session()->put(ActiveRole::SESSION_KEY, RoleName::Admin->value);

        return $admin;
    }
}
