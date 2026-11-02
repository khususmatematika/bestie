<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Pembuat kata sandi awal yang mudah dibaca (tanpa karakter membingungkan 0/O, 1/l/I).
 */
final class PasswordGenerator
{
    private const UPPER = 'ABCDEFGHJKLMNPQRSTUVWXYZ';

    private const LOWER = 'abcdefghijkmnopqrstuvwxyz';

    private const DIGIT = '23456789';

    public static function generate(?int $length = null): string
    {
        $length = max(8, $length ?? (int) config('banksoal.password.generated_length', 10));

        $chars = [
            self::pick(self::UPPER),
            self::pick(self::LOWER),
            self::pick(self::DIGIT),
        ];

        $pool = self::UPPER.self::LOWER.self::DIGIT;

        while (count($chars) < $length) {
            $chars[] = self::pick($pool);
        }

        // Acak urutan dengan Fisher-Yates memakai random_int.
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }

    private static function pick(string $set): string
    {
        return $set[random_int(0, strlen($set) - 1)];
    }
}
