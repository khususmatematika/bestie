<?php

declare(strict_types=1);

namespace App\Enums;

enum ReviewDecision: string
{
    case Setuju = 'setuju';
    case Ganti = 'ganti';
    case BukaUlang = 'buka_ulang';

    public function label(): string
    {
        return match ($this) {
            self::Setuju => 'Disetujui',
            self::Ganti => 'Diminta ganti',
            self::BukaUlang => 'Dibuka kembali',
        };
    }
}
