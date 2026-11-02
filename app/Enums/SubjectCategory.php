<?php

declare(strict_types=1);

namespace App\Enums;

enum SubjectCategory: string
{
    case Umum = 'umum';
    case Pilihan = 'pilihan';

    public function label(): string
    {
        return match ($this) {
            self::Umum => 'Mapel Umum',
            self::Pilihan => 'Mapel Pilihan',
        };
    }

    /**
     * Menerjemahkan isian bebas (Excel) ke kategori. Mengembalikan null bila tidak dikenali.
     */
    public static function fromInput(?string $value): ?self
    {
        return match (mb_strtolower(trim((string) $value))) {
            'umum', 'u', 'wajib' => self::Umum,
            'pilihan', 'p', 'peminatan' => self::Pilihan,
            default => null,
        };
    }
}
