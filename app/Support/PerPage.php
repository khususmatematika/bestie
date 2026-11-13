<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Jumlah baris per halaman pada daftar admin: 15 (bawaan), 30, 50, 100, atau "all" (semua, dibatasi 1.000 baris
 * agar halaman tetap ringan).
 */
final class PerPage
{
    public const OPTIONS = [15, 30, 50, 100];

    public const ALL_LIMIT = 1000;

    public static function resolve(Request $request): int
    {
        $value = $request->query('per_page');

        if ($value === 'all') {
            return self::ALL_LIMIT;
        }

        return in_array((int) $value, self::OPTIONS, true) ? (int) $value : 15;
    }
}
