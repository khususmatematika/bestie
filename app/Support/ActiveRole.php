<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\RoleName;
use Illuminate\Http\Request;

/**
 * Peran aktif disimpan di sesi dan SELALU divalidasi ulang di server
 * terhadap peran yang benar-benar dimiliki pengguna (aturan rules.md §6.1.4).
 */
final class ActiveRole
{
    public const SESSION_KEY = 'active_role';

    public static function current(Request $request): ?string
    {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        /** @var list<string> $owned */
        $owned = $user->getRoleNames()->all();
        $stored = $request->session()->get(self::SESSION_KEY);

        if (is_string($stored) && in_array($stored, $owned, true)) {
            return $stored;
        }

        foreach (RoleName::priority() as $role) {
            if (in_array($role->value, $owned, true)) {
                $request->session()->put(self::SESSION_KEY, $role->value);

                return $role->value;
            }
        }

        return null;
    }

    public static function switchTo(Request $request, string $role): bool
    {
        $user = $request->user();

        if ($user === null || ! $user->hasRole($role)) {
            return false;
        }

        $request->session()->put(self::SESSION_KEY, $role);

        return true;
    }

    public static function homeRoute(?string $role): string
    {
        return match ($role) {
            RoleName::Admin->value => 'admin.dashboard',
            RoleName::Validator->value => 'validator.dashboard',
            RoleName::Teacher->value => 'teacher.dashboard',
            default => 'login',
        };
    }
}
